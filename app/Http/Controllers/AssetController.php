<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\AssetRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $query = Asset::query()->with(['site.client', 'assetType']);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'serial_number', $pattern);
                $this->whereSearchLike($query, 'location_desc', $pattern, 'or');
                $query->orWhereHas('site', function ($query) use ($pattern): void {
                    $this->whereSearchLike($query, 'site_name', $pattern);
                    $query->orWhereHas('client', fn ($query) => $this->whereSearchLike($query, 'company_name', $pattern));
                });
            });
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }
        $this->filterServiceStatus($query, $request, 'next_service_due');
        $this->filterDates($query, $request, 'next_service_due');

        $filters = $request->safe()->except(['page']);
        $records = $query->orderBy('id')->paginate(10)->withQueryString();
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('assets.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('assets', [
            'records' => $records,
            'sites' => Site::with('client')->orderBy('site_name')->get(),
            'assetTypes' => AssetType::orderBy('id')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        Asset::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset created.']);

        return back();
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset updated.']);

        return back();
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset deleted.']);

        return back();
    }
}
