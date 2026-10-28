<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MetricsStatus;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\AccountMetric;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\Workspace;
use App\Support\InstanceSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class AnalyticsController extends Controller
{
    public function index(AnalyticsFilterRequest $request, InstanceSettings $settings): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('analytics/index', [
            ...$this->buildPayload($filters),
            'rangeDays' => $filters['days'],
            'polling' => [
                'post_metrics_enabled' => collect(Platform::cases())
                    ->mapWithKeys(fn (Platform $platform): array => [
                        $platform->value => $settings->postMetricsPollingEnabled($platform),
                    ])
                    ->all(),
                'account_metrics_enabled' => collect(Platform::cases())
                    ->mapWithKeys(fn (Platform $platform): array => [
                        $platform->value => $settings->accountMetricsPollingEnabled($platform),
                    ])
                    ->all(),
            ],
        ]);
    }

    public function print(AnalyticsFilterRequest $request): Response
    {
        return Inertia::render('analytics/report-print', $this->buildPayload($this->filters($request)));
    }

    public function exportCsv(AnalyticsFilterRequest $request): SymfonyStreamedResponse
    {
        $payload = $this->buildPayload($this->filters($request));
        $filename = 'analytics-'.$payload['filters']['from'].'-'.$payload['filters']['to'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            $writeRow = function (array $row) use ($stream): void {
                fputcsv($stream, array_map($this->csvSafeValue(...), $row), ',', '"', '');
            };

            $writeRow(['section', 'field', 'value', 'measured', 'total']);
            $writeRow(['report', 'workspace', $payload['workspace']['name']]);
            $writeRow(['report', 'period_start', $payload['filters']['from_at']]);
            $writeRow(['report', 'period_end', $payload['filters']['to_at']]);
            $writeRow(['report', 'platform', $payload['filters']['platform'] ?? 'all']);
            $writeRow(['report', 'account_id', $payload['filters']['account_id'] ?? 'all']);
            $writeRow(['report', 'timezone', $payload['filters']['timezone']]);
            $writeRow(['report', 'generated_at', $payload['generatedAt']]);
            $writeRow(['summary', 'followers', $payload['summary']['followers']['value'], $payload['summary']['followers']['coverage']['measured'], $payload['summary']['followers']['coverage']['total']]);
            $writeRow(['summary', 'engagement', $payload['summary']['engagement']['value'], $payload['summary']['engagement']['coverage']['measured'], $payload['summary']['engagement']['coverage']['total']]);
            $writeRow(['summary', 'published_posts', $payload['summary']['posts']['value']]);

            foreach ($payload['definitions'] as $definition) {
                $writeRow(['definition', 'metric', $definition]);
            }

            $writeRow(['accounts', 'id', 'platform', 'handle', 'followers', 'followers_delta']);

            foreach ($payload['accounts'] as $account) {
                $writeRow([
                    'account',
                    $account['id'],
                    $account['platform'],
                    $account['handle'],
                    $account['latest_followers'],
                    $account['followers_delta'],
                ]);
            }

            $writeRow(['posts', 'post_id', 'platforms', 'published_at', 'engagement', 'measured_targets', 'total_targets']);

            foreach ($payload['posts'] as $post) {
                $writeRow([
                    'post',
                    $post['id'],
                    implode('|', $post['platforms']),
                    $post['published_at'],
                    $post['engagement'],
                    $post['measured_targets'],
                    $post['total_targets'],
                ]);
            }

            $writeRow(['ranking', 'post_id', 'platforms', 'published_at', 'engagement', 'measured_targets', 'total_targets']);

            foreach ($payload['ranking'] as $post) {
                $writeRow([
                    'ranked_post',
                    $post['id'],
                    implode('|', $post['platforms']),
                    $post['published_at'],
                    $post['engagement'],
                    $post['measured_targets'],
                    $post['total_targets'],
                ]);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{
     *     days: int,
     *     platform: string|null,
     *     account_id: string|null,
     *     workspace_id: string,
     *     timezone: string,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     previous_from: CarbonImmutable,
     *     workspace: Workspace
     * }
     */
    private function filters(AnalyticsFilterRequest $request): array
    {
        $workspaceId = (string) $request->user()->current_workspace_id;
        $workspace = Workspace::query()->whereKey($workspaceId)->firstOrFail();
        $validated = $request->validated();
        $days = max(7, min(365, (int) ($validated['days'] ?? 90)));
        $platform = is_string($validated['platform'] ?? null) ? $validated['platform'] : null;
        $accountId = is_string($validated['account_id'] ?? null) ? $validated['account_id'] : null;

        if ($accountId !== null) {
            $selectedAccount = ConnectedAccount::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereKey($accountId)
                ->first();

            abort_if($selectedAccount === null || $selectedAccount->platform === Platform::Discord, 404);
            abort_if($platform !== null && $platform !== $selectedAccount->platform->value, 404);
            $platform = $selectedAccount->platform->value;
        }

        $now = Date::now($workspace->timezone);
        $from = $now->copy()->subDays($days);
        $to = $now;
        $previousFrom = $from->copy()->subDays($days);

        return [
            'days' => $days,
            'platform' => $platform,
            'account_id' => $accountId,
            'workspace_id' => $workspaceId,
            'timezone' => $workspace->timezone,
            'from' => $from,
            'to' => $to,
            'previous_from' => $previousFrom,
            'workspace' => $workspace,
        ];
    }

    /**
     * @param  array{
     *     days: int,
     *     platform: string|null,
     *     account_id: string|null,
     *     workspace_id: string,
     *     timezone: string,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     previous_from: CarbonImmutable,
     *     workspace: Workspace
     * }  $filters
     * @return array<string, mixed>
     */
    private function buildPayload(array $filters): array
    {
        $days = $filters['days'];
        $from = $filters['from']->copy()->setTimezone('UTC');
        $to = $filters['to']->copy()->setTimezone('UTC');
        $previousFrom = $filters['previous_from']->copy()->setTimezone('UTC');

        // Discord webhooks have no follower/member metrics — omit them from the
        // follower growth chart and account cards entirely.
        $accounts = ConnectedAccount::query()
            ->where('workspace_id', $filters['workspace_id'])
            ->where('platform', '!=', Platform::Discord->value)
            ->when($filters['platform'] !== null, fn (Builder $query): Builder => $query->where('platform', $filters['platform']))
            ->when($filters['account_id'] !== null, fn (Builder $query): Builder => $query->whereKey($filters['account_id']))
            ->with(['metrics' => fn ($q) => $q
                ->where('captured_at', '>=', $from)
                ->where('captured_at', '<=', $to)
                ->whereNotNull('followers')
                ->orderBy('captured_at')
                // Drop the per-row `raw` API-response JSON blob — the series only
                // needs follower/following counts.
                ->select(['id', 'connected_account_id', 'captured_at', 'followers', 'following'])])
            ->get()
            ->map(fn (ConnectedAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'handle' => $account->handle,
                'display_name' => $account->display_name,
                'avatar_url' => $account->avatar_url,
                'status' => $account->metrics_status?->value,
                'latest_followers' => $account->metrics->last()?->followers,
                'followers_delta' => $this->followerDelta($account->metrics),
                'series' => $this->downsampleDaily($account->metrics, $filters['timezone']),
            ])->all();

        $posts = Post::query()
            ->with(['targets' => fn ($relation) => $this->applyTargetFilters($relation->getQuery(), $filters)
                ->select(['id', 'post_id', 'connected_account_id', 'platform', 'likes', 'comments', 'reposts', 'metrics_status'])])
            ->where('workspace_id', $filters['workspace_id'])
            ->whereIn('status', [PostStatus::Published->value, PostStatus::Partial->value])
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $from)
            ->where('published_at', '<=', $to)
            ->whereHas('targets', fn (Builder $query) => $this->applyTargetFilters($query, $filters))
            ->orderBy('published_at')
            ->get();

        $markers = $posts->map(function (Post $post): array {
            $measuredTargets = $post->targets->filter(
                fn (PostTarget $target): bool => $target->metrics_status === MetricsStatus::Ok,
            );

            return [
                'id' => $post->id,
                'title' => $this->resolveTitle($post),
                'published_at' => $post->published_at?->toIso8601String(),
                'platforms' => $post->targets->pluck('platform')->map(fn ($platform): string => $platform->value)->unique()->values()->all(),
                'engagement' => $measuredTargets->isEmpty()
                    ? null
                    : (int) $measuredTargets->sum(fn (PostTarget $target): int => $target->likes + $target->comments + $target->reposts),
                'measured_targets' => $measuredTargets->count(),
                'total_targets' => $post->targets->count(),
            ];
        })->all();

        $postsWithMeasuredTargets = $posts
            ->filter(fn (Post $post): bool => $post->targets->contains(
                fn (PostTarget $t): bool => $t->metrics_status === MetricsStatus::Ok,
            ));

        $ranked = $postsWithMeasuredTargets
            ->map(function (Post $post): array {
                $measuredTargets = $post->targets->filter(
                    fn (PostTarget $target): bool => $target->metrics_status === MetricsStatus::Ok,
                );

                return [
                    'id' => $post->id,
                    'title' => $this->resolveTitle($post),
                    'published_at' => $post->published_at?->toIso8601String(),
                    'platforms' => $post->targets->pluck('platform')->map(fn ($platform): string => $platform->value)->unique()->values()->all(),
                    'engagement' => (int) $measuredTargets->sum(fn (PostTarget $target): int => $target->likes + $target->comments + $target->reposts),
                    'measured_targets' => $measuredTargets->count(),
                    'total_targets' => $post->targets->count(),
                ];
            })
            ->sortByDesc('engagement')
            ->values();

        $totalTargets = $posts->sum(fn (Post $post): int => $post->targets->count());
        $measuredTargets = $posts->sum(fn (Post $post): int => $post->targets
            ->filter(fn (PostTarget $target): bool => $target->metrics_status === MetricsStatus::Ok)
            ->count());
        $totalEngagement = $measuredTargets === 0
            ? null
            : (int) $ranked->sum('engagement');

        $comparisonTop = $ranked->count() < 10
            ? $ranked->values()->all()
            : $ranked->take(5)->values()->all();

        $comparisonBottom = $ranked->count() < 10
            ? []
            : $ranked->reverse()->take(5)->values()->all();

        return [
            'accounts' => $accounts,
            'posts' => $markers,
            'ranking' => $ranked->all(),
            'summary' => $this->buildSummary(
                $accounts,
                $totalEngagement,
                $measuredTargets,
                $totalTargets,
                $posts->count(),
                $previousFrom,
                $from,
                $filters,
            ),
            'comparison' => [
                'top' => $comparisonTop,
                'bottom' => $comparisonBottom,
            ],
            'filters' => [
                'days' => $days,
                'platform' => $filters['platform'],
                'account_id' => $filters['account_id'],
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'from_at' => $filters['from']->toIso8601String(),
                'to_at' => $filters['to']->toIso8601String(),
                'timezone' => $filters['timezone'],
            ],
            'workspace' => [
                'name' => $filters['workspace']->name,
            ],
            'generatedAt' => Date::now($filters['timezone'])->toIso8601String(),
            'reportUrls' => [
                'print' => route('analytics.print', [
                    'days' => $days,
                    'platform' => $filters['platform'],
                    'account_id' => $filters['account_id'],
                ]),
                'csv' => route('analytics.export.csv', [
                    'days' => $days,
                    'platform' => $filters['platform'],
                    'account_id' => $filters['account_id'],
                ]),
            ],
            'definitions' => [
                'Followers are the latest available snapshot per account in the selected period; totals are summed by account and are not unique people.',
                'Engagement is the latest successful likes, comments, and reposts for published or partially published targets in the selected period; counters are not necessarily actions that occurred within that period.',
                'Coverage reports measured accounts or destinations divided by all accounts or destinations in the same filtered set. Null means no measurement; zero is a valid measurement.',
            ],
            'filterOptions' => [
                'platforms' => collect(Platform::cases())
                    ->reject(fn (Platform $candidate): bool => $candidate === Platform::Discord)
                    ->map(fn (Platform $candidate): array => ['value' => $candidate->value, 'label' => $candidate->label()])
                    ->values()
                    ->all(),
                'accounts' => ConnectedAccount::query()
                    ->where('workspace_id', $filters['workspace_id'])
                    ->where('platform', '!=', Platform::Discord->value)
                    ->when($filters['platform'] !== null, fn (Builder $query): Builder => $query->where('platform', $filters['platform']))
                    ->orderBy('handle')
                    ->get(['id', 'platform', 'handle', 'display_name'])
                    ->map(fn (ConnectedAccount $account): array => [
                        'id' => $account->id,
                        'platform' => $account->platform->value,
                        'handle' => $account->handle,
                        'display_name' => $account->display_name,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @param  Builder<PostTarget>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<PostTarget>
     */
    private function applyTargetFilters(Builder $query, array $filters): Builder
    {
        return $query->whereHas('account', function (Builder $accountQuery) use ($filters): void {
            $accountQuery
                ->where('workspace_id', $filters['workspace_id'])
                ->where('platform', '!=', Platform::Discord->value)
                ->when($filters['platform'] !== null, fn (Builder $builder): Builder => $builder->where('platform', $filters['platform']))
                ->when($filters['account_id'] !== null, fn (Builder $builder): Builder => $builder->whereKey($filters['account_id']));
        });
    }

    private function csvSafeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * The follower delta is growth across the selected window. Engagement and
     * post deltas compare against the previous equal-length window.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  array{
     *     days: int,
     *     platform: string|null,
     *     account_id: string|null,
     *     workspace_id: string,
     *     timezone: string,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     previous_from: CarbonImmutable,
     *     workspace: Workspace
     * }  $filters
     * @return array{
     *     account_count: int,
     *     followers: array{value: int|null, delta: int|null, coverage: array{measured: int, total: int}},
     *     engagement: array{value: int|null, delta: int|null, coverage: array{measured: int, total: int}},
     *     posts: array{value: int, delta: int|null},
     * }
     */
    private function buildSummary(
        array $accounts,
        ?int $totalEngagement,
        int $measuredTargetCount,
        int $totalTargetCount,
        int $postsCount,
        CarbonInterface $previousFrom,
        CarbonInterface $from,
        array $filters,
    ): array {
        $accountsCollection = collect($accounts);

        $measuredAccounts = $accountsCollection->filter(fn (array $account): bool => $account['latest_followers'] !== null);
        $totalFollowers = $measuredAccounts->isEmpty()
            ? null
            : (int) $measuredAccounts->sum(fn (array $account): int => (int) $account['latest_followers']);
        $trackedDeltas = $accountsCollection->pluck('followers_delta')->filter(fn ($d): bool => $d !== null);
        $hasFullFollowerBaseline = $accountsCollection->isNotEmpty()
            && $trackedDeltas->count() === $accountsCollection->count();
        $followersDelta = $hasFullFollowerBaseline ? (int) $trackedDeltas->sum() : null;

        // Previous window — only used as a baseline for the delta chips.
        $previousPosts = Post::query()
            ->with(['targets' => fn ($relation) => $this->applyTargetFilters($relation->getQuery(), $filters)
                ->select(['id', 'post_id', 'connected_account_id', 'platform', 'likes', 'comments', 'reposts', 'metrics_status'])])
            ->where('workspace_id', $filters['workspace_id'])
            ->whereIn('status', [PostStatus::Published->value, PostStatus::Partial->value])
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $previousFrom)
            ->where('published_at', '<', $from)
            ->whereHas('targets', fn (Builder $query) => $this->applyTargetFilters($query, $filters))
            ->get();

        // Engagement is only measurable on posts that captured Ok metrics, so the
        // baseline for the engagement delta must also require such a post — a
        // previous window of unmeasured posts is not a zero baseline.
        $previousMeasuredTargets = $previousPosts->flatMap(
            fn (Post $post) => $post->targets->filter(
                fn (PostTarget $target): bool => $target->metrics_status === MetricsStatus::Ok,
            ),
        );
        $hasEngagementBaseline = $previousMeasuredTargets->isNotEmpty();
        $previousEngagement = (int) $previousMeasuredTargets->sum(
            fn (PostTarget $target): int => $target->likes + $target->comments + $target->reposts,
        );

        return [
            'account_count' => $accountsCollection->count(),
            'followers' => [
                'value' => $totalFollowers,
                'delta' => $followersDelta,
                'coverage' => [
                    'measured' => $measuredAccounts->count(),
                    'total' => $accountsCollection->count(),
                ],
            ],
            'engagement' => [
                'value' => $totalEngagement,
                'delta' => $hasEngagementBaseline && $totalEngagement !== null
                    ? $totalEngagement - $previousEngagement
                    : null,
                'coverage' => [
                    'measured' => $measuredTargetCount,
                    'total' => $totalTargetCount,
                ],
            ],
            'posts' => [
                'value' => $postsCount,
                'delta' => $postsCount - $previousPosts->count(),
            ],
        ];
    }

    /**
     * Change in followers across the window: latest reading minus the earliest.
     * Null unless there are at least two comparable readings.
     *
     * @param  Collection<int, AccountMetric>  $metrics
     */
    private function followerDelta(Collection $metrics): ?int
    {
        if ($metrics->count() < 2) {
            return null;
        }

        $first = $metrics->first()?->followers;
        $last = $metrics->last()?->followers;

        if ($first === null || $last === null) {
            return null;
        }

        return $last - $first;
    }

    /**
     * Collapse a chronologically-ordered metric collection to one point per day
     * (the last reading of each day), bounding the series sent to the client
     * regardless of how often metrics are captured.
     *
     * @param  Collection<int, AccountMetric>  $metrics
     * @return array<int, array{at: string, followers: int|null, following: int|null}>
     */
    private function downsampleDaily(Collection $metrics, string $timezone): array
    {
        return $metrics
            ->groupBy(fn (AccountMetric $m): string => $m->captured_at->setTimezone($timezone)->toDateString())
            ->map(fn ($dayMetrics): AccountMetric => $dayMetrics->last())
            ->map(fn (AccountMetric $m): array => [
                'at' => $m->captured_at->toIso8601String(),
                'followers' => $m->followers,
                'following' => $m->following,
            ])
            ->values()
            ->all();
    }

    private function resolveTitle(Post $post): string
    {
        $first = trim((string) Str::of($post->base_text)->explode("\n")->first());

        return Str::limit($first, 60) ?: 'Untitled post';
    }
}
