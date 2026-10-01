<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteController extends Controller
{
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
}
