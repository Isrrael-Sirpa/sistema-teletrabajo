<?php

namespace App\Services;

use App\Enums\DocumentDeadlineStatus;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentDeadlineNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class DocumentDeadlineReminderService
{
    public function __construct(
        private readonly DocumentDeadlineService $deadlines,
        private readonly DocumentDeadlineRecipientResolver $recipients,
    ) {}

    /** @return array{evaluated: int, generated: int, omitted: int} */
    public function process(?CarbonInterface $now = null): array
    {
        $current = $this->deadlines->normalizeNow($now);
        $cutoff = $this->deadlines->dueSoonCutoff($current);
        $documentIds = Document::query()
            ->pendingWithDeadline()
            ->where('due_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');
        $generated = 0;
        $omitted = 0;

        foreach ($documentIds as $documentId) {
            $createdForDocument = $this->processDocument((int) $documentId, $current);

            $generated += $createdForDocument;

            if ($createdForDocument === 0) {
                $omitted++;
            }
        }

        return [
            'evaluated' => $documentIds->count(),
            'generated' => $generated,
            'omitted' => $omitted,
        ];
    }

    public function processDocument(int $documentId, ?CarbonInterface $now = null): int
    {
        $current = $this->deadlines->normalizeNow($now);

        return DB::transaction(function () use ($documentId, $current): int {
            $document = Document::query()->lockForUpdate()->find($documentId);

            if (! $document) {
                return 0;
            }

            $milestone = $this->deadlines->status($document, $current);

            if (! $milestone->requiresReminder()) {
                return 0;
            }

            $created = 0;

            foreach ($this->recipients->resolve($document, $milestone) as $user) {
                $notificationId = $this->notificationId($document, $user, $milestone);

                if ($user->notifications()->whereKey($notificationId)->exists()) {
                    continue;
                }

                $notification = new DocumentDeadlineNotification(
                    $document,
                    $milestone,
                    $current->toIso8601String(),
                );
                $notification->id = $notificationId;
                $user->notify($notification);
                $created++;
            }

            return $created;
        }, 3);
    }

    private function notificationId(
        Document $document,
        User $user,
        DocumentDeadlineStatus $milestone,
    ): string {
        $key = implode('|', [
            'document-deadline',
            $document->public_id,
            $user->public_id,
            $milestone->value,
            $document->due_at === null
                ? null
                : CarbonImmutable::instance($document->due_at)->utc()->toIso8601String(),
        ]);

        return Uuid::uuid5(Uuid::NAMESPACE_URL, $key)->toString();
    }
}
