<?php

namespace App\Http\Controllers;

use App\Actions\ScheduleInspection;
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

    public function index(TrackerFilterRequest $request, ScheduleInspection $schedule): Response|RedirectResponse
    {
        $query = Inspection::query()->with(['asset:id,serial_number,location_desc', 'technician:id,full_name']);
        $this->filterRecordState($query, $request);
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
            'assets' => Asset::query()->select(['id', 'site_id', 'asset_type_id', 'serial_number', 'location_desc', 'status', 'entered_in_error_at'])
                ->with('site:id,client_id,site_name,is_active,entered_in_error_at', 'site.client:id,is_active,entered_in_error_at', 'assetType:id,type_name,default_interval_months')->orderBy('serial_number')->get()
                ->each(fn (Asset $asset) => $asset->setAttribute('suggested_next_due_date', $schedule->nextDueDate(today()->toDateString(), $asset->assetType->default_interval_months))),
            'technicians' => Technician::query()->select(['id', 'full_name', 'is_active', 'entered_in_error_at'])->orderBy('full_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(InspectionRequest $request, ScheduleInspection $schedule): RedirectResponse
    {
        $schedule->save($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection logged.']);

        return back();
    }

    public function update(InspectionRequest $request, Inspection $inspection, ScheduleInspection $schedule): RedirectResponse
    {
        $schedule->save($request->validated(), $inspection);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection updated.']);

        return back();
    }

    public function markError(Inspection $inspection, ScheduleInspection $schedule): RedirectResponse
    {
        $schedule->setEnteredInError($inspection, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection marked as entered in error.']);

        return back();
    }

    public function restoreError(Inspection $inspection, ScheduleInspection $schedule): RedirectResponse
    {
        $schedule->setEnteredInError($inspection, false);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection restored.']);

        return back();
    }
}
