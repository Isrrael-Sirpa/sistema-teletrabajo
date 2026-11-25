<?php

namespace App\Services;

use App\Models\ActivityFile;
use App\Models\User;
use App\Models\WorkActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkActivityFileService
{
    /** @param array<int, UploadedFile> $files */
    public function store(User $actor, WorkActivity $activity, array $files): void
    {
        $disk = (string) config('documents.disk');

        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
                throw ValidationException::withMessages(['files' => 'Uno de los archivos tiene un nombre no permitido.']);
            }
            $path = Storage::disk($disk)->putFileAs('activities/'.$activity->public_id, $file, Str::uuid()->toString().'.'.$file->extension());
            ActivityFile::query()->create([
                'activity_id' => $activity->getKey(), 'uploaded_by' => $actor->getKey(), 'disk' => $disk,
                'path' => $path, 'original_name' => $name, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => Storage::disk($disk)->size($path),
            ]);
        }
    }
}
