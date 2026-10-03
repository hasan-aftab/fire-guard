<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReminderRecipientRequest;
use App\Models\ReminderRecipient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReminderRecipientController extends Controller
{
    public function store(StoreReminderRecipientRequest $request): RedirectResponse
    {
        ReminderRecipient::query()->createOrFirst($request->safe()->only(['email']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder recipient added.']);

        return back();
    }

    public function destroy(ReminderRecipient $reminderRecipient): RedirectResponse
    {
        $reminderRecipient->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder recipient removed.']);

        return back();
    }
}
