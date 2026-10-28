<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use App\Enums\Platform;
use App\Enums\PostFormat;
use App\Http\Requests\Post\Concerns\DerivesMentionHandleRules;
use App\Models\ConnectedAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    use DerivesMentionHandleRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_text' => ['sometimes', 'nullable', 'string'],
            'segments' => ['present', 'array'],
            'segments.*' => ['nullable', 'string'],
            'mentions' => ['array'],
            'mentions.*.id' => ['required', 'string'],
            'mentions.*.label' => ['required', 'string'],
            'mentions.*.handles' => ['array'],
            ...$this->mentionHandleRules(),
            'destination' => ['required', 'array'],
            'destination.kind' => ['required', Rule::in(['all', 'none', 'set', 'account', 'accounts'])],
            'destination.id' => ['nullable', 'string', 'required_if:destination.kind,set,account'],
            'destination.ids' => ['array', 'required_if:destination.kind,accounts'],
            'destination.ids.*' => ['string'],
            'targets' => ['array'],
            'targets.*.connected_account_id' => ['required', 'string'],
            'targets.*.auto_split' => ['boolean'],
            'targets.*.format' => ['nullable', Rule::enum(PostFormat::class)],
            'targets.*.content_override' => ['nullable', 'array'],
            'targets.*.content_override.text' => ['nullable', 'string'],
            'targets.*.content_override.segments' => ['array'],
            'targets.*.content_override.segments.*' => ['nullable', 'string'],
            'targets.*.content_override.media_ids' => ['array'],
            'targets.*.content_override.media_ids.*' => ['string'],
            'targets.*.segment_breaks' => ['nullable', 'array'],
            'targets.*.segment_breaks.*' => ['string'],
            'targets.*.placements' => ['nullable', 'array'],
            'targets.*.placements.*.media_id' => ['required', 'string'],
            'targets.*.placements.*.segment_ref' => ['required', 'string'],
            'targets.*.placements.*.position' => ['required', 'integer'],
            'media_ids' => ['array'],
            'media_ids.*' => ['string'],
            'segment_breaks' => ['array'],
            'segment_breaks.*' => ['string'],
            'placements' => ['array'],
            'placements.*.media_id' => ['required', 'string'],
            'placements.*.segment_ref' => ['required', 'string'],
            'placements.*.position' => ['required', 'integer'],
            'auto_repost' => ['sometimes', 'nullable', 'boolean'],
            'skip_sync' => ['sometimes', 'boolean'],
            'expected_updated_at' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $targets = $this->input('targets', []);

                if (! is_array($targets)) {
                    return;
                }

                $storyAccountIds = collect($targets)
                    ->filter(fn (mixed $target): bool => is_array($target)
                        && ($target['format'] ?? null) === PostFormat::Story->value
                        && is_string($target['connected_account_id'] ?? null))
                    ->pluck('connected_account_id')
                    ->all();

                if ($storyAccountIds === []) {
                    return;
                }

                $accounts = ConnectedAccount::query()
                    ->where('workspace_id', $this->user()->current_workspace_id)
                    ->whereIn('id', $storyAccountIds)
                    ->get()
                    ->keyBy('id');

                foreach ($targets as $index => $target) {
                    if (
                        ! is_array($target)
                        || ($target['format'] ?? null) !== PostFormat::Story->value
                        || ! is_string($target['connected_account_id'] ?? null)
                    ) {
                        continue;
                    }

                    $account = $accounts->get($target['connected_account_id']);

                    if ($account === null) {
                        $validator->errors()->add(
                            "targets.{$index}.connected_account_id",
                            'The selected account is not available in this workspace.',
                        );

                        continue;
                    }

                    if ($account->platform !== Platform::Instagram) {
                        continue;
                    }

                    $message = $account->instagramStoryRestrictionMessage();

                    if ($message !== null) {
                        $validator->errors()->add("targets.{$index}.format", $message);
                    }
                }
            },
        ];
    }
}
