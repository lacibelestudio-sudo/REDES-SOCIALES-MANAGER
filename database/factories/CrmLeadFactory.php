<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\CrmLead;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CrmLead>
 */
class CrmLeadFactory extends Factory
{
    #[Override]
    protected $model = CrmLead::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'status' => LeadStatus::New,
        ];
    }
}
