<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdPlan;
use App\Models\User;
use Illuminate\Support\Facades\Context;

class AdPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.read'], Context::get('workspace_id'));
    }

    public function create(User $user): bool
    {
        return $user->hasAllPermissions(['workspace.update'], Context::get('workspace_id'));
    }

    public function update(User $user, AdPlan $plan): bool
    {
        return $this->create($user) && $plan->workspace_id === Context::get('workspace_id');
    }

    public function delete(User $user, AdPlan $plan): bool
    {
        return $this->update($user, $plan);
    }
}
