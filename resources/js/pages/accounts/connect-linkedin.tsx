import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import ConnectedAccountController from '@/actions/App/Http/Controllers/ConnectedAccounts/ConnectedAccountController';
import LinkedInPageConnectionController from '@/actions/App/Http/Controllers/ConnectedAccounts/LinkedInPageConnectionController';
import Heading from '@/components/common/heading';
import { PlatformGlyph } from '@/components/common/platform-glyph';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type LinkedInPerson = {
    remoteAccountId: string;
    handle: string;
    displayName: string | null;
    avatarUrl: string | null;
};

type LinkedInOrganization = {
    id: string;
    urn: string;
    name: string;
    vanityName: string;
};

type Selection = { type: 'person' } | { type: 'organization'; id: string };

type DiscoveryStatus =
    | 'succeeded'
    | 'permission_denied'
    | 'authentication_failed'
    | 'rate_limited'
    | 'connection_failed'
    | 'provider_failed';

type Props = {
    person: LinkedInPerson;
    organizations: LinkedInOrganization[];
    organizationDiscoveryStatus: DiscoveryStatus;
    organizationDiscoveryMessage: string | null;
};

export default function ConnectLinkedIn({
    person,
    organizations,
    organizationDiscoveryStatus,
    organizationDiscoveryMessage,
}: Props) {
    const [personChecked, setPersonChecked] = useState(true);
    const [checkedOrgs, setCheckedOrgs] = useState<Record<string, boolean>>({});
    const [processing, setProcessing] = useState(false);

    const selection: Selection[] = [
        ...(personChecked ? [{ type: 'person' as const }] : []),
        ...organizations
            .filter((org) => checkedOrgs[org.id])
            .map((org) => ({ type: 'organization' as const, id: org.id })),
    ];

    const submit = () => {
        router.post(
            LinkedInPageConnectionController.store.url(),
            { selected: selection },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 pt-6 pb-16 sm:px-6">
            <Head title="Connect LinkedIn" />
            <Heading
                title="Choose what to connect"
                description="Connect your personal LinkedIn profile and any Pages you administer to this workspace."
            />
            {organizationDiscoveryMessage !== null ? (
                <p
                    role="status"
                    className="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200"
                >
                    {organizationDiscoveryMessage}
                </p>
            ) : organizationDiscoveryStatus === 'succeeded' &&
              organizations.length === 0 ? (
                <p
                    role="status"
                    className="rounded-xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
                >
                    LinkedIn completed the organization lookup and returned no
                    Pages for this profile. You can still connect the personal
                    profile.
                </p>
            ) : null}
            <div className="flex flex-col gap-4">
                <label className="flex items-center gap-3 rounded-xl border p-4">
                    <Checkbox
                        checked={personChecked}
                        onCheckedChange={(c) => setPersonChecked(c === true)}
                    />
                    <PlatformGlyph
                        platform="linkedin"
                        size={16}
                        className="size-4"
                    />
                    <span className="font-medium">
                        {person.displayName ?? person.handle}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        Personal profile
                    </span>
                </label>

                {organizations.map((org) => (
                    <label
                        key={org.id}
                        className="flex items-center gap-3 rounded-xl border p-4"
                    >
                        <Checkbox
                            checked={!!checkedOrgs[org.id]}
                            onCheckedChange={(c) =>
                                setCheckedOrgs((prev) => ({
                                    ...prev,
                                    [org.id]: c === true,
                                }))
                            }
                        />
                        <PlatformGlyph
                            platform="linkedin"
                            size={16}
                            className="size-4"
                        />
                        <span className="font-medium">{org.name}</span>
                        <span className="text-sm text-muted-foreground">
                            Page
                        </span>
                    </label>
                ))}

                <Button
                    type="button"
                    onClick={submit}
                    disabled={selection.length === 0 || processing}
                    className="w-full sm:w-auto"
                >
                    Connect selected
                </Button>
            </div>
        </div>
    );
}

ConnectLinkedIn.layout = {
    breadcrumbs: [
        { title: 'Accounts', href: ConnectedAccountController.index().url },
    ],
};
