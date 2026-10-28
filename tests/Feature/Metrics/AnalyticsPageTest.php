<?php

use App\Enums\MetricsStatus;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Models\AccountMetric;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\InstanceSettings;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;

function createAnalyticsTarget(Post $post, array $attributes): PostTarget
{
    $accountId = $attributes['connected_account_id'] ?? ConnectedAccount::withoutGlobalScopes()
        ->where('workspace_id', $post->workspace_id)
        ->where('platform', $attributes['platform'] ?? Platform::X->value)
        ->whereNotIn('id', PostTarget::query()->where('post_id', $post->id)->select('connected_account_id'))
        ->value('id');

    if ($accountId === null) {
        $accountId = ConnectedAccount::factory()
            ->for(Workspace::query()->findOrFail($post->workspace_id))
            ->create(['platform' => $attributes['platform'] ?? Platform::X->value])
            ->id;
    }

    return PostTarget::factory()->create([
        ...$attributes,
        'post_id' => $post->id,
        'connected_account_id' => $accountId,
    ]);
}

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    Context::add('workspace_id', $this->workspace->id);
});

test('analytics page renders with accounts and range', function (): void {
    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/index', shouldExist: false)
            ->has('accounts')
            ->has('posts')
            ->has('comparison')
            ->where('rangeDays', 90));
});

test('the summary reports total followers with a window delta', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();

    // Two readings inside the default 90-day window: 100 → 130 = +30.
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now()->subDays(10), 'followers' => 100]);
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now(), 'followers' => 130]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.account_count', 1)
            ->where('summary.followers.value', 130)
            ->where('summary.followers.delta', 30)
            ->where('accounts.0.followers_delta', 30));
});

test('the follower delta is null without two comparable readings', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now(), 'followers' => 100]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.followers.value', 100)
            ->where('summary.followers.delta', null)
            ->where('accounts.0.followers_delta', null));
});

test('no connected accounts reports missing measurements and a known zero post count', function (): void {
    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.followers.value', null)
            ->where('summary.followers.coverage.measured', 0)
            ->where('summary.followers.coverage.total', 0)
            ->where('summary.engagement.value', null)
            ->where('summary.engagement.coverage.measured', 0)
            ->where('summary.engagement.coverage.total', 0)
            ->where('summary.posts.value', 0)
            ->where('summary.posts.delta', 0));
});

test('an account without readings is not represented as zero followers', function (): void {
    ConnectedAccount::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.followers.value', null)
            ->where('summary.followers.coverage.measured', 0)
            ->where('summary.followers.coverage.total', 1)
            ->where('accounts.0.latest_followers', null)
            ->where('summary.followers.delta', null));
});

