<?php

use App\Enums\AdPlanStatus;
use App\Models\AdPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    WorkspaceMembership::factory()->owner()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);
    $this->owner->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    $this->actingAs($this->owner);
});

it('stores Meta ad planning drafts without activating or recording spend', function () {
    $this->post(route('ads.plans.store'), [
        'name' => 'Synthetic launch plan',
        'objective' => 'traffic',
        'planned_budget' => '125.50',
        'planned_currency' => 'USD',
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-14',
    ])->assertRedirect(route('ads.index'));

    $plan = AdPlan::query()->where('workspace_id', $this->workspace->id)->firstOrFail();

    expect($plan->platform)->toBe('meta')
        ->and($plan->status)->toBe(AdPlanStatus::Draft)
        ->and($plan->planned_budget)->toBe('125.50')
        ->and($plan->planned_currency)->toBe('USD')
        ->and($plan->getAttributes())->not->toHaveKey('actual_spend');

    expect(Route::has('ads.plans.publish'))->toBeFalse();
});

it('requires budget and currency together and rejects negative plans', function () {
    $payload = [
        'name' => 'Invalid plan',
        'objective' => 'awareness',
        'planned_budget' => '25.00',
    ];

    $this->from(route('ads.index'))
        ->post(route('ads.plans.store'), $payload)
        ->assertRedirect(route('ads.index'))
        ->assertSessionHasErrors('planned_currency');

    $this->from(route('ads.index'))
        ->post(route('ads.plans.store'), [
            ...$payload,
            'planned_budget' => '-1',
            'planned_currency' => 'USD',
        ])
        ->assertRedirect(route('ads.index'))
        ->assertSessionHasErrors('planned_budget');

    expect(AdPlan::query()->count())->toBe(0);
});

it('keeps ad plan drafts isolated to the current workspace and enforces manager permissions', function () {
    $otherWorkspace = Workspace::factory()->create();
    $foreignPlan = AdPlan::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
    ]);
    $member->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->actingAs($member)
        ->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('ads.plans.store'), [
            'name' => 'Not allowed',
            'objective' => 'leads',
        ])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->delete(route('ads.plans.destroy', $foreignPlan->id))
        ->assertNotFound();
    expect(AdPlan::query()->withoutGlobalScopes()->find($foreignPlan->id))->not->toBeNull();
});

it('supports internal review status without creating a launch action', function () {
    $plan = AdPlan::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->put(route('ads.plans.update', $plan->id), [
        'name' => $plan->name,
        'objective' => 'leads',
        'starts_on' => null,
        'ends_on' => null,
        'planned_budget' => null,
        'planned_currency' => null,
        'status' => 'ready_for_review',
    ])->assertRedirect(route('ads.index'));

    expect($plan->fresh()->status)->toBe(AdPlanStatus::ReadyForReview);
});
