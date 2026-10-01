<?php

namespace App\Http\Controllers;

use App\Concerns\MarksEnteredInError;
use App\Http\Requests\SiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteController extends Controller
{
    use MarksEnteredInError;

    public function store(SiteRequest $request): RedirectResponse
    {
        Site::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site created.']);

        return back();
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site updated.']);

        return back();
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->update(['is_active' => false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site deactivated.']);

        return back();
    }

    public function restore(Site $site): RedirectResponse
    {
        $site->update(['is_active' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site reactivated.']);

        return back();
    }

    public function markError(Site $site): RedirectResponse
    {
        return $this->setEnteredInError($site, true, 'Site');
    }

    public function restoreError(Site $site): RedirectResponse
    {
        return $this->setEnteredInError($site, false, 'Site');
    }

    public function purge(Site $site): RedirectResponse
    {
        return $this->deleteUnlinked($site, $site->assets()->exists() || $site->certificates()->exists(), 'Site');
    }
}
