import { Head, Link } from '@inertiajs/react';
import { lazy, Suspense } from 'react';

import { formatAnalyticsDate } from '@/components/analytics/follower-chart-utils';
import { StatTile } from '@/components/analytics/stat-tile';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Card, CardDescription, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { PauseCircle, TrendingUp } from '@/components/ui/icons';
import { disabledPlatformLabels } from '@/lib/platforms';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as analyticsRoute } from '@/routes/analytics';
import { show as postRoute } from '@/routes/posts';
import type {
    AnalyticsPageProps,
    AnalyticsComparisonRow,
} from '@/types/metrics';

// Lazily loaded so recharts is fetched only when there is series data to plot.
const FollowerChart = lazy(
    () => import('@/components/analytics/follower-chart'),
);

const RANGE_OPTIONS = [
    { days: 7, label: '7d' },
    { days: 14, label: '14d' },
    { days: 30, label: '30d' },
    { days: 90, label: '90d' },
];

// Trimming the response to just the range-dependent props keeps switching snappy.
const RANGE_RELOAD_PROPS = [
    'accounts',
    'posts',
    'ranking',
    'summary',
    'comparison',
    'filters',
    'filterOptions',
    'reportUrls',
    'generatedAt',
    'workspace',
    'definitions',
];

function allDisabled(enabled: Record<string, boolean>) {
    return Object.values(enabled).every((value) => !value);
}

function DisabledMetricsNotice({
    title,
    disabledPlatforms,
    children,
}: {
    title: string;
    disabledPlatforms: string[];
    children: React.ReactNode;
}) {
    if (disabledPlatforms.length === 0) {
        return null;
    }

    return (
        <div className="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <div className="flex items-start gap-2">
                <PauseCircle className="mt-0.5 size-4 shrink-0" />
                <div>
                    <p className="font-medium">{title}</p>
                    <p className="mt-0.5">
                        {children}{' '}
                        <span className="font-medium">
                            {disabledPlatforms.join(', ')}
                        </span>
                        .
                    </p>
                </div>
            </div>
        </div>
    );
}

