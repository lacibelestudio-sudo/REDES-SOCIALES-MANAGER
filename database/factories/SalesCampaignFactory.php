<?php

namespace Database\Factories;

use App\Enums\SalesCampaignStatus;
use App\Models\SalesCampaign;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<SalesCampaign>
 */
class SalesCampaignFactory extends Factory
{
    #[Override]
    protected $model = SalesCampaign::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
            'utm_source' => fake()->word(),
            'utm_medium' => fake()->word(),
            'utm_campaign' => fake()->slug(3),
            'status' => SalesCampaignStatus::Draft,
        ];
    }
}
