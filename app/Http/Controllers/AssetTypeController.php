<?php

namespace App\Http\Controllers;

use App\Actions\SyncFireNotifications;
use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\AssetTypeRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\AssetType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssetTypeController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $query = AssetType::query()->withCount('assets');
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(fn ($query) => $this->whereSearchLike($query, 'type_name', $pattern));
        }
        $records = $query->orderBy('type_name')->orderBy('id')->paginate(10)->withQueryString();
        $filters = $request->safe()->except(['page']);
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('asset-types.index', $filters + ['page' => $records->lastPage()]);
        }

        return Inertia::render('asset-types', ['records' => $records, 'filters' => $filters]);
    }

    public function store(AssetTypeRequest $request): RedirectResponse
    {
        AssetType::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset type created.']);

        return back();
    }

    public function update(AssetTypeRequest $request, AssetType $assetType, SyncFireNotifications $notifications): RedirectResponse
    {
        $assetType->update($request->validated());
        if ($assetType->wasChanged('type_name')) {
            $notifications->handleAll();
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset type updated.']);

        return back();
    }

    public function destroy(AssetType $assetType): RedirectResponse
    {
        $assetType->update(['is_active' => false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset type deactivated.']);

        return back();
    }

    public function restore(AssetType $assetType): RedirectResponse
    {
        $assetType->update(['is_active' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset type activated.']);

        return back();
    }
}
