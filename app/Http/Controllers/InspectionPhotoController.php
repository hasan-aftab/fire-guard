<?php

namespace App\Http\Controllers;

use App\Actions\ManagedFiles;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InspectionPhotoController extends Controller
{
    public function store(Request $request, Inspection $inspection, ManagedFiles $files): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max((int) config('fire-tracker.photo_max_kilobytes')), 'extensions:jpg,jpeg,png,webp'],
        ]);

        $stored = [];
        try {
            foreach ($validated['photos'] as $photo) {
                $stored[] = $files->store($photo, 'fire-tracker/inspections/'.$inspection->id);
            }
            $photos = DB::transaction(function () use ($inspection, $stored): array {
                $photos = [];
                foreach ($stored as $metadata) {
                    $photos[] = $inspection->photos()->create($metadata);
                }

                return $photos;
            });
            foreach ($photos as $photo) {
                $files->createThumbnail($photo);
            }
        } catch (Throwable $exception) {
            foreach ($stored as $metadata) {
                Storage::disk($metadata['storage_disk'])->delete($metadata['storage_path']);
            }
            report($exception);

            return back()->withErrors(['photos' => 'Photos could not be saved. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection photos uploaded.']);

        return back();
    }

    public function show(Inspection $inspection, InspectionPhoto $photo, ManagedFiles $files): StreamedResponse
    {
        abort_unless($photo->inspection_id === $inspection->id, 404);

        return $files->response($photo);
    }

    public function download(Inspection $inspection, InspectionPhoto $photo, ManagedFiles $files): StreamedResponse
    {
        abort_unless($photo->inspection_id === $inspection->id, 404);

        return $files->response($photo, true);
    }

    public function thumbnail(Inspection $inspection, InspectionPhoto $photo, ManagedFiles $files): StreamedResponse
    {
        abort_unless($photo->inspection_id === $inspection->id, 404);

        return $files->thumbnailResponse($photo);
    }

    public function destroy(Inspection $inspection, InspectionPhoto $photo, ManagedFiles $files): RedirectResponse
    {
        abort_unless($photo->inspection_id === $inspection->id && $photo->pending_deletion_at === null, 404);
        try {
            $files->remove($photo);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['photos' => 'The photo could not be removed. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection photo removed.']);

        return back();
    }
}
