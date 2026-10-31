<?php

declare(strict_types=1);

namespace App\Http\Requests\Ads;

use App\Enums\AdPlanObjective;
use App\Enums\AdPlanStatus;
use App\Models\AdPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = AdPlan::query()
            ->where('workspace_id', $this->user()?->current_workspace_id)
            ->find($this->route('plan'));

        return $plan !== null && ($this->user()?->can('update', $plan) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'objective' => ['required', Rule::enum(AdPlanObjective::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'planned_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_with:planned_currency'],
            'planned_currency' => ['nullable', 'required_with:planned_budget', 'regex:/^[A-Z]{3}$/'],
            'status' => ['required', Rule::enum(AdPlanStatus::class)],
        ];
    }
}
