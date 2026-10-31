<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasWorkspaceScope;
use App\Enums\LeadStatus;
use Database\Factories\CrmLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable([
    'workspace_id',
    'sales_campaign_id',
    'name',
    'email',
    'status',
])]
class CrmLead extends Model
{
    /** @use HasFactory<CrmLeadFactory> */
    use HasFactory, HasUuids, HasWorkspaceScope;

    #[Override]
    protected function casts(): array
    {
        return ['status' => LeadStatus::class];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<SalesCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SalesCampaign::class, 'sales_campaign_id');
    }
}
