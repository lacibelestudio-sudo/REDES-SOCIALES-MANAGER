<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Support\Facades\Context;

class CrmLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.read'], Context::get('workspace_id'));
    }

    public function create(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.update'], Context::get('workspace_id'));
    }

    public function update(User $user, CrmLead $lead): bool
    {
        return $this->create($user) && $lead->workspace_id === Context::get('workspace_id');
    }

    public function delete(User $user, CrmLead $lead): bool
    {
        return $this->update($user, $lead);
    }
}