function AnalyticsPollingBanner({
    disabledAccountMetricPlatforms,
    disabledPostMetricPlatforms,
}: {
    disabledAccountMetricPlatforms: string[];
    disabledPostMetricPlatforms: string[];
}) {
    if (
        disabledAccountMetricPlatforms.length === 0 &&
        disabledPostMetricPlatforms.length === 0
    ) {
        return null;
    }

    return (
        <div className="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-5 py-4 text-sm text-amber-900 dark:text-amber-200">
            <div className="flex items-start gap-3">
                <PauseCircle className="mt-0.5 size-5 shrink-0" />
                <div>
                    <p className="font-semibold">
                        Some analytics are temporarily disabled
                    </p>
                    <div className="mt-1 space-y-1">
                        {disabledAccountMetricPlatforms.length > 0 && (
                            <p>
                                Account metrics are paused for{' '}
                                <span className="font-medium">
                                    {disabledAccountMetricPlatforms.join(', ')}
                                </span>
                                .
                            </p>
                        )}
                        {disabledPostMetricPlatforms.length > 0 && (
                            <p>
                                Post metrics are paused for{' '}
                                <span className="font-medium">
                                    {disabledPostMetricPlatforms.join(', ')}
                                </span>
                                .
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

type ComparisonItemProps = {
    row: AnalyticsComparisonRow;
    rank: number;
    isTop: boolean;
    timezone: string;
};

function ComparisonItem({ row, rank, isTop, timezone }: ComparisonItemProps) {
    return (
        <Link
            href={postRoute(row.id).url}
            className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
        >
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                    isTop
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {rank}
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm leading-snug font-medium">
                    {row.title || 'Untitled post'}
                </p>
                <div className="mt-1 flex items-center gap-1.5">
                    {row.platforms.map((p) => (
                        <span key={p} className="text-muted-foreground">
                            <PlatformGlyph platform={p} size={11} />
                        </span>
                    ))}
                    <span className="text-xs text-muted-foreground">
                        {formatAnalyticsDate(row.published_at, timezone)}
                    </span>
                </div>
            </div>
            <div className="shrink-0 text-right">
                <p className="text-sm font-semibold tabular-nums">
                    {row.engagement.toLocaleString()}
                </p>
                <p className="text-xs text-muted-foreground">engagement</p>
                <p className="text-xs text-muted-foreground">
                    {row.measured_targets} of {row.total_targets} destinations
                    measured
                </p>
            </div>
        </Link>
    );
}

function FollowerChartSkeleton({ count }: { count: number }) {
    return (
        <div
            className={
                count > 1
                    ? 'grid grid-cols-1 gap-4 lg:grid-cols-2'
                    : 'grid grid-cols-1 gap-4'
            }
        >
            {Array.from({ length: Math.max(count, 1) }).map((_, i) => (
                <div
                    key={i}
                    className="h-[196px] w-full animate-pulse rounded-[min(var(--radius-4xl),24px)] bg-muted/50"
                />
            ))}
        </div>
    );
}

export default function AnalyticsIndex({
    accounts,
    posts,
    summary,
    comparison,
    polling,
    filterOptions,
    reportUrls,
    filters: analyticsFilters,
}: AnalyticsPageProps) {
    const hasSeries = accounts.some((a) => a.series.length > 0);
    const disabledAccountMetricPlatforms = disabledPlatformLabels(
        polling.account_metrics_enabled,
    );
    const disabledPostMetricPlatforms = disabledPlatformLabels(
        polling.post_metrics_enabled,
    );
    const metricsDisabled =
        allDisabled(polling.account_metrics_enabled) &&
        allDisabled(polling.post_metrics_enabled);
    const rangeLabel =
        RANGE_OPTIONS.find((opt) => opt.days === analyticsFilters.days)
            ?.label ?? `${analyticsFilters.days}d`;

    return (
        <>
            <Head title="Analytics" />

            <div className="mx-auto w-full max-w-6xl space-y-6 px-4 pt-6 pb-16 sm:px-6">
                {/* Page header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl leading-tight font-semibold tracking-tight">
                            Analytics
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Follower counts and accumulated post engagement
                            across your accounts.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={reportUrls.print}
                            className="rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
                        >
                            Print report
                        </Link>
                        <a
                            href={reportUrls.csv}
                            className="rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>

                <div className="flex flex-col gap-4 rounded-2xl border border-border bg-card p-4">
                    <form
                        action={analyticsRoute().url}
                        method="get"
                        className="flex flex-wrap items-end gap-3"
                    >
                        <input
                            type="hidden"
                            name="days"
                            value={analyticsFilters.days}
                        />
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Platform</span>
                            <select
                                name="platform"
                                defaultValue={analyticsFilters.platform ?? ''}
                                onChange={(event) => {
                                    const accountSelect =
                                        event.currentTarget.form?.elements.namedItem(
                                            'account_id',
                                        );
                                    if (
                                        accountSelect instanceof
                                        HTMLSelectElement
                                    ) {
                                        accountSelect.value = '';
                                    }
                                }}
                                className="h-10 min-w-40 rounded-lg border border-input bg-background px-3"
                            >
                                <option value="">All platforms</option>
                                {filterOptions.platforms.map((platform) => (
                                    <option
                                        key={platform.value}
                                        value={platform.value}
                                    >
                                        {platform.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Account</span>
                            <select
                                name="account_id"
                                defaultValue={analyticsFilters.account_id ?? ''}
                                className="h-10 min-w-52 rounded-lg border border-input bg-background px-3"
                            >
                                <option value="">All accounts</option>
                                {filterOptions.accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.display_name ?? account.handle}{' '}
                                        ({account.platform})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <button
                            type="submit"
                            className="h-10 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Apply filters
                        </button>
                        <p className="text-sm text-muted-foreground">
                            {analyticsFilters.from} – {analyticsFilters.to} (
                            {analyticsFilters.timezone})
                        </p>
                    </form>

                    {/* Range selector */}
                    <div className="flex items-center gap-0.5 rounded-lg border border-border bg-muted/40 p-1">
                        {RANGE_OPTIONS.map((opt) => (
                            <Link
                                key={opt.days}
                                href={
                                    analyticsRoute({
                                        query: {
                                            days: opt.days,
                                            platform:
                                                analyticsFilters.platform ??
                                                undefined,
                                            account_id:
                                                analyticsFilters.account_id ??
                                                undefined,
                                        },
                                    }).url
                                }
                                preserveScroll
                                preserveState
                                only={RANGE_RELOAD_PROPS}
                                className={cn(
                                    'rounded-md px-3 py-1 text-sm font-medium transition-all active:scale-[0.97]',
                                    analyticsFilters.days === opt.days
                                        ? 'bg-background text-foreground shadow-sm ring-1 ring-border/60'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {opt.label}
                            </Link>
                        ))}
                    </div>
                </div>

                {metricsDisabled && (
                    <Empty className="rounded-2xl border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PauseCircle />
                            </EmptyMedia>
                            <EmptyTitle>
                                Metrics temporarily disabled
                            </EmptyTitle>
                            <EmptyDescription>
                                Account and post metric polling is paused by
                                your instance admin. Existing data remains
                                visible and new data will resume after polling
                                is enabled again.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}

                {!metricsDisabled && (
                    <>
                        {/* Headline numbers — the at-a-glance answer */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <StatTile
                                label="Followers"
                                metric={summary.followers}
                                caption={`Sum per account; measured for ${summary.followers.coverage.measured} of ${summary.followers.coverage.total} non-Discord accounts`}
                                deltaLabel={`followers over the last ${rangeLabel}`}
                            />
                            <StatTile
                                label="Engagement"
                                metric={summary.engagement}
                                caption={`Latest successful likes, comments & reposts for posts published or partially published in this period; measured for ${summary.engagement.coverage.measured} of ${summary.engagement.coverage.total} destinations`}
                                deltaLabel="vs previous period"
                            />
                            <StatTile
                                label="Posts published"
                                metric={summary.posts}
                                caption="Published or partially published posts in the selected period"
                                deltaLabel="vs previous period"
                            />
                        </div>

                        <AnalyticsPollingBanner
                            disabledAccountMetricPlatforms={
                                disabledAccountMetricPlatforms
                            }
                            disabledPostMetricPlatforms={
                                disabledPostMetricPlatforms
                            }
                        />

                        {/* Follower growth — all accounts on one shared timeline */}
                        {accounts.length > 0 && (
                            <section className="space-y-3">
                                <div>
                                    <h2 className="font-heading text-base font-medium">
                                        Follower growth
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Each account on its own scale over the
                                        last {rangeLabel}
                                    </p>
                                </div>

                                <DisabledMetricsNotice
                                    title="Some account metrics are temporarily disabled"
                                    disabledPlatforms={
                                        disabledAccountMetricPlatforms
                                    }
                                >
                                    Follower snapshots are paused for
                                </DisabledMetricsNotice>

                                {allDisabled(
                                    polling.account_metrics_enabled,
                                ) ? (
                                    <Empty className="rounded-2xl border border-dashed py-10">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <PauseCircle />
                                            </EmptyMedia>
                                            <EmptyTitle className="text-base">
                                                Account metrics temporarily
                                                disabled
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                Follower growth snapshots are
                                                paused by your instance admin.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : !hasSeries ? (
                                    <Empty className="rounded-2xl border border-dashed py-10">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <TrendingUp />
                                            </EmptyMedia>
                                            <EmptyTitle className="text-base">
                                                Collecting your data
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                First numbers appear after the
                                                next sync — follower snapshots
                                                refresh a few times a day.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : (
                                    <Suspense
                                        fallback={
                                            <FollowerChartSkeleton
                                                count={accounts.length}
                                            />
                                        }
                                    >
                                        <FollowerChart
                                            accounts={accounts}
                                            posts={posts}
                                            timezone={analyticsFilters.timezone}
                                            accountMetricsEnabled={
                                                polling.account_metrics_enabled
                                            }
                                        />
                                    </Suspense>
                                )}
                            </section>
                        )}
                    </>
                )}

                {/* Post comparison */}
                {!metricsDisabled &&
                    (allDisabled(polling.post_metrics_enabled) ? (
                        <Empty className="rounded-2xl border border-dashed">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <PauseCircle />
                                </EmptyMedia>
                                <EmptyTitle>
                                    Post metrics temporarily disabled
                                </EmptyTitle>
                                <EmptyDescription>
                                    Post engagement polling is paused by your
                                    instance admin.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        (comparison.top.length > 0 ||
                            comparison.bottom.length > 0) && (
                            <div className="space-y-4">
                                <DisabledMetricsNotice
                                    title="Some post metrics are temporarily disabled"
                                    disabledPlatforms={
                                        disabledPostMetricPlatforms
                                    }
                                >
                                    Post engagement polling is paused for
                                </DisabledMetricsNotice>

                                {comparison.bottom.length === 0 ? (
                                    /* Fewer than 10 eligible posts — single ranked list */
                                    <Card className="gap-0 py-0">
                                        <div className="px-5 py-4">
                                            <CardTitle>
                                                Posts by engagement
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                All posts ranked by engagement
                                                this period
                                            </CardDescription>
                                        </div>
                                        <div className="divide-y divide-border border-t border-border">
                                            {comparison.top.map((row, i) => (
                                                <ComparisonItem
                                                    key={row.id}
                                                    row={row}
                                                    rank={i + 1}
                                                    isTop={true}
                                                    timezone={
                                                        analyticsFilters.timezone
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </Card>
                                ) : (
                                    /* 10+ eligible posts — best vs needs-attention two-column layout */
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {comparison.top.length > 0 && (
                                            <Card className="gap-0 py-0">
                                                <div className="px-5 py-4">
                                                    <CardTitle>
                                                        Best performing
                                                    </CardTitle>
                                                    <CardDescription className="mt-1">
                                                        Highest accumulated
                                                        engagement for posts
                                                        published or partially
                                                        published this period
                                                    </CardDescription>
                                                </div>
                                                <div className="divide-y divide-border border-t border-border">
                                                    {comparison.top.map(
                                                        (row, i) => (
                                                            <ComparisonItem
                                                                key={row.id}
                                                                row={row}
                                                                rank={i + 1}
                                                                isTop={true}
                                                                timezone={
                                                                    analyticsFilters.timezone
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                </div>
                                            </Card>
                                        )}

                                        <Card className="gap-0 py-0">
                                            <div className="px-5 py-4">
                                                <CardTitle>
                                                    Needs attention
                                                </CardTitle>
                                                <CardDescription className="mt-1">
                                                    Lowest accumulated
                                                    engagement for posts
                                                    published or partially
                                                    published this period
                                                </CardDescription>
                                            </div>
                                            <div className="divide-y divide-border border-t border-border">
                                                {comparison.bottom.map(
                                                    (row, i) => (
                                                        <ComparisonItem
                                                            key={row.id}
                                                            row={row}
                                                            rank={i + 1}
                                                            isTop={false}
                                                            timezone={
                                                                analyticsFilters.timezone
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </Card>
                                    </div>
                                )}
                            </div>
                        )
                    ))}

                {/* No accounts at all */}
                {accounts.length === 0 && (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <TrendingUp />
                            </EmptyMedia>
                            <EmptyTitle>No accounts connected</EmptyTitle>
                            <EmptyDescription>
                                Connect an account to start tracking follower
                                growth and post engagement.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Link
                                href={dashboard().url}
                                className="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition-all hover:bg-muted active:scale-[0.98]"
                            >
                                Go to Dashboard
                            </Link>
                        </EmptyContent>
                    </Empty>
                )}
            </div>
        </>
    );
}

AnalyticsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Analytics',
            href: analyticsRoute().url,
        },
    ],
};
