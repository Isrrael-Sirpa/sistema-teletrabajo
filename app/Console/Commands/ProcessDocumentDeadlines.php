<?php

namespace App\Console\Commands;

use App\Services\DocumentDeadlineReminderService;
use Illuminate\Console\Command;

class ProcessDocumentDeadlines extends Command
{
    protected $signature = 'documents:process-deadlines';

    protected $description = 'Procesa hitos de vencimiento y genera recordatorios internos idempotentes';

    public function handle(DocumentDeadlineReminderService $reminders): int
    {
        $result = $reminders->process();

        $this->components->info('Procesamiento de fechas límite finalizado.');
        $this->line("Documentos evaluados: {$result['evaluated']}");
        $this->line("Recordatorios generados: {$result['generated']}");
        $this->line("Documentos omitidos: {$result['omitted']}");

        return self::SUCCESS;
    }
}
