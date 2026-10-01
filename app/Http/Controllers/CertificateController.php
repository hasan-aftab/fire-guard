<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Http\Requests\CertificateRequest;
use App\Http\Requests\TrackerFilterRequest;
use App\Models\Certificate;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(TrackerFilterRequest $request): Response|RedirectResponse
    {
        $query = Certificate::query()->with(['site:id,client_id,site_name', 'site.client:id,company_name']);
        if ($request->filled('search')) {
            $pattern = $this->searchPattern($request);
            $query->where(function ($query) use ($pattern): void {
                $this->whereSearchLike($query, 'certificate_type', $pattern);
                $query->orWhereHas('site', function ($query) use ($pattern): void {
                    $this->whereSearchLike($query, 'site_name', $pattern);
                    $query->orWhereHas('client', fn ($query) => $this->whereSearchLike($query, 'company_name', $pattern));
                });
            });
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }
        $this->filterServiceStatus($query, $request, 'expiry_date');
        $this->filterDates($query, $request, 'expiry_date');
        $this->filterDates($query, $request, 'issue_date', 'issue_');

        $records = $query->orderBy('expiry_date')->orderBy('id')->paginate(10)->withQueryString();
        $filters = $request->validated();
        unset($filters['page']);

        if ($records->currentPage() > $records->lastPage()) {
            return redirect()->route('certificates.index', [...$filters, 'page' => $records->lastPage()]);
        }

        return Inertia::render('compliance', [
            'records' => $records,
            'sites' => Site::query()->select(['id', 'client_id', 'site_name', 'is_active'])
                ->with('client:id,company_name,is_active')->orderBy('site_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(CertificateRequest $request): RedirectResponse
    {
        Certificate::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate created.']);

        return back();
    }

    public function update(CertificateRequest $request, Certificate $certificate): RedirectResponse
    {
        $certificate->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate updated.']);

        return back();
    }
}
