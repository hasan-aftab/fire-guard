<?php

namespace App\Http\Controllers;

use App\Actions\SyncFireNotifications;
use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\CertificateTypeRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\CertificateType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CertificateTypeController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $query = CertificateType::query()->withCount('certificates');
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(fn ($query) => $this->whereSearchLike($query, 'name', $pattern));
        }
        $records = $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
        $filters = $request->safe()->except(['page']);
        if ($records->currentPage() > $records->lastPage()) {
            return to_route('certificate-types.index', $filters + ['page' => $records->lastPage()]);
        }

        return Inertia::render('certificate-types', ['records' => $records, 'filters' => $filters]);
    }

    public function store(CertificateTypeRequest $request): RedirectResponse
    {
        CertificateType::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate type created.']);

        return back();
    }

    public function update(CertificateTypeRequest $request, CertificateType $certificateType, SyncFireNotifications $notifications): RedirectResponse
    {
        $certificateType->update($request->validated());
        if ($certificateType->wasChanged('name')) {
            $notifications->handleAll();
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate type updated.']);

        return back();
    }

    public function destroy(CertificateType $certificateType): RedirectResponse
    {
        $certificateType->update(['is_active' => false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate type deactivated.']);

        return back();
    }

    public function restore(CertificateType $certificateType): RedirectResponse
    {
        $certificateType->update(['is_active' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate type activated.']);

        return back();
    }
}
