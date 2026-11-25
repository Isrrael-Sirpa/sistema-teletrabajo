<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DocumentTemplateController extends Controller
{
    public function download(Document $document): Response
    {
        Gate::authorize('view', $document);
        abort_unless(
            $document->general_status === DocumentStatus::DRAFT
            && $document->documentType->uses_correlative
            && $document->code !== null
            && in_array($document->authorization_status, [
                OutgoingAuthorizationStatus::NOT_REQUESTED,
                OutgoingAuthorizationStatus::RETURNED,
            ], true),
            404,
        );

        $document->loadMissing(['originUnit', 'recipients.unit', 'recipients.user']);
        $mainRecipients = $document->recipients->where('requires_response', true)
            ->map(fn ($recipient): string => $recipient->user?->displayName() ?? $recipient->unit->name)
            ->values();
        $copies = $document->recipients->where('requires_response', false)
            ->map(fn ($recipient): string => $recipient->user?->displayName() ?? $recipient->unit->name)
            ->values();
        $date = ($document->correlative_reserved_at ?? $document->created_at)
            ->timezone(config('app.timezone'))
            ->translatedFormat('d \d\e F \d\e Y');
        $recipient = $mainRecipients->isEmpty() ? '________________________________________' : $mainRecipients->join('; ');
        $copyLine = $copies->isEmpty() ? '' : '<p><strong>cc:</strong> '.e($copies->join(' · ')).'</p>';
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:Arial,sans-serif;font-size:11pt;line-height:1.45;margin:2.5cm} .cite{text-align:right;font-weight:bold} .date{text-align:right;margin-top:18px} .subject{font-weight:bold;margin-top:32px} .space{height:180px}'
            .'</style></head><body>'
            .'<p class="cite">'.e($document->code).'</p>'
            .'<p class="date">La Paz, '.e($date).'</p>'
            .'<p>Señor(a):<br><strong>'.e($recipient).'</strong><br>Presente.-</p>'
            .'<p class="subject">Ref.: '.e($document->subject === 'Pendiente de redacción' ? '____________________________' : $document->subject).'</p>'
            .'<div class="space"></div>'.$copyLine
            .'</body></html>';
        $filename = Str::slug($document->documentType->name.'-'.$document->correlative_number).'.doc';

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
