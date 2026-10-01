<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\InspectionRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\Asset;
use App\Models\Inspection;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $query = Inspection::query()->with(['asset:id,serial_number,location_desc', 'technician:id,full_name']);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'notes', $pattern);
                $query->orWhereHas('asset', function ($query) use ($pattern): void {
                    $this->whereSearchLike($query, 'serial_number', $pattern);
                    $this->whereSearchLike($query, 'location_desc', $pattern, 'or');
                });
                $query->orWhereHas('technician', fn ($query) => $this->whereSearchLike($query, 'full_name', $pattern));
            });
        }
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->integer('asset_id'));
        }
        if ($request->filled('result') && $request->string('result')->toString() !== 'all') {
            $query->where('result', $request->string('result')->toString());
        }
        $this->filterDates($query, $request, 'inspection_date');

        $filters = $request->safe()->except(['page']);
        $records = $query->orderByDesc('inspection_date')->orderByDesc('id')->paginate(10)->withQueryString();
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('inspections.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('inspections', [
            'records' => $records,
            'assets' => Asset::query()->select(['id', 'site_id', 'serial_number', 'location_desc', 'status'])
                ->with('site:id,client_id,site_name,is_active', 'site.client:id,is_active')->orderBy('serial_number')->get(),
            'technicians' => Technician::query()->select(['id', 'full_name', 'is_active'])->orderBy('full_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(InspectionRequest $request): RedirectResponse
    {
        Inspection::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection logged.']);

        return back();
    }

    public function update(InspectionRequest $request, Inspection $inspection): RedirectResponse
    {
        $inspection->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection updated.']);

        return back();
    }
}
