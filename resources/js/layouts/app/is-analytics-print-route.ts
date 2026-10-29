export function isAnalyticsPrintRoute(pageUrl: string): boolean {
    return new URL(pageUrl, 'http://localhost').pathname === '/analytics/print';
}
