<?php

namespace App\Http\Controllers;

use App\Actions\ManagedFiles;
use App\Models\Certificate;
use App\Models\Inspection;
use App\Models\ManagedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ManagedUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['photo', 'document'])],
            'record_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);
        $kind = $validated['kind'];
        $recordId = (int) $validated['record_id'];
        $exists = $kind === 'photo'
            ? Inspection::whereKey($recordId)->exists()
            : Certificate::whereKey($recordId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['record_id' => 'The record no longer exists.']);
        }

        $limit = (int) config($kind === 'photo' ? 'fire-tracker.photo_max_kilobytes' : 'fire-tracker.document_max_kilobytes') * 1024;
        if ((int) $validated['size'] > $limit) {
            throw ValidationException::withMessages(['size' => 'The file is too large for this record.']);
        }
        if (ManagedUpload::query()->where('user_id', $request->user()->id)->where('expires_at', '>', now())->whereIn('status', ['pending', 'processing'])->count() >= 10) {
            throw ValidationException::withMessages(['files' => 'Finish or remove an existing upload before starting another.']);
        }

        $chunkBytes = (int) config('fire-tracker.upload_chunk_bytes');
        $upload = ManagedUpload::create([
            'user_id' => $request->user()->id,
            'kind' => $kind,
            'record_id' => $recordId,
            'storage_disk' => (string) config('fire-tracker.disk'),
            'original_name' => $validated['name'],
            'file_size' => $validated['size'],
            'total_chunks' => (int) ceil($validated['size'] / $chunkBytes),
            'expires_at' => now()->addHours(2),
        ]);

        return response()->json(['id' => $upload->id, 'chunk_bytes' => $chunkBytes, 'total_chunks' => $upload->total_chunks], 201);
    }

    public function chunk(Request $request, ManagedUpload $upload, int $index): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        $chunkBytes = (int) config('fire-tracker.upload_chunk_bytes');
        if ((int) $request->server('CONTENT_LENGTH', 0) > $chunkBytes) {
            abort(413, 'Upload chunk is too large.');
        }
        $contents = $request->getContent();
        if (strlen($contents) > $chunkBytes) {
            abort(413, 'Upload chunk is too large.');
        }

        DB::transaction(function () use ($upload, $index, $chunkBytes, $contents): void {
            $locked = ManagedUpload::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);
            if ($index < $locked->next_chunk) {
                return;
            }
            abort_unless($index === $locked->next_chunk && $index < $locked->total_chunks, 409);
            $expected = min($chunkBytes, $locked->file_size - ($index * $chunkBytes));
            abort_unless(strlen($contents) === $expected, 422, 'Upload chunk size does not match.');
            $path = $this->chunkPath($locked, $index);
            if (! Storage::disk($locked->storage_disk)->put($path, $contents, ['visibility' => 'private'])) {
                throw new RuntimeException('Could not store upload chunk.');
            }
            $locked->increment('next_chunk');
        });

        return response()->json(['received' => $index]);
    }

    public function complete(Request $request, ManagedUpload $upload, ManagedFiles $files): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        if ($upload->status === 'done') {
            return response()->json(['message' => 'File uploaded.']);
        }
        DB::transaction(function () use ($upload): void {
            $locked = ManagedUpload::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending' && $locked->next_chunk === $locked->total_chunks, 409);
            $locked->update(['status' => 'processing']);
        });

        $temporary = tmpfile();
        if ($temporary === false) {
            $upload->update(['status' => 'pending']);

            return response()->json(['message' => 'Could not prepare the file. Please retry.'], 503);
        }

        $stored = null;
        try {
            for ($index = 0; $index < $upload->total_chunks; $index++) {
                $stream = Storage::disk($upload->storage_disk)->readStream($this->chunkPath($upload, $index));
                if (! is_resource($stream)) {
                    throw new RuntimeException('An upload chunk is missing.');
                }
                stream_copy_to_stream($stream, $temporary);
                fclose($stream);
            }
            if (ftell($temporary) !== $upload->file_size) {
                throw new RuntimeException('The uploaded file size does not match.');
            }
            $path = stream_get_meta_data($temporary)['uri'] ?? null;
            if (! is_string($path)) {
                throw new RuntimeException('The uploaded file could not be prepared.');
            }
            $file = new UploadedFile($path, $upload->original_name, null, null, true);
            $rules = $upload->kind === 'photo'
                ? [File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max((int) config('fire-tracker.photo_max_kilobytes')), 'extensions:jpg,jpeg,png,webp']
                : [File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('fire-tracker.document_max_kilobytes')), 'extensions:pdf,jpg,jpeg,png'];
            Validator::make(['file' => $file], ['file' => $rules])->validate();

            $directory = $upload->kind === 'photo'
                ? 'fire-tracker/inspections/'.$upload->record_id
                : 'fire-tracker/certificates/'.$upload->record_id;
            $stored = $files->store($file, $directory, $upload->storage_disk);
            $files->attach($upload->kind, $upload->record_id, $stored);
            $upload->update(['status' => 'done']);
        } catch (ValidationException $exception) {
            $upload->update(['status' => 'failed']);

            throw $exception;
        } catch (Throwable $exception) {
            if ($stored !== null) {
                Storage::disk($stored['storage_disk'])->delete($stored['storage_path']);
            }
            $upload->update(['status' => 'pending']);
            report($exception);

            return response()->json(['message' => 'The file could not be saved. Please retry.'], 503);
        } finally {
            fclose($temporary);
        }

        $this->cleanChunks($upload);

        return response()->json(['message' => 'File uploaded.'], 201);
    }

    public function destroy(Request $request, ManagedUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        abort_if(in_array($upload->status, ['processing', 'done'], true), 409);
        $upload->update(['status' => 'cancelled']);
        $this->cleanChunks($upload);

        return response()->json(['message' => 'Upload removed.']);
    }

    private function authorizeUpload(Request $request, ManagedUpload $upload): void
    {
        abort_unless($upload->user_id === $request->user()?->id, 404);
        abort_if($upload->expires_at->isPast(), 410, 'Upload expired. Please select the file again.');
    }

    private function chunkPath(ManagedUpload $upload, int $index): string
    {
        return 'fire-tracker/pending/'.$upload->id.'/'.$index;
    }

    private function cleanChunks(ManagedUpload $upload): void
    {
        try {
            Storage::disk($upload->storage_disk)->deleteDirectory('fire-tracker/pending/'.$upload->id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
