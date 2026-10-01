<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

trait MarksEnteredInError
{
    private function deleteUnlinked(Model $record, bool $hasLinks, string $noun): RedirectResponse
    {
        abort_if($hasLinks, 409, 'This record has linked history and cannot be permanently deleted.');

        try {
            /** @throws QueryException When a new foreign-key link races with the check above. */
            $record->delete();
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['19', '23000'], true)) {
                abort(409, 'This record acquired a link and cannot be permanently deleted.');
            }

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $noun.' deleted.']);

        return back();
    }

    private function setEnteredInError(Model $record, bool $enteredInError, string $noun): RedirectResponse
    {
        $record->setAttribute('entered_in_error_at', $enteredInError ? now() : null);
        $record->save();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $noun.($enteredInError ? ' marked as entered in error.' : ' restored.'),
        ]);

        return back();
    }
}
