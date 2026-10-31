<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\SalesCampaignStatus;
use App\Http\Requests\Commercial\StoreCrmLeadRequest;
use App\Http\Requests\Commercial\StoreSalesCampaignRequest;
use App\Http\Requests\Commercial\UpdateCrmLeadRequest;
use App\Http\Requests\Commercial\UpdateSalesCampaignRequest;
use App\Models\CrmLead;
use App\Models\SalesCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspaceId = $user->current_workspace_id;

        abort_unless($user->can('viewAny', SalesCampaign::class), 403);
        abort_unless($user->can('viewAny', CrmLead::class), 403);

        $leadSummary = array_fill_keys(
            array_map(
                fn (LeadStatus $status): string => $status->value,
                LeadStatus::cases(),
            ),
            0,
        );

        foreach (
            CrmLead::query()
                ->where('workspace_id', $workspaceId)
                ->selectRaw('status, COUNT(*) AS record_count')
                ->groupBy('status')
                ->pluck('record_count', 'status') as $status => $recordCount
        ) {
            $leadSummary[$status] = (int) $recordCount;
        }

        return Inertia::render('sales/index', [
            'campaigns' => SalesCampaign::query()
                ->where('workspace_id', $workspaceId)
                ->withCount('leads')
                ->latest()
                ->get()
                ->map(fn (SalesCampaign $campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'utm_source' => $campaign->utm_source,
                    'utm_medium' => $campaign->utm_medium,
                    'utm_campaign' => $campaign->utm_campaign,
                    'utm_term' => $campaign->utm_term,
                    'utm_content' => $campaign->utm_content,
                    'starts_on' => $campaign->starts_on?->toDateString(),
                    'ends_on' => $campaign->ends_on?->toDateString(),
                    'status' => $campaign->status->value,
                    'leads_count' => $campaign->leads_count,
                ])->all(),
            'leads' => CrmLead::query()
                ->where('workspace_id', $workspaceId)
                ->with('campaign:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (CrmLead $lead): array => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'status' => $lead->status->value,
                    'campaign_id' => $lead->sales_campaign_id,
                    'campaign_name' => $lead->campaign?->name,
                ])->all(),
            'leadSummary' => $leadSummary,
            'canManage' => $user->can('create', SalesCampaign::class)
                && $user->can('create', CrmLead::class),
        ]);
    }

    public function storeCampaign(StoreSalesCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] ??= SalesCampaignStatus::Draft->value;
        $data['workspace_id'] = $request->user()->current_workspace_id;

        SalesCampaign::query()->create($data);

        return to_route('sales.index')->with('success', 'Campaign created.');
    }

    public function updateLead(UpdateCrmLeadRequest $request, string $lead): RedirectResponse
    {
        $leadModel = CrmLead::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($lead);
        $this->authorize('update', $leadModel);

        $leadModel->update($request->validated());

        return to_route('sales.index')->with('success', 'Lead updated.');
    }

    public function updateCampaign(UpdateSalesCampaignRequest $request, string $campaign): RedirectResponse
    {
        $campaignModel = SalesCampaign::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($campaign);
        $this->authorize('update', $campaignModel);
        $campaignModel->update($request->validated());

        return to_route('sales.index')->with('success', 'Campaign updated.');
    }

    public function storeLead(StoreCrmLeadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['status']);
        $data['workspace_id'] = $request->user()->current_workspace_id;

        CrmLead::query()->create($data);

        return to_route('sales.index')->with('success', 'Lead created.');
    }

    public function destroyCampaign(Request $request, string $campaign): RedirectResponse
    {
        $campaignModel = SalesCampaign::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($campaign);
        $this->authorize('delete', $campaignModel);
        $campaignModel->delete();

        return to_route('sales.index')->with('success', 'Campaign deleted.');
    }

    public function destroyLead(Request $request, string $lead): RedirectResponse
    {
        $leadModel = CrmLead::query()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($lead);
        $this->authorize('delete', $leadModel);
        $leadModel->delete();

        return to_route('sales.index')->with('success', 'Lead deleted.');
    }
}
