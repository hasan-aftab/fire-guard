<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\TechnicianFilterRequest;
use App\Http\Requests\TechnicianRequest;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TechnicianController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TechnicianFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->safe()->except(['page']);
        $query = Technician::query();
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

        $records = $query->orderBy('full_name')->orderBy('id')->paginate(10)->withQueryString();
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('technicians.index', $filters + ($records->lastPage() > 1 ? ['page' => $records->lastPage()] : []));
        }

        return Inertia::render('technicians', [
            'records' => $records,
            'filters' => $filters,
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
        $technician->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Technician deleted.']);

        return back();
    }
}
