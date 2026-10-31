import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';

import AdPlanController from '@/actions/App/Http/Controllers/AdPlanController';
import Heading from '@/components/common/heading';

type AdPlan = {
    id: string;
    platform: 'meta';
    name: string;
    objective: string;
    starts_on: string | null;
    ends_on: string | null;
    planned_budget: string | null;
    planned_currency: string | null;
    status: string;
};

type Props = {
    plans: AdPlan[];
    canManage: boolean;
};

const OBJECTIVES = [
    ['awareness', 'Awareness'],
    ['traffic', 'Traffic'],
    ['engagement', 'Engagement'],
    ['leads', 'Leads'],
    ['sales', 'Sales'],
    ['app_promotion', 'App promotion'],
] as const;

function ConfirmDelete({ onDelete }: { onDelete: () => void }) {
    const [confirming, setConfirming] = useState(false);

    return confirming ? (
        <span className="flex items-center gap-2">
            <span className="text-sm">Delete this draft?</span>
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
            Delete draft
        </button>
    );
}

export default function AdsIndex({ plans, canManage }: Props) {
    return (
        <>
            <Head title="Ad plan drafts" />
            <div className="space-y-6">
                <Heading
                    title="Ad plan drafts"
                    description="Meta Ads planning only. These budgets are estimates, not spend; nothing here connects to or activates an ad account."
                />

                {canManage && (
                    <Form
                        {...AdPlanController.store.form()}
                        className="grid gap-3 rounded-xl border border-border p-5 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <label className="grid gap-1 text-sm">
                                    Plan name
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
                                <label className="grid gap-1 text-sm">
                                    Objective
                                    <select
                                        name="objective"
                                        defaultValue="awareness"
                                        className="rounded-md border border-input bg-background px-3 py-2"
                                    >
                                        {OBJECTIVES.map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Planned budget
                                    <input
                                        name="planned_budget"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className="rounded-md border border-input bg-background px-3 py-2"
                                    />
                                    {errors.planned_budget && (
                                        <span className="text-destructive">
                                            {errors.planned_budget}
                                        </span>
                                    )}
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Currency (ISO 4217)
                                    <input
                                        name="planned_currency"
                                        maxLength={3}
                                        pattern="[A-Z]{3}"
                                        className="rounded-md border border-input bg-background px-3 py-2 uppercase"
                                    />
                                    {errors.planned_currency && (
                                        <span className="text-destructive">
                                            {errors.planned_currency}
                                        </span>
                                    )}
                                </label>
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
                                    Save draft
                                </button>
                            </>
                        )}
                    </Form>
                )}

                {plans.length === 0 ? (
                    <p className="rounded-xl border border-border p-5 text-sm text-muted-foreground">
                        No ad plan drafts yet.
                    </p>
                ) : (
                    <div className="grid gap-4 xl:grid-cols-2">
                        {plans.map((plan) => (
                            <div
                                key={plan.id}
                                className="space-y-3 rounded-xl border border-border p-5"
                            >
                                <Form
                                    {...AdPlanController.update.form(plan.id)}
                                    className="grid gap-3 md:grid-cols-2"
                                >
                                    {({ errors, processing }) => (
                                        <fieldset
                                            disabled={!canManage}
                                            className="contents"
                                        >
                                            <label className="grid gap-1 text-sm">
                                                Plan name
                                                <input
                                                    name="name"
                                                    required
                                                    maxLength={120}
                                                    defaultValue={plan.name}
                                                    className="rounded-md border border-input bg-background px-3 py-2"
                                                />
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                Objective
                                                <select
                                                    name="objective"
                                                    defaultValue={
                                                        plan.objective
                                                    }
                                                    className="rounded-md border border-input bg-background px-3 py-2"
                                                >
                                                    {OBJECTIVES.map(
                                                        ([value, label]) => (
                                                            <option
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                Workflow status
                                                <select
                                                    name="status"
                                                    defaultValue={plan.status}
                                                    className="rounded-md border border-input bg-background px-3 py-2"
                                                >
                                                    <option value="draft">
                                                        Draft
                                                    </option>
                                                    <option value="ready_for_review">
                                                        Ready for internal
                                                        review
                                                    </option>
                                                </select>
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                Starts on
                                                <input
                                                    name="starts_on"
                                                    type="date"
                                                    defaultValue={
                                                        plan.starts_on ?? ''
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
                                                        plan.ends_on ?? ''
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
                                                Planned budget
                                                <input
                                                    name="planned_budget"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue={
                                                        plan.planned_budget ??
                                                        ''
                                                    }
                                                    className="rounded-md border border-input bg-background px-3 py-2"
                                                />
                                                {errors.planned_budget && (
                                                    <span className="text-destructive">
                                                        {errors.planned_budget}
                                                    </span>
                                                )}
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                Currency (ISO 4217)
                                                <input
                                                    name="planned_currency"
                                                    maxLength={3}
                                                    pattern="[A-Z]{3}"
                                                    defaultValue={
                                                        plan.planned_currency ??
                                                        ''
                                                    }
                                                    className="rounded-md border border-input bg-background px-3 py-2 uppercase"
                                                />
                                                {errors.planned_currency && (
                                                    <span className="text-destructive">
                                                        {
                                                            errors.planned_currency
                                                        }
                                                    </span>
                                                )}
                                            </label>
                                            <p className="text-sm text-muted-foreground">
                                                Meta Ads · Planned budget:{' '}
                                                {plan.planned_budget === null
                                                    ? 'not set'
                                                    : `${plan.planned_budget} ${plan.planned_currency}`}
                                                <br />
                                                This is not actual spend.
                                            </p>
                                            <div className="flex flex-wrap items-center gap-3">
                                                {canManage && (
                                                    <button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="rounded-md border border-border px-3 py-1.5 text-sm"
                                                    >
                                                        Save changes
                                                    </button>
                                                )}
                                                {errors.status && (
                                                    <span className="text-sm text-destructive">
                                                        {errors.status}
                                                    </span>
                                                )}
                                            </div>
                                        </fieldset>
                                    )}
                                </Form>
                                {canManage && (
                                    <ConfirmDelete
                                        onDelete={() =>
                                            router.delete(
                                                AdPlanController.destroy(
                                                    plan.id,
                                                ).url,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
