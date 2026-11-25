<?php

namespace App\Http\Controllers;

use App\Models\ActivityFile;
use App\Models\WorkActivity;
use App\Services\WorkActivityFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WorkActivityFileController extends Controller
{
    public function store(Request $request, WorkActivity $activity, WorkActivityFileService $files): RedirectResponse
    {
        Gate::authorize('attachFile', $activity);
        $data = $request->validate(['files' => ['required', 'array', 'min:1', 'max:10'], 'files.*' => ['file', 'max:20480']]);
        $files->store($request->user(), $activity, $data['files']);

        return back()->with('status', 'Archivo adjuntado a la actividad.');
    }

    public function download(WorkActivity $activity, ActivityFile $activityFile)
    {
        Gate::authorize('view', $activity);
        abort_unless($activityFile->activity_id === $activity->getKey(), 404);
        abort_unless(Storage::disk($activityFile->disk)->exists($activityFile->path), 404);

        return Storage::disk($activityFile->disk)->download($activityFile->path, $activityFile->original_name);
    }

    public function preview(WorkActivity $activity, ActivityFile $activityFile)
    {
        Gate::authorize('view', $activity);
        abort_unless($activityFile->activity_id === $activity->getKey(), 404);
        abort_unless(in_array($activityFile->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return Storage::disk($activityFile->disk)->response($activityFile->path, $activityFile->original_name, [
            'Content-Type' => $activityFile->mime_type,
            'Content-Disposition' => 'inline; filename="'.$activityFile->original_name.'"',
        ]);
    }

    public function destroy(Request $request, WorkActivity $activity, ActivityFile $activityFile): RedirectResponse
    {
        Gate::authorize('deleteOwnFile', $activity);
        abort_unless($activityFile->activity_id === $activity->getKey(), 404);
        abort_unless($activityFile->uploaded_by === $request->user()->getKey(), 403);
        Storage::disk($activityFile->disk)->delete($activityFile->path);
        $activityFile->delete();

        return back()->with('status', 'Archivo retirado de la actividad.');
    }
}
