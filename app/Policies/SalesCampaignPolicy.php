<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Context;

class SalesCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.read'], Context::get('workspace_id'));
    }

    public function create(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.update'], Context::get('workspace_id'));
    }

    public function update(User $user, SalesCampaign $campaign): bool
    {
        return $this->create($user) && $campaign->workspace_id === Context::get('workspace_id');
    }

    public function delete(User $user, SalesCampaign $campaign): bool
    {
        return $this->create($user) && $campaign->workspace_id === Context::get('workspace_id');
    }
}
