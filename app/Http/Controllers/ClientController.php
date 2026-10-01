<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Concerns\MarksEnteredInError;
use App\Http\Requests\ClientFilterRequest;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use FiltersFireTrackerRecords;
    use MarksEnteredInError;

    public function index(ClientFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->safe()->only(['search', 'active', 'site_presence', 'sort', 'record_state']);
        $selectedClientId = $request->integer('site_client');
        $siteParameters = $selectedClientId ? ['site_client' => $selectedClientId] + $request->safe()->only(['site_page', 'site_state']) : [];
        $query = Client::query()
            ->withCount(['sites', 'sites as current_sites_count' => fn ($siteQuery) => $siteQuery->whereNull('entered_in_error_at')])
            ->with(['sites' => fn ($siteQuery) => $siteQuery
                ->select('id', 'client_id', 'site_name', 'address', 'is_active', 'entered_in_error_at')
                ->whereNull('entered_in_error_at')
                ->orderBy('site_name')
                ->orderBy('id')
                ->limit(5)]);
        $this->filterRecordState($query, $request);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'company_name', $pattern);
                foreach (['contact_name', 'phone', 'email'] as $column) {
                    $this->whereSearchLike($query, $column, $pattern, 'or');
                }
                $query->orWhereHas('sites', function ($siteQuery) use ($pattern): void {
                    $siteQuery->where(function ($siteQuery) use ($pattern): void {
                        $this->whereSearchLike($siteQuery, 'site_name', $pattern);
                        $this->whereSearchLike($siteQuery, 'address', $pattern, 'or');
                    });
                });
            });
        }

        match ($request->string('active')->toString()) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        match ($request->string('site_presence')->toString()) {
            'with_sites' => $query->has('sites'),
            'without_sites' => $query->doesntHave('sites'),
            default => null,
        };

        match ($request->string('sort')->toString()) {
            'name_desc' => $query->orderByDesc('company_name'),
            'most_sites' => $query->orderByDesc('sites_count')->orderBy('company_name'),
            default => $query->orderBy('company_name'),
        };

        $records = $query->orderBy('id')->paginate(10)->withQueryString();

        if ($records->currentPage() > $records->lastPage()) {
            return to_route('clients.index', $filters + $siteParameters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        $selectedClient = $selectedClientId ? Client::query()->select('id', 'company_name', 'is_active', 'entered_in_error_at')->find($selectedClientId) : null;
        $selectedSites = $selectedClient?->sites()
            ->withCount(['assets', 'certificates'])
            ->when($request->input('site_state') === 'entered_in_error', fn ($query) => $query->whereNotNull('entered_in_error_at'), fn ($query) => $query->whereNull('entered_in_error_at'))
            ->orderBy('site_name')
            ->orderBy('id')
            ->paginate(10, ['*'], 'site_page');

        if ($selectedSites && $selectedSites->currentPage() > $selectedSites->lastPage()) {
            return to_route('clients.index', $filters + ['site_client' => $selectedClientId] + $request->safe()->only(['site_state']) + ($records->currentPage() > 1 ? ['page' => $records->currentPage()] : []) + ($selectedSites->lastPage() > 1 ? ['site_page' => $selectedSites->lastPage()] : []));
        }

        return Inertia::render('clients', [
            'records' => $records,
            'filters' => $filters,
            'selectedClient' => $selectedClient,
            'selectedSites' => $selectedSites,
            'siteState' => $request->input('site_state') === 'entered_in_error' ? 'entered_in_error' : 'current',
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client created.']);

        return back();
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client updated.']);

        return back();
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->update(['is_active' => false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client deactivated.']);

        return back();
    }

    public function restore(Client $client): RedirectResponse
    {
        $client->update(['is_active' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client reactivated.']);

        return back();
    }

    public function markError(Client $client): RedirectResponse
    {
        return $this->setEnteredInError($client, true, 'Client');
    }

    public function restoreError(Client $client): RedirectResponse
    {
        return $this->setEnteredInError($client, false, 'Client');
    }

    public function purge(Client $client): RedirectResponse
    {
        return $this->deleteUnlinked($client, $client->sites()->exists(), 'Client');
    }
}
