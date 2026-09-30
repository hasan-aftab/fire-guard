<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\ClientRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->safe()->only(['search']);
        $query = Client::query()
            ->with(['sites' => fn ($query) => $query->orderBy('site_name')->orderBy('id')])
            ->withCount('sites');
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'company_name', $pattern);
                foreach (['contact_name', 'phone', 'email'] as $column) {
                    $this->whereSearchLike($query, $column, $pattern, 'or');
                }
            });
        }

        $records = $query->orderBy('company_name')->orderBy('id')->paginate(10)->withQueryString();

        if ($records->currentPage() > $records->lastPage()) {
            return to_route('clients.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('clients', [
            'records' => $records,
            'filters' => $filters,
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
        $client->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client deleted.']);

        return back();
    }
}
