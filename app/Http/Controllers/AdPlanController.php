<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AdPlanStatus;
use App\Http\Requests\Ads\StoreAdPlanRequest;
use App\Http\Requests\Ads\UpdateAdPlanRequest;
use App\Models\AdPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspaceId = $user->current_workspace_id;
        abort_unless($user->can('viewAny', AdPlan::class), 403);

        return Inertia::render('ads/index', [
            'plans' => AdPlan::query()
                ->where('workspace_id', $workspaceId)
                ->latest()
                ->get()
                ->map(fn (AdPlan $plan): array => [
                    'id' => $plan->id,
                    'platform' => $plan->platform,
                    'name' => $plan->name,
                    'objective' => $plan->objective->value,
                    'starts_on' => $plan->starts_on?->toDateString(),
                    'ends_on' => $plan->ends_on?->toDateString(),
                    'planned_budget' => $plan->planned_budget,
                    'planned_currency' => $plan->planned_currency,
                    'status' => $plan->status->value,
                ])->all(),
            'canManage' => $user->can('create', AdPlan::class),
        ]);
    }

    public function store(StoreAdPlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['workspace_id'] = $request->user()->current_workspace_id;
        $data['platform'] = 'meta';
        $data['status'] = AdPlanStatus::Draft;

        AdPlan::query()->create($data);

        return to_route('ads.index')->with('success', 'Ad plan draft created.');
    }

    public function update(UpdateAdPlanRequest $request, string $plan): RedirectResponse
    {
        $adPlan = AdPlan::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($plan);
        $this->authorize('update', $adPlan);

        $adPlan->update($request->validated());

        return to_route('ads.index')->with('success', 'Ad plan draft updated.');
    }

    public function destroy(Request $request, string $plan): RedirectResponse
    {
        $adPlan = AdPlan::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($plan);
        $this->authorize('delete', $adPlan);
        $adPlan->delete();

        return to_route('ads.index')->with('success', 'Ad plan draft deleted.');
    }
}
