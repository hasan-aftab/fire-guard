<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Concerns\MarksEnteredInError;
use App\Http\Requests\AssetFilterRequest;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    use FiltersFireTrackerRecords;
    use MarksEnteredInError;

    public function index(AssetFilterRequest $request): Response|RedirectResponse
    {
        $query = Asset::query()
            ->select(['id', 'site_id', 'asset_type_id', 'serial_number', 'location_desc', 'install_date', 'last_service_date', 'next_service_due', 'status', 'entered_in_error_at'])
            ->withCount(['inspections', 'certificates'])
            ->with(['site:id,client_id,site_name,is_active,entered_in_error_at', 'site.client:id,company_name,is_active,entered_in_error_at', 'assetType:id,type_name,is_active']);
        $this->filterRecordState($query, $request);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'serial_number', $pattern);
                $this->whereSearchLike($query, 'location_desc', $pattern, 'or');
                $query->orWhereHas('site', function ($query) use ($pattern): void {
                    $this->whereSearchLike($query, 'site_name', $pattern);
                    $query->orWhereHas('client', fn ($query) => $this->whereSearchLike($query, 'company_name', $pattern));
                });
                $query->orWhereHas('assetType', fn ($query) => $this->whereSearchLike($query, 'type_name', $pattern));
            });
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }
        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', $request->integer('asset_type_id'));
        }
        if ($request->filled('equipment_status') && $request->input('equipment_status') !== 'all') {
            $query->where('status', $request->string('equipment_status')->toString());
        }
        if (in_array($request->string('status')->toString(), ['overdue', 'due_soon', 'ok'], true)) {
            $query->where('status', 'active')
                ->whereHas('site', fn ($site) => $site->where('is_active', true)->whereNull('entered_in_error_at')
                    ->whereHas('client', fn ($client) => $client->where('is_active', true)->whereNull('entered_in_error_at')));
        }
        $this->filterServiceStatus($query, $request, 'next_service_due', Asset::DUE_SOON_DAYS);
        $this->filterDates($query, $request, 'next_service_due');

        match ($request->string('sort')->toString()) {
            'newest' => $query->orderByDesc('id'),
            'due_soonest' => $query->orderByRaw('CASE WHEN next_service_due IS NULL THEN 1 ELSE 0 END')->orderBy('next_service_due')->orderBy('id'),
            'due_latest' => $query->orderByRaw('CASE WHEN next_service_due IS NULL THEN 1 ELSE 0 END')->orderByDesc('next_service_due')->orderBy('id'),
            default => $query->orderBy('id'),
        };

        $filters = $request->safe()->except(['page']);
        $records = $query->paginate(10)->withQueryString()
            ->through(fn (Asset $asset) => $asset->append('service_status'));
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('assets.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('assets', [
            'records' => $records,
            'sites' => Site::select(['id', 'client_id', 'site_name', 'is_active', 'entered_in_error_at'])->with('client:id,company_name,is_active,entered_in_error_at')->orderBy('site_name')->orderBy('id')->get(),
            'assetTypes' => AssetType::select(['id', 'type_name', 'is_active'])->orderBy('type_name')->orderBy('id')->get(),
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
        $asset->update(['status' => 'decommissioned']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment decommissioned.']);

        return back();
    }

    public function restore(Asset $asset): RedirectResponse
    {
        $asset->update(['status' => 'active']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment reactivated.']);

        return back();
    }

    public function markError(Asset $asset): RedirectResponse
    {
        return $this->setEnteredInError($asset, true, 'Equipment');
    }

    public function restoreError(Asset $asset): RedirectResponse
    {
        return $this->setEnteredInError($asset, false, 'Equipment');
    }

    public function purge(Asset $asset): RedirectResponse
    {
        return $this->deleteUnlinked($asset, $asset->inspections()->exists() || $asset->certificates()->exists(), 'Equipment');
    }
}
