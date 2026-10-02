<?php

namespace App\Http\Controllers;

use App\Actions\ManagedFiles;
use App\Models\Certificate;
use App\Models\CertificateDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CertificateDocumentController extends Controller
{
    public function store(Request $request, Certificate $certificate, ManagedFiles $files): RedirectResponse
    {
        $validated = $request->validate([
            'document' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('fire-tracker.document_max_kilobytes')), 'extensions:pdf,jpg,jpeg,png'],
        ]);

        try {
            $metadata = $files->store($validated['document'], 'fire-tracker/certificates/'.$certificate->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['document' => 'The document could not be stored. Please try again.']);
        }

        try {
            [$oldDocument, $document] = DB::transaction(function () use ($certificate, $metadata): array {
                Certificate::query()->whereKey($certificate->id)->lockForUpdate()->firstOrFail();
                $oldDocument = CertificateDocument::query()->where('certificate_id', $certificate->id)
                    ->whereNull('pending_deletion_at')->orderByDesc('id')->first();
                $oldDocument?->update(['pending_deletion_at' => now()]);
                $document = $certificate->document()->create($metadata);

                return [$oldDocument, $document];
            });
        } catch (Throwable $exception) {
            Storage::disk($metadata['storage_disk'])->delete($metadata['storage_path']);
            report($exception);

            return back()->withErrors(['document' => 'The document could not be saved. Please try again.']);
        }

        $files->createThumbnail($document);

        if ($oldDocument !== null) {
            try {
                $files->deleteObject($oldDocument);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate document uploaded.']);

        return back();
    }

    public function show(Certificate $certificate, CertificateDocument $document, ManagedFiles $files): StreamedResponse
    {
        abort_unless($document->certificate_id === $certificate->id, 404);

        return $files->response($document);
    }

    public function download(Certificate $certificate, CertificateDocument $document, ManagedFiles $files): StreamedResponse
    {
        abort_unless($document->certificate_id === $certificate->id, 404);

        return $files->response($document, true);
    }

    public function thumbnail(Certificate $certificate, CertificateDocument $document, ManagedFiles $files): StreamedResponse
    {
        abort_unless($document->certificate_id === $certificate->id, 404);

        return $files->thumbnailResponse($document);
    }

    public function destroy(Certificate $certificate, CertificateDocument $document, ManagedFiles $files): RedirectResponse
    {
        abort_unless($document->certificate_id === $certificate->id && $document->pending_deletion_at === null, 404);
        try {
            $files->remove($document);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['document' => 'The document could not be removed. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Certificate document removed.']);

        return back();
    }
}
