<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Platform;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Post::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $platforms = collect(Platform::cases())
            ->reject(fn (Platform $platform): bool => $platform === Platform::Discord)
            ->map(fn (Platform $platform): string => $platform->value)
            ->all();

        return [
            'days' => ['sometimes', 'integer'],
            'platform' => ['nullable', 'string', Rule::in($platforms)],
            'account_id' => ['nullable', 'string'],
        ];
    }
}
