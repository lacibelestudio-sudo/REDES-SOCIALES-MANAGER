import type * as InertiaReact from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import AdsIndex from '@/pages/ads/index';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return { ...actual, Head: () => null };
});

describe('AdsIndex', () => {
    it('labels planned budgets and excludes campaign activation actions', () => {
        render(<AdsIndex plans={[]} canManage />);

        expect(screen.getByText(/planning only/i)).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /launch|publish|activate/i }),
        ).not.toBeInTheDocument();
    });
});
