<?php

declare(strict_types=1);

namespace App\Http\Requests\Commercial;

use App\Enums\LeadStatus;
use App\Models\CrmLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CrmLead::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:name'],
            'sales_campaign_id' => [
                'nullable',
                'uuid',
                Rule::exists('sales_campaigns', 'id')
                    ->where('workspace_id', $this->user()?->current_workspace_id),
            ],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
        ];
    }
}
