import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';

import SalesController from '@/actions/App/Http/Controllers/SalesController';
import Heading from '@/components/common/heading';

type Campaign = {
    id: string;
    name: string;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
    starts_on: string | null;
    ends_on: string | null;
    status: string;
    leads_count: number;
};

type Lead = {
    id: string;
    name: string | null;
    email: string | null;
    status: string;
    campaign_id: string | null;
    campaign_name: string | null;
};

type Props = {
    campaigns: Campaign[];
    leads: Lead[];
    leadSummary: Record<(typeof LEAD_STATUSES)[number]['value'], number>;
    canManage: boolean;
};

const LEAD_STATUSES = [
    { value: 'new', label: 'New' },
    { value: 'qualified', label: 'Qualified' },
    { value: 'opportunity', label: 'Opportunity' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
];

function ConfirmDelete({
    label,
    onDelete,
}: {
    label: string;
    onDelete: () => void;
}) {
    const [confirming, setConfirming] = useState(false);

    return confirming ? (
        <span className="flex flex-wrap items-center gap-2">
            <span className="text-sm">Permanently delete?</span>
            <button
                type="button"
                onClick={onDelete}
                className="text-sm font-medium text-destructive underline"
            >
                Confirm
            </button>
            <button
                type="button"
                onClick={() => setConfirming(false)}
                className="text-sm underline"
            >
                Cancel
            </button>
        </span>
    ) : (
        <button
            type="button"
            onClick={() => setConfirming(true)}
            className="text-sm text-destructive underline"
        >
            {label}
        </button>
    );
}

export default function SalesIndex({
    campaigns,
    leads,
    leadSummary,
    canManage,
}: Props) {
    return (
        <>
            <Head title="Campaigns and leads" />
            <div className="space-y-8">
                <Heading
                    title="Campaigns and leads"
                    description="Internal workspace records only. No outbound messaging, tracking, or revenue attribution."
                />

                <section className="space-y-4 rounded-xl border border-border p-5">
                    <h2 className="text-lg font-semibold">UTM campaigns</h2>
                    {canManage && (
                        <Form
                            {...SalesController.storeCampaign.form()}
                            className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                        >
                            {({ errors, processing }) => (
                                <fieldset
                                    disabled={!canManage}
                                    className="contents"
                                >
                                    <label className="grid gap-1 text-sm">
                                        Campaign name
                                        <input
                                            name="name"
                                            required
                                            maxLength={120}
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        />
                                        {errors.name && (
                                            <span className="text-destructive">
                                                {errors.name}
                                            </span>
                                        )}
                                    </label>
                                    {(
                                        [
                                            'utm_source',
                                            'utm_medium',
                                            'utm_campaign',
                                            'utm_term',
                                            'utm_content',
                                        ] as const
                                    ).map((field) => (
                                        <label
                                            key={field}
                                            className="grid gap-1 text-sm"
                                        >
                                            {field}
                                            <input
                                                name={field}
                                                maxLength={255}
                                                className="rounded-md border border-input bg-background px-3 py-2"
                                            />
                                        </label>
                                    ))}
                                    <label className="grid gap-1 text-sm">
                                        Starts on
                                        <input
                                            name="starts_on"
                                            type="date"
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        Ends on
                                        <input
                                            name="ends_on"
                                            type="date"
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        />
                                        {errors.ends_on && (
                                            <span className="text-destructive">
                                                {errors.ends_on}
                                            </span>
                                        )}
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-fit rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                    >
                                        Create campaign
                                    </button>
                                </fieldset>
                            )}
                        </Form>
                    )}
                    {campaigns.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No campaigns yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-border">
                            {campaigns.map((campaign) => (
                                <article
                                    key={campaign.id}
                                    className="space-y-3 border-b border-border py-4 last:border-0"
                                >
                                    <Form
                                        {...SalesController.updateCampaign.form(
                                            campaign.id,
                                        )}
                                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                                    >
                                        {({ errors, processing }) => (
                                            <fieldset
                                                disabled={!canManage}
                                                className="contents"
                                            >
                                                <label className="grid gap-1 text-sm">
                                                    Campaign name
                                                    <input
                                                        name="name"
                                                        required
                                                        defaultValue={
                                                            campaign.name
                                                        }
                                                        className="rounded-md border border-input bg-background px-3 py-2"
                                                    />
                                                    {errors.name && (
                                                        <span className="text-destructive">
                                                            {errors.name}
                                                        </span>
                                                    )}
                                                </label>
                                                {(
                                                    [
                                                        'utm_source',
                                                        'utm_medium',
                                                        'utm_campaign',
                                                        'utm_term',
                                                        'utm_content',
                                                    ] as const
                                                ).map((field) => (
                                                    <label
                                                        key={field}
                                                        className="grid gap-1 text-sm"
                                                    >
                                                        {field}
                                                        <input
                                                            name={field}
                                                            defaultValue={
                                                                campaign[
                                                                    field
                                                                ] ?? ''
                                                            }
                                                            maxLength={255}
                                                            className="rounded-md border border-input bg-background px-3 py-2"
                                                        />
                                                    </label>
                                                ))}
                                                <label className="grid gap-1 text-sm">
                                                    Starts on
                                                    <input
                                                        name="starts_on"
                                                        type="date"
                                                        defaultValue={
                                                            campaign.starts_on ??
                                                            ''
                                                        }
                                                        className="rounded-md border border-input bg-background px-3 py-2"
                                                    />
                                                </label>
                                                <label className="grid gap-1 text-sm">
                                                    Ends on
                                                    <input
                                                        name="ends_on"
                                                        type="date"
                                                        defaultValue={
                                                            campaign.ends_on ??
                                                            ''
                                                        }
                                                        className="rounded-md border border-input bg-background px-3 py-2"
                                                    />
                                                    {errors.ends_on && (
                                                        <span className="text-destructive">
                                                            {errors.ends_on}
                                                        </span>
                                                    )}
                                                </label>
                                                <label className="grid gap-1 text-sm">
                                                    Status
                                                    <select
                                                        name="status"
                                                        defaultValue={
                                                            campaign.status
                                                        }
                                                        className="rounded-md border border-input bg-background px-3 py-2"
                                                    >
                                                        <option value="draft">
                                                            Draft
                                                        </option>
                                                        <option value="ready">
                                                            Ready
                                                        </option>
                                                        <option value="archived">
                                                            Archived
                                                        </option>
                                                    </select>
                                                </label>
                                                <div className="flex items-center gap-3">
                                                    <button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="w-fit rounded-md border border-border px-3 py-1.5 text-sm disabled:opacity-50"
                                                    >
                                                        Save changes
                                                    </button>
                                                    <span className="text-xs text-muted-foreground">
                                                        {campaign.leads_count}{' '}
                                                        leads
                                                    </span>
                                                </div>
                                            </fieldset>
                                        )}
                                    </Form>
                                    {canManage && (
                                        <ConfirmDelete
                                            label="Delete campaign"
                                            onDelete={() =>
                                                router.delete(
                                                    SalesController.destroyCampaign(
                                                        campaign.id,
                                                    ).url,
                                                )
                                            }
                                        />
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-xl border border-border p-5">
                    <h2 className="text-lg font-semibold">Leads</h2>
                    <p className="text-sm text-muted-foreground">
                        Store only a name and/or email. A lead stage is not a
                        confirmed sale or revenue.
                    </p>
                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Lead records by stage
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Counts cover all records in this workspace; they are
                            not deduplicated people or attributed conversions.
                        </p>
                        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            {LEAD_STATUSES.map((status) => (
                                <div
                                    key={status.value}
                                    className="rounded-lg border border-border p-3"
                                >
                                    <dt className="text-xs text-muted-foreground">
                                        {status.label}
                                    </dt>
                                    <dd className="text-lg font-semibold">
                                        {leadSummary[status.value]}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                    {canManage && (
                        <Form
                            {...SalesController.storeLead.form()}
                            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <label className="grid gap-1 text-sm">
                                        Name
                                        <input
                                            name="name"
                                            maxLength={120}
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        />
                                        {errors.name && (
                                            <span className="text-destructive">
                                                {errors.name}
                                            </span>
                                        )}
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        Email
                                        <input
                                            name="email"
                                            type="email"
                                            maxLength={255}
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        />
                                        {errors.email && (
                                            <span className="text-destructive">
                                                {errors.email}
                                            </span>
                                        )}
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        Campaign
                                        <select
                                            name="sales_campaign_id"
                                            className="rounded-md border border-input bg-background px-3 py-2"
                                        >
                                            <option value="">None</option>
                                            {campaigns.map((campaign) => (
                                                <option
                                                    key={campaign.id}
                                                    value={campaign.id}
                                                >
                                                    {campaign.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-fit self-end rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                    >
                                        Add lead
                                    </button>
                                </>
                            )}
                        </Form>
                    )}
                    {leads.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No leads yet.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {leads.map((lead) => (
                                <article
                                    key={lead.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {lead.name || lead.email}
                                        </p>
                                        {lead.name && lead.email && (
                                            <p className="text-sm text-muted-foreground">
                                                {lead.email}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {lead.campaign_name ??
                                                'No campaign'}
                                        </p>
                                    </div>
                                    {canManage && (
                                        <div className="flex items-center gap-3">
                                            <Form
                                                {...SalesController.updateLead.form(
                                                    lead.id,
                                                )}
                                                className="flex items-center gap-2"
                                            >
                                                <input
                                                    type="text"
                                                    name="name"
                                                    defaultValue={
                                                        lead.name ?? ''
                                                    }
                                                    aria-label={`Name for ${lead.name || lead.email}`}
                                                    className="w-36 rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                                />
                                                <input
                                                    type="email"
                                                    name="email"
                                                    defaultValue={
                                                        lead.email ?? ''
                                                    }
                                                    aria-label={`Email for ${lead.name || lead.email}`}
                                                    className="w-48 rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                                />
                                                <input
                                                    type="hidden"
                                                    name="sales_campaign_id"
                                                    value={
                                                        lead.campaign_id ?? ''
                                                    }
                                                />
                                                <select
                                                    name="status"
                                                    defaultValue={lead.status}
                                                    aria-label={`Status for ${lead.name || lead.email}`}
                                                    className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                                >
                                                    {LEAD_STATUSES.map(
                                                        (status) => (
                                                            <option
                                                                key={
                                                                    status.value
                                                                }
                                                                value={
                                                                    status.value
                                                                }
                                                            >
                                                                {status.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <button
                                                    type="submit"
                                                    className="text-sm underline"
                                                >
                                                    Save
                                                </button>
                                            </Form>
                                            <ConfirmDelete
                                                label="Delete"
                                                onDelete={() =>
                                                    router.delete(
                                                        SalesController.destroyLead(
                                                            lead.id,
                                                        ).url,
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                    {!canManage && (
                                        <span className="text-sm text-muted-foreground capitalize">
                                            {lead.status}
                                        </span>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
