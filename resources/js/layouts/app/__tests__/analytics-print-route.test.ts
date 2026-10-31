import { readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import { isAnalyticsPrintRoute } from '../is-analytics-print-route';

const layoutSource = readFileSync(
    new URL('../app-sidebar-layout.tsx', import.meta.url),
    'utf8',
);

describe('analytics print route detection', () => {
    it('matches the report route when its filters are in the URL', () => {
        expect(
            isAnalyticsPrintRoute(
                '/analytics/print?days=14&platform=facebook&account_id=12',
            ),
        ).toBe(true);
    });

    it('does not hide app navigation on the regular analytics dashboard', () => {
        expect(isAnalyticsPrintRoute('/analytics?days=14')).toBe(false);
    });

    it('applies print-only hiding to the report shell and keeps normal shell classes', () => {
        expect(layoutSource).toContain('isAnalyticsPrintRoute(usePage().url)');
        expect(layoutSource).toContain(
            "wrapperClassName={isPrintReport ? 'print:hidden' : undefined}",
        );
        expect(layoutSource).toContain(
            "className={isPrintReport ? 'print:hidden' : undefined}",
        );
        expect(layoutSource).toContain("'overflow-x-hidden'");
    });
});
