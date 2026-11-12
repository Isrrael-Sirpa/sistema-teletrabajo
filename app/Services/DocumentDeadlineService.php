<?php

namespace App\Services;

use App\Enums\DocumentDeadlineStatus;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DocumentDeadlineService
{
    public function status(Document $document, ?CarbonInterface $now = null): DocumentDeadlineStatus
    {
        if ($document->due_at === null) {
            return DocumentDeadlineStatus::NO_DUE_DATE;
        }

        if (! $document->general_status->hasPendingDeadlineWork()) {
            return DocumentDeadlineStatus::NOT_APPLICABLE;
        }

        return $this->statusForPendingWork($document, $now);
    }

    public function statusForPendingWork(Document $document, ?CarbonInterface $now = null): DocumentDeadlineStatus
    {
        if ($document->due_at === null) {
            return DocumentDeadlineStatus::NO_DUE_DATE;
        }

        $current = $this->normalizeNow($now);
        $dueAt = CarbonImmutable::instance($document->due_at)->setTimezone($this->timezone());

        if ($dueAt->lessThanOrEqualTo($current)) {
            return DocumentDeadlineStatus::OVERDUE;
        }

        if ($dueAt->lessThanOrEqualTo($this->dueSoonCutoff($current))) {
            return DocumentDeadlineStatus::DUE_SOON;
        }

        return DocumentDeadlineStatus::CURRENT;
    }

    public function dueSoonCutoff(?CarbonInterface $now = null): CarbonImmutable
    {
        return $this->normalizeNow($now)
            ->addDays($this->dueSoonDays())
            ->endOfDay();
    }

    public function normalizeNow(?CarbonInterface $now = null): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())
            ->setTimezone($this->timezone());
    }

    public function dueSoonDays(): int
    {
        return max(1, min(30, (int) config('documents.deadlines.due_soon_days', 3)));
    }

    public function timezone(): string
    {
        return (string) config('app.timezone', 'America/La_Paz');
    }
}
