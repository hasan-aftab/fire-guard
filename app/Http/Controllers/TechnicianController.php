<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Concerns\MarksEnteredInError;
use App\Http\Requests\TechnicianFilterRequest;
use App\Http\Requests\TechnicianRequest;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TechnicianController extends Controller
{
    use FiltersFireTrackerRecords;
    use MarksEnteredInError;

    public function index(TechnicianFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->safe()->except(['page']);
        $today = today()->toDateString();
        $soon = today()->addDays(30)->toDateString();
        $counts = Technician::query()->whereNull('entered_in_error_at')
            ->selectRaw('COUNT(CASE WHEN is_active = 1 THEN 1 END) AS active, COUNT(CASE WHEN is_active = 1 AND license_expiry < ? THEN 1 END) AS expired, COUNT(CASE WHEN is_active = 1 AND license_expiry BETWEEN ? AND ? THEN 1 END) AS expiring_soon, COUNT(CASE WHEN is_active = 1 AND license_expiry IS NULL THEN 1 END) AS missing', [$today, $today, $soon])
            ->firstOrFail();
        $overview = [
            'active' => (int) $counts->getAttribute('active'),
            'expired' => (int) $counts->getAttribute('expired'),
            'expiring_soon' => (int) $counts->getAttribute('expiring_soon'),
            'missing' => (int) $counts->getAttribute('missing'),
        ];
        $query = Technician::query()->withCount('inspections');
        $this->filterRecordState($query, $request);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'full_name', $pattern);
                $this->whereSearchLike($query, 'phone', $pattern, 'or');
                $this->whereSearchLike($query, 'email', $pattern, 'or');
                $this->whereSearchLike($query, 'license_number', $pattern, 'or');
            });
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        match ($request->string('license_status')->toString()) {
            'expired' => $query->where('license_expiry', '<', $today),
            'expiring_soon' => $query->whereBetween('license_expiry', [$today, $soon]),
            'valid' => $query->where('license_expiry', '>', $soon),
            'missing' => $query->whereNull('license_expiry'),
            default => null,
        };

        if ($request->filled('from')) {
            $query->where('license_expiry', '>=', $request->string('from')->toString());
        }
        if ($request->filled('to')) {
            $query->where('license_expiry', '<=', $request->string('to')->toString());
        }

        match ($request->string('inspection_activity')->toString()) {
            'with_inspections' => $query->has('inspections'),
            'without_inspections' => $query->doesntHave('inspections'),
            default => null,
        };

        match ($request->string('sort')->toString()) {
            'expiry_soonest' => $query->orderByRaw('CASE WHEN license_expiry IS NULL THEN 1 ELSE 0 END')->orderBy('license_expiry')->orderBy('full_name'),
            'most_inspections' => $query->orderByDesc('inspections_count')->orderBy('full_name'),
            default => $query->orderBy('full_name'),
        };

        $records = $query->orderBy('id')->paginate(10)->withQueryString();
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('technicians.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('technicians', [
            'records' => $records->through(function (Technician $technician) use ($today, $soon): Technician {
                $expiry = $technician->license_expiry?->toDateString();
                $technician->setAttribute('license_status', match (true) {
                    $expiry === null => 'unknown',
                    $expiry < $today => 'expired',
                    $expiry <= $soon => 'expiring_soon',
                    default => 'valid',
                });

                return $technician;
            }),
            'filters' => $filters,
            'overview' => $overview,
        ]);
    }

    public function store(TechnicianRequest $request): RedirectResponse
    {
        Technician::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Technician created.']);

        return back();
    }

    public function update(TechnicianRequest $request, Technician $technician): RedirectResponse
    {
        $technician->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Technician updated.']);

        return back();
    }

    public function destroy(Technician $technician): RedirectResponse
    {
        $technician->update(['is_active' => false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Technician deactivated.']);

        return back();
    }

    public function restore(Technician $technician): RedirectResponse
    {
        $technician->update(['is_active' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Technician reactivated.']);

        return back();
    }

    public function markError(Technician $technician): RedirectResponse
    {
        return $this->setEnteredInError($technician, true, 'Technician');
    }

    public function restoreError(Technician $technician): RedirectResponse
    {
        return $this->setEnteredInError($technician, false, 'Technician');
    }

    public function purge(Technician $technician): RedirectResponse
    {
        return $this->deleteUnlinked($technician, $technician->inspections()->exists(), 'Technician');
    }
}
