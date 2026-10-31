<?php

use App\Enums\LeadStatus;
use App\Enums\SalesCampaignStatus;
use App\Models\CrmLead;
use App\Models\SalesCampaign;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

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

it('creates and edits workspace campaigns with declared UTM values', function () {
    $this->post(route('sales.campaigns.store'), [
        'name' => 'Launch',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'fall-launch',
    ])->assertRedirect(route('sales.index'));

    $campaign = SalesCampaign::query()->where('workspace_id', $this->workspace->id)->firstOrFail();

    expect($campaign->status)->toBe(SalesCampaignStatus::Draft)
        ->and($campaign->utm_source)->toBe('newsletter');

    $this->put(route('sales.campaigns.update', $campaign->id), [
        'name' => 'Launch update',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'fall-launch',
        'utm_term' => 'existing-customers',
        'utm_content' => 'variant-b',
        'status' => 'ready',
    ])->assertRedirect(route('sales.index'));

    expect($campaign->fresh()->name)->toBe('Launch update')
        ->and($campaign->fresh()->status)->toBe(SalesCampaignStatus::Ready);
});

it('requires a lead name or email and validates campaign workspace ownership', function () {
    $foreignWorkspace = Workspace::factory()->create();
    $foreignCampaign = SalesCampaign::factory()->create([
        'workspace_id' => $foreignWorkspace->id,
    ]);

    $this->from(route('sales.index'))
        ->post(route('sales.leads.store'), [])
        ->assertRedirect(route('sales.index'))
        ->assertSessionHasErrors(['name', 'email']);

    $this->from(route('sales.index'))
        ->post(route('sales.leads.store'), [
            'name' => 'Synthetic lead',
            'sales_campaign_id' => $foreignCampaign->id,
        ])
        ->assertRedirect(route('sales.index'))
        ->assertSessionHasErrors(['sales_campaign_id']);

    expect(CrmLead::query()->count())->toBe(0);
});

it('keeps leads workspace-scoped and allows an authorized user to update their stage', function () {
    $lead = CrmLead::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Synthetic contact',
        'email' => null,
    ]);

    $this->put(route('sales.leads.update', $lead->id), [
        'name' => 'Synthetic contact',
        'email' => null,
        'sales_campaign_id' => null,
        'status' => 'qualified',
    ])->assertRedirect(route('sales.index'));

    expect($lead->fresh()->status)->toBe(LeadStatus::Qualified);

    $foreignWorkspace = Workspace::factory()->create();
    $foreignLead = CrmLead::factory()->create(['workspace_id' => $foreignWorkspace->id]);

    $this->delete(route('sales.leads.destroy', $foreignLead->id))
        ->assertNotFound();
    expect(CrmLead::query()->withoutGlobalScopes()->find($foreignLead->id))->not->toBeNull();
});

it('deletes leads with personal data and detaches leads when a campaign is deleted', function () {
    $campaign = SalesCampaign::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $lead = CrmLead::factory()->create([
        'workspace_id' => $this->workspace->id,
        'sales_campaign_id' => $campaign->id,
    ]);

    $this->delete(route('sales.campaigns.destroy', $campaign->id))
        ->assertRedirect(route('sales.index'));

    expect($lead->fresh()->sales_campaign_id)->toBeNull();

    $this->delete(route('sales.leads.destroy', $lead->id))
        ->assertRedirect(route('sales.index'));
    expect(CrmLead::query()->withoutGlobalScopes()->find($lead->id))->toBeNull();
});

it('allows workspace members to view sales records but not change them', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $member->id,
    ]);
    $member->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    $campaign = SalesCampaign::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('sales.campaigns.store'), ['name' => 'Not allowed'])
        ->assertForbidden();

    expect($campaign->fresh()->name)->not->toBe('Not allowed');
});