test('valid zero follower and engagement measurements remain zero', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();
    AccountMetric::factory()->create([
        'connected_account_id' => $account->id,
        'captured_at' => Date::now(),
        'followers' => 0,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($post, [
        'post_id' => $post->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 0,
        'comments' => 0,
        'reposts' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.followers.value', 0)
            ->where('summary.followers.coverage.measured', 1)
            ->where('summary.followers.coverage.total', 1)
            ->where('summary.engagement.value', 0)
            ->where('summary.engagement.coverage.measured', 1)
            ->where('summary.engagement.coverage.total', 1));
});

test('follower total declares partial account coverage', function (): void {
    $measuredAccount = ConnectedAccount::factory()->for($this->workspace)->create();
    ConnectedAccount::factory()->for($this->workspace)->create();
    AccountMetric::factory()->create([
        'connected_account_id' => $measuredAccount->id,
        'captured_at' => Date::now(),
        'followers' => 135,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.followers.value', 135)
            ->where('summary.followers.coverage.measured', 1)
            ->where('summary.followers.coverage.total', 2)
            ->where('summary.followers.delta', null));
});

test('the engagement summary compares against the previous window', function (): void {
    // Previous window (10–20 days ago): one post with 40 engagement.
    $previous = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDays(15),
    ]);
    createAnalyticsTarget($previous, [
        'post_id' => $previous->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 40, 'comments' => 0, 'reposts' => 0,
    ]);

    // Current window (last 10 days): one post with 100 engagement.
    $current = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($current, [
        'post_id' => $current->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 70, 'comments' => 20, 'reposts' => 10,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.engagement.value', 100)
            ->where('summary.engagement.delta', 60)
            ->where('summary.posts.value', 1)
            ->where('summary.posts.delta', 0));
});

test('engagement has no baseline when the previous window has no posts', function (): void {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($post, [
        'post_id' => $post->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 50, 'comments' => 0, 'reposts' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 7]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.engagement.value', 50)
            ->where('summary.engagement.delta', null)
            ->where('summary.posts.delta', 1));
});

test('engagement delta is null when the previous window has posts but no captured metrics', function (): void {
    // Previous window has a post, but its target never captured Ok metrics — so
    // there is no engagement baseline even though a post exists.
    $previous = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDays(15),
    ]);
    foreach ([MetricsStatus::Failed, MetricsStatus::Unsupported, MetricsStatus::RateLimited] as $status) {
        createAnalyticsTarget($previous, [
            'post_id' => $previous->id,
            'metrics_status' => $status->value,
            'likes' => 500, 'comments' => 200, 'reposts' => 100,
        ]);
    }

    $current = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($current, [
        'post_id' => $current->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 50, 'comments' => 0, 'reposts' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.engagement.value', 50)
            // No measured post last window → no fake "+50" spike.
            ->where('summary.engagement.delta', null)
            // The post count baseline still exists (one post each window).
            ->where('summary.posts.delta', 0));
});

test('summary and ranking include only successfully measured destinations', function (): void {
    $previous = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDays(15),
    ]);
    createAnalyticsTarget($previous, [
        'post_id' => $previous->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 4, 'comments' => 0, 'reposts' => 0,
    ]);
    foreach ([MetricsStatus::Failed, MetricsStatus::Unsupported, MetricsStatus::RateLimited] as $status) {
        createAnalyticsTarget($previous, [
            'post_id' => $previous->id,
            'metrics_status' => $status->value,
            'likes' => 500, 'comments' => 200, 'reposts' => 100,
        ]);
    }

    $current = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($current, [
        'post_id' => $current->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 10, 'comments' => 0, 'reposts' => 0,
    ]);
    foreach ([MetricsStatus::Failed, MetricsStatus::Unsupported, MetricsStatus::RateLimited] as $status) {
        createAnalyticsTarget($current, [
            'post_id' => $current->id,
            'metrics_status' => $status->value,
            'likes' => 500, 'comments' => 200, 'reposts' => 100,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.engagement.value', 10)
            ->where('summary.engagement.delta', 6)
            ->where('summary.engagement.coverage.measured', 1)
            ->where('summary.engagement.coverage.total', 4)
            ->where('comparison.top.0.engagement', 10)
            ->where('comparison.top.0.measured_targets', 1)
            ->where('comparison.top.0.total_targets', 4));
});

test('analytics queries remain isolated to the active workspace', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();
    AccountMetric::factory()->create([
        'connected_account_id' => $account->id,
        'captured_at' => Date::now(),
        'followers' => 7,
    ]);
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($post, [
        'post_id' => $post->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 9, 'comments' => 0, 'reposts' => 0,
    ]);

    $otherWorkspace = Workspace::factory()->create();
    $otherAccount = ConnectedAccount::factory()->for($otherWorkspace)->create();
    AccountMetric::factory()->create([
        'connected_account_id' => $otherAccount->id,
        'captured_at' => Date::now(),
        'followers' => 700,
    ]);
    $otherPost = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'author_id' => User::factory()->create()->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($otherPost, [
        'post_id' => $otherPost->id,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 900, 'comments' => 0, 'reposts' => 0,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
            ->where('summary.followers.value', 7)
            ->where('summary.engagement.value', 9)
            ->has('posts', 1)
            ->where('comparison.top.0.id', $post->id));
});

test('combined platform and account filters stay consistent across screen print and CSV', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create([
        'platform' => Platform::Instagram->value,
        'handle' => '@=HYPERLINK("https://example.invalid","profile"),line'."\n".'next',
    ]);
    $otherAccount = ConnectedAccount::factory()->for($this->workspace)->create([
        'platform' => Platform::X->value,
        'handle' => '@other',
    ]);
    AccountMetric::factory()->create([
        'connected_account_id' => $account->id,
        'captured_at' => Date::now(),
        'followers' => 0,
    ]);
    AccountMetric::factory()->create([
        'connected_account_id' => $otherAccount->id,
        'captured_at' => Date::now(),
        'followers' => 999,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'base_text' => '=HYPERLINK("https://example.invalid","post title")',
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($post, [
        'post_id' => $post->id,
        'connected_account_id' => $account->id,
        'platform' => Platform::Instagram->value,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 0,
        'comments' => 0,
        'reposts' => 0,
    ]);
    $otherPost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => Date::now()->subDay(),
    ]);
    createAnalyticsTarget($otherPost, [
        'post_id' => $otherPost->id,
        'connected_account_id' => $otherAccount->id,
        'platform' => Platform::X->value,
        'metrics_status' => MetricsStatus::Ok->value,
        'likes' => 50,
    ]);

    $query = [
        'days' => 7,
        'platform' => Platform::Instagram->value,
        'account_id' => $account->id,
    ];

    $this->actingAs($this->user)
        ->get(route('analytics.index', $query))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.platform', Platform::Instagram->value)
            ->where('filters.account_id', $account->id)
            ->where('summary.followers.value', 0)
            ->where('summary.engagement.value', 0)
            ->where('summary.engagement.coverage.measured', 1)
            ->where('summary.engagement.coverage.total', 1)
            ->where('summary.posts.value', 1)
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
            ->has('posts', 1)
            ->where('posts.0.id', $post->id)
            ->has('ranking', 1)
            ->where('ranking.0.id', $post->id));

    $this->get(route('analytics.print', $query))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/report-print', shouldExist: false)
            ->where('filters.account_id', $account->id)
            ->where('summary.posts.value', 1)
            ->where('posts.0.id', $post->id)
            ->has('definitions', 3));

    $csv = $this->get(route('analytics.export.csv', $query))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain($account->id)
        ->toContain($post->id)
        ->toContain('\'@=HYPERLINK')
        ->toContain('""https://example.invalid""')
        ->toContain("\nnext\"")
        ->not->toContain($otherAccount->id)
        ->not->toContain($otherPost->id);
});

