<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasWorkspaceScope;
use App\Enums\AdPlanObjective;
use App\Enums\AdPlanStatus;
use Database\Factories\AdPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable([
    'workspace_id',
    'platform',
    'name',
    'objective',
    'starts_on',
    'ends_on',
    'planned_budget',
    'planned_currency',
    'status',
])]
class AdPlan extends Model
{
    /** @use HasFactory<AdPlanFactory> */
    use HasFactory, HasUuids, HasWorkspaceScope;

    #[Override]
    protected function casts(): array
    {
        return [
            'objective' => AdPlanObjective::class,
            'status' => AdPlanStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'planned_budget' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
