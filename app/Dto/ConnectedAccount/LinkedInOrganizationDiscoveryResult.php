<?php

declare(strict_types=1);

namespace App\Dto\ConnectedAccount;

use App\Enums\LinkedInOrganizationDiscoveryStatus;

final readonly class LinkedInOrganizationDiscoveryResult
{
    /**
     * @param  list<LinkedInOrganization>  $organizations
     */
    public function __construct(
        public LinkedInOrganizationDiscoveryStatus $status,
        public array $organizations = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->status === LinkedInOrganizationDiscoveryStatus::Succeeded;
    }
}