test('analytics report rejects account ids from another workspace on every endpoint', function (): void {
    $foreignWorkspace = Workspace::factory()->create();
    $foreignAccount = ConnectedAccount::factory()->for($foreignWorkspace)->create([
        'platform' => Platform::Instagram->value,
    ]);
    $query = ['days' => 7, 'platform' => Platform::Instagram->value, 'account_id' => $foreignAccount->id];

    $this->actingAs($this->user)
        ->get(route('analytics.index', $query))
        ->assertNotFound();

    $this->get(route('analytics.print', $query))->assertNotFound();
    $this->get(route('analytics.export.csv', $query))->assertNotFound();
});

test('analytics period boundaries use the workspace timezone across daylight saving changes', function (): void {
    $this->workspace->forceFill(['timezone' => 'America/Los_Angeles'])->save();
    Date::setTestNow('2026-03-09T12:00:00Z');

    $account = ConnectedAccount::factory()->for($this->workspace)->create([
        'platform' => Platform::Instagram->value,
    ]);
    AccountMetric::factory()->create([
        'connected_account_id' => $account->id,
        'captured_at' => Date::parse('2026-03-02T12:59:00Z'),
        'followers' => 44,
    ]);
    AccountMetric::factory()->create([
        'connected_account_id' => $account->id,
        'captured_at' => Date::parse('2026-03-02T13:00:00Z'),
        'followers' => 55,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 7, 'platform' => 'instagram']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.from', '2026-03-02')
            ->where('filters.to', '2026-03-09')
            ->where('filters.timezone', 'America/Los_Angeles')
            ->where('summary.followers.value', 55)
            ->has('accounts.0.series', 1));

    Date::setTestNow();
});

