import type * as InertiaReact from '@inertiajs/react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import SalesIndex from '@/pages/sales/index';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return { ...actual, Head: () => null };
});

const campaign = {
    id: 'campaign-1',
    name: 'Synthetic campaign',
    utm_source: 'newsletter',
    utm_medium: 'email',
    utm_campaign: 'fall-launch',
    utm_term: null,
    utm_content: null,
    starts_on: null,
    ends_on: null,
    status: 'ready',
    leads_count: 1,
};

const lead = {
    id: 'lead-1',
    name: 'Synthetic contact',
    email: 'synthetic@example.test',
    status: 'qualified',
    campaign_id: campaign.id,
    campaign_name: campaign.name,
};

const leadSummary = {
    new: 0,
    qualified: 1,
    opportunity: 0,
    won: 0,
    lost: 0,
};

describe('SalesIndex', () => {
    it('shows UTM values and commercial stages as internal records', () => {
        render(
            <SalesIndex
                campaigns={[campaign]}
                leads={[lead]}
                leadSummary={leadSummary}
                canManage
            />,
        );

        expect(screen.getByText('Campaigns and leads')).toBeInTheDocument();
        expect(screen.getByText('Lead records by stage')).toBeInTheDocument();
        expect(
            screen.getByText(/not deduplicated people/i),
        ).toBeInTheDocument();
        expect(screen.getByDisplayValue('newsletter')).toBeInTheDocument();
        expect(
            screen.getByDisplayValue('synthetic@example.test'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Opportunity' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/not a confirmed sale or revenue/i),
        ).toBeInTheDocument();
    });

    it('keeps member controls read-only', () => {
        render(
            <SalesIndex
                campaigns={[campaign]}
                leads={[lead]}
                leadSummary={leadSummary}
                canManage={false}
            />,
        );

        expect(
            screen.getByRole('textbox', { name: 'Campaign name' }),
        ).toBeDisabled();
        expect(
            screen.queryByRole('button', { name: 'Add lead' }),
        ).not.toBeInTheDocument();
    });

    it('requires confirmation before deleting a campaign', () => {
        render(
            <SalesIndex
                campaigns={[campaign]}
                leads={[]}
                leadSummary={leadSummary}
                canManage
            />,
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Delete campaign' }),
        );
        expect(
            screen.getByRole('button', { name: 'Confirm' }),
        ).toBeInTheDocument();
    });
});
