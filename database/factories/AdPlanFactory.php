<?php

namespace Database\Factories;

use App\Enums\AdPlanObjective;
use App\Enums\AdPlanStatus;
use App\Models\AdPlan;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AdPlan>
 */
class AdPlanFactory extends Factory
{
    #[Override]
    protected $model = AdPlan::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'platform' => 'meta',
            'name' => fake()->words(3, true),
            'objective' => AdPlanObjective::Awareness,
            'planned_budget' => null,
            'planned_currency' => null,
            'status' => AdPlanStatus::Draft,
        ];
    }
}