test('analytics polling settings are keyed by platform enum values', function (): void {
    app(InstanceSettings::class)->update([
        'post_metrics_polling_enabled' => [
            Platform::X->value => false,
            Platform::Bluesky->value => true,
            Platform::LinkedIn->value => true,
        ],
        'account_metrics_polling_enabled' => [
            Platform::X->value => true,
            Platform::Bluesky->value => false,
            Platform::LinkedIn->value => true,
        ],
    ]);

    $expectedPlatforms = collect(Platform::cases())
        ->mapWithKeys(fn (Platform $platform): array => [$platform->value => true])
        ->all();

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('polling.post_metrics_enabled', [
                ...$expectedPlatforms,
                Platform::X->value => false,
            ])
            ->where('polling.account_metrics_enabled', [
                ...$expectedPlatforms,
                Platform::Bluesky->value => false,
            ]));
});

test('range is clamped to 365', function (): void {
    $this->actingAs($this->user)
        ->get(route('analytics.index', ['days' => 5000]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rangeDays', 365));
});

test('analytics 404s when metrics disabled', function (): void {
    config(['metrics.enabled' => false]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertNotFound();
});

test('comparison collapses to single ranked list when fewer than 10 eligible posts', function (): void {
    // Create 3 published posts with at least one ok PostTarget each.
    $posts = Post::factory(3)->create([
        'workspace_id' => $this->workspace->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->subDay(),
    ]);

    // Give each post a different engagement total so ranking is deterministic.
    $engagements = [10, 30, 20];
    foreach ($posts as $i => $post) {
        createAnalyticsTarget($post, [
            'post_id' => $post->id,
            'metrics_status' => MetricsStatus::Ok->value,
            'likes' => $engagements[$i],
            'comments' => 0,
            'reposts' => 0,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/index', shouldExist: false)
            ->where('comparison.bottom', [])
            ->has('comparison.top', 3)
            ->where('comparison.top.0.engagement', 30)
            ->where('comparison.top.1.engagement', 20)
            ->where('comparison.top.2.engagement', 10));
});

test('discord accounts are excluded from follower analytics', function (): void {
    $x = ConnectedAccount::factory()->for($this->workspace)->create([
        'platform' => Platform::X,
    ]);
    ConnectedAccount::factory()->for($this->workspace)->discord()->create();

    AccountMetric::factory()->create([
        'connected_account_id' => $x->id,
        'captured_at' => Date::now(),
        'followers' => 42,
    ]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.id', $x->id)
            ->where('accounts.0.platform', Platform::X->value));
});

test('the follower series is downsampled to one point per day', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();
    $previousDay = Date::now()->subDay()->startOfDay();

    // Three readings on the same day collapse to the last one (30).
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => $previousDay->copy()->setTime(8, 0), 'followers' => 10]);
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => $previousDay->copy()->setTime(14, 0), 'followers' => 20]);
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => $previousDay->copy()->setTime(20, 0), 'followers' => 30]);
    // One reading the next day.
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now()->subHour(), 'followers' => 40]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts.0.series', 2)
            ->where('accounts.0.series.0.followers', 30)
            ->where('accounts.0.series.1.followers', 40)
            ->where('accounts.0.latest_followers', 40));
});

test('the analytics rollup reflects newly captured metrics on the next page load', function (): void {
    $account = ConnectedAccount::factory()->for($this->workspace)->create();
    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now()->subHours(2), 'followers' => 100]);

    $this->actingAs($this->user)->get(route('analytics.index'))->assertOk();

    AccountMetric::factory()->create(['connected_account_id' => $account->id, 'captured_at' => Date::now(), 'followers' => 999]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertInertia(fn ($page) => $page->where('accounts.0.latest_followers', 999));
});

test('disconnecting an account removes it from cached analytics', function (): void {
    $keptAccount = ConnectedAccount::factory()->for($this->workspace)->create();
    $disconnectedAccount = ConnectedAccount::factory()->for($this->workspace)->create();

    AccountMetric::factory()->create(['connected_account_id' => $keptAccount->id, 'captured_at' => Date::now(), 'followers' => 100]);
    AccountMetric::factory()->create(['connected_account_id' => $disconnectedAccount->id, 'captured_at' => Date::now(), 'followers' => 200]);

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('accounts', 2));

    $this->actingAs($this->user)
        ->delete(route('accounts.destroy', $disconnectedAccount))
        ->assertRedirect(route('accounts.index'));

    $this->actingAs($this->user)
        ->get(route('analytics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 1)
            ->where('accounts.0.id', $keptAccount->id));
});
