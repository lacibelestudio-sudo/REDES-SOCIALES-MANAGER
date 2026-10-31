<?php

declare(strict_types=1);

namespace App\Http\Requests\Commercial;

use App\Enums\SalesCampaignStatus;
use App\Models\SalesCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = SalesCampaign::query()
            ->where('workspace_id', $this->user()?->current_workspace_id)
            ->find($this->route('campaign'));

        return $campaign !== null
            && ($this->user()?->can('update', $campaign) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(SalesCampaignStatus::class)],
        ];
    }
}
