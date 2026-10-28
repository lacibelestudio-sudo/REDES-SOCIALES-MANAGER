import type { PlatformName } from '@/types/compose';

export type MetricsStatus = 'ok' | 'unsupported' | 'rate_limited' | 'failed';

export type PostStatTarget = {
    id: string;
    platform: PlatformName;
    handle: string | null;
    display_name: string | null;
    avatar_url: string | null;
    status: MetricsStatus | null;
    likes: number;
    comments: number;
    reposts: number;
    impressions: number | null;
    captured_at: string | null;
    series: {
        at: string;
        likes: number;
        comments: number;
        reposts: number;
        impressions: number | null;
    }[];
};

export type PostStatsPayload = {
    supported: boolean;
    captured_at: string | null;
    totals: { likes: number; comments: number; reposts: number };
    targets: PostStatTarget[];
};

export type AnalyticsAccount = {
    id: string;
    platform: PlatformName;
    handle: string;
    display_name: string | null;
    avatar_url: string | null;
    status: MetricsStatus | null;
    latest_followers: number | null;
    /** Change in followers across the selected window (null until 2+ readings). */
    followers_delta: number | null;
    series: { at: string; followers: number; following: number | null }[];
};

export type AnalyticsPostMarker = {
    id: string;
    title: string;
    published_at: string;
    platforms: PlatformName[];
    engagement: number | null;
    measured_targets: number;
    total_targets: number;
};

export type AnalyticsComparisonRow = AnalyticsPostMarker & {
    engagement: number;
    measured_targets: number;
    total_targets: number;
};

/** A single number with an optional change-over-the-period delta. */
export type AnalyticsSummaryMetric = {
    value: number | null;
    /** Change vs the previous equal-length window; null when there's no baseline. */
    delta: number | null;
};

export type AnalyticsMetricCoverage = {
    measured: number;
    total: number;
};

export type AnalyticsSummary = {
    followers: AnalyticsSummaryMetric & { coverage: AnalyticsMetricCoverage };
    engagement: AnalyticsSummaryMetric & { coverage: AnalyticsMetricCoverage };
    posts: { value: number; delta: number | null };
    /** How many accounts contribute to the follower total. */
    account_count: number;
};

export type AnalyticsPageProps = {
    accounts: AnalyticsAccount[];
    posts: AnalyticsPostMarker[];
    ranking: AnalyticsComparisonRow[];
    summary: AnalyticsSummary;
    comparison: {
        top: AnalyticsComparisonRow[];
        bottom: AnalyticsComparisonRow[];
    };
    filters: {
        days: number;
        platform: PlatformName | null;
        account_id: string | null;
        from: string;
        to: string;
        from_at: string;
        to_at: string;
        timezone: string;
    };
    workspace: { name: string };
    generatedAt: string;
    reportUrls: { print: string; csv: string };
    definitions: string[];
    filterOptions: {
        platforms: { value: PlatformName; label: string }[];
        accounts: {
            id: string;
            platform: PlatformName;
            handle: string;
            display_name: string | null;
        }[];
    };
    polling: {
        post_metrics_enabled: Record<PlatformName, boolean>;
        account_metrics_enabled: Record<PlatformName, boolean>;
    };
};
