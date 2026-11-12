<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentReportPdfExporter;
use App\Services\DocumentReportService;
use App\Services\DocumentReportXlsxExporter;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DocumentReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-21 11:20:00');
        $this->seed([AuthorizationSeeder::class, DocumentTypeSeeder::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_xlsx_opens_with_official_logos_same_csv_columns_metadata_filters_and_readable_formatting(): void
    {
        [$admin, $career, $creator] = $this->context();
        $admin->forceFill(['name' => 'Ana María Pérez'])->save();
        $sent = $this->document($creator, $career, 'Adquisición de equipos para el área técnica');
        $sent->forceFill([
            'sent_at' => CarbonImmutable::parse('2026-10-21 10:18:00', 'America/La_Paz'),
            'due_at' => CarbonImmutable::parse('2026-10-31 23:59:00', 'America/La_Paz'),
        ])->save();
        $this->recipient($sent, $career);
        $draft = $this->document($creator, $career, 'Borrador pendiente', DocumentStatus::DRAFT);
        $filters = [
            'origin_unit' => $career->public_id,
            'period_from' => '2026-10-01', 'period_to' => '2026-10-31',
        ];
        $csv = $this->actingAs($admin)->get(route('reports.documents.csv', $filters));
        $csv->assertOk();
        $csvHeaders = $this->csvRows($csv->streamedContent())[0];

        $response = $this->actingAs($admin)->get(route('reports.documents.xlsx', $filters));
        $response->assertOk()
            ->assertDownload('reporte-documentos-20261021-112000.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->inspectWorkbook($response->streamedContent(), function (Spreadsheet $workbook, string $path) use ($admin, $career, $sent, $draft, $csvHeaders): void {
            $this->assertSame(['Documentos'], $workbook->getSheetNames());
            $sheet = $workbook->getActiveSheet();
            $header = $this->headerRow($sheet);
            $this->assertSame($csvHeaders, $sheet->rangeToArray('A'.$header.':L'.$header, null, false, false)[0]);
            $this->assertSame('L', $sheet->getHighestDataColumn());
            $this->assertCount(2, $sheet->getDrawingCollection());
            $this->assertOriginalLocalLogosEmbedded($path);

            $text = $this->sheetText($sheet);
            $this->assertStringContainsString('UNIVERSIDAD MAYOR DE SAN ANDRÉS', mb_strtoupper($text));
            $this->assertStringContainsString('FACULTAD DE TECNOLOGÍA', mb_strtoupper($text));
            $this->assertStringContainsString('REPORTE DE DOCUMENTOS', mb_strtoupper($text));
            $this->assertStringContainsString($admin->name, $text);
            $this->assertStringContainsString('21/10/2026 11:20', $text);
            $this->assertStringContainsString($career->name, $text);
            $this->assertStringContainsString('01/10/2026', $text);
            $this->assertStringContainsString('31/10/2026', $text);
            $this->assertStringNotContainsString($career->public_id, $text);
            $this->assertStringNotContainsString($sent->public_id, $text);

            $this->assertSame($header + 2, $sheet->getHighestDataRow());
            $this->assertSame('A'.$header.':L'.($header + 2), $sheet->getAutoFilter()->getRange());
            $this->assertMatchesRegularExpression('/^[A-Z]+'.($header + 1).'$/', $sheet->getFreezePane());
            $this->assertTrue($sheet->getStyle('A'.$header)->getFont()->getBold());
            $this->assertTrue($sheet->getStyle('C'.($header + 1))->getAlignment()->getWrapText());
            $this->assertGreaterThan(0, $sheet->getColumnDimension('C')->getWidth());

            $sentRow = $this->subjectRow($sheet, $sent->subject);
            $this->assertSame($sent->code, $sheet->getCell('A'.$sentRow)->getValue());
            $this->assertEquals(1, $sheet->getCell('L'.$sentRow)->getValue());
            foreach (['I' => '21/10/2026 10:18', 'J' => '31/10/2026 23:59'] as $column => $expected) {
                $value = $sheet->getCell($column.$sentRow)->getValue();
                $this->assertTrue(is_numeric($value));
                $this->assertSame('dd/mm/yyyy hh:mm', strtolower($sheet->getStyle($column.$sentRow)->getNumberFormat()->getFormatCode()));
                $this->assertSame($expected, Date::excelToDateTimeObject((float) $value)->format('d/m/Y H:i'));
            }
            $draftRow = $this->subjectRow($sheet, $draft->subject);
            $this->assertSame('—', $sheet->getCell('A'.$draftRow)->getValue());
            $this->assertSame('Borrador', $sheet->getCell('F'.$draftRow)->getValue());
            $this->assertSame('—', $sheet->getCell('I'.$draftRow)->getValue());
            $this->assertSame('—', $sheet->getCell('J'.$draftRow)->getValue());
        });
        $this->assertNull($draft->fresh()->code);
        $this->assertSame('21/10/2026 10:18', $sent->fresh()->sent_at->timezone('America/La_Paz')->format('d/m/Y H:i'));
    }

    public function test_xlsx_keeps_formula_like_subjects_as_literal_strings(): void
    {
        [$admin, $career, $creator] = $this->context();
        $subjects = ['=SUM(1,1)', '+SUM(1,1)', '-SUM(1,1)', '@SUM(1,1)', '  =HYPERLINK("https://example.invalid")'];
        foreach ($subjects as $subject) {
            $this->document($creator, $career, $subject);
        }

        $response = $this->actingAs($admin)->get(route('reports.documents.xlsx'));
        $response->assertOk();
        $this->inspectWorkbook($response->streamedContent(), function (Spreadsheet $workbook) use ($subjects): void {
            $sheet = $workbook->getActiveSheet();
            foreach ($subjects as $subject) {
                $cell = $sheet->getCell('C'.$this->subjectRow($sheet, $subject));
                $this->assertSame($subject, $cell->getValue());
                $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            }
        });
    }

    public function test_xlsx_reuses_csv_filters_and_does_not_reveal_invisible_documents(): void
    {
        $career = $this->career('Mecánica Industrial', 'MI');
        $foreign = $this->career('Química Industrial', 'QI');
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $foreignCreator = $this->user(RoleName::SECRETARY, $foreign);
        $visible = $this->document($creator, $career, 'Reporte urgente autorizado', priority: DocumentPriority::URGENT);
        $filtered = $this->document($creator, $career, 'No coincide con prioridad');
        $hidden = $this->document($foreignCreator, $foreign, 'Confidencial ajeno no visible', priority: DocumentPriority::URGENT);
        $filters = ['priority' => DocumentPriority::URGENT->value];

        $csv = $this->actingAs($head)->get(route('reports.documents.csv', $filters));
        $csv->assertOk();
        $csvRows = $this->csvRows($csv->streamedContent());
        $this->assertCount(2, $csvRows);
        $this->assertSame($visible->code, $csvRows[1][0]);

        $response = $this->actingAs($head)->get(route('reports.documents.xlsx', $filters));
        $response->assertOk();
        $this->inspectWorkbook($response->streamedContent(), function (Spreadsheet $workbook) use ($visible, $filtered, $hidden, $csvRows): void {
            $sheet = $workbook->getActiveSheet();
            $header = $this->headerRow($sheet);
            $this->assertSame($header + 1, $sheet->getHighestDataRow());
            $this->assertSame($csvRows[1][0], $sheet->getCell('A'.($header + 1))->getValue());
            $text = $this->sheetText($sheet);
            $this->assertStringContainsString($visible->subject, $text);
            $this->assertStringNotContainsString($filtered->subject, $text);
            $this->assertStringNotContainsString($hidden->subject, $text);
            $this->assertStringNotContainsString($hidden->code, $text);
        });
    }

    public function test_extra_and_user_without_export_permission_receive_403(): void
    {
        $career = $this->career('Aeronáutica', 'AERO');
        $extra = $this->user(RoleName::EXTRA, $career);
        $readOnly = User::factory()->create();
        $readOnly->givePermissionTo(PermissionName::REPORTS_VIEW_GLOBAL->value);

        foreach ([$extra, $readOnly] as $user) {
            foreach (['csv', 'xlsx'] as $format) {
                $this->actingAs($user)->get(route('reports.documents.'.$format))->assertForbidden();
            }
        }
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    public function test_xlsx_limit_is_checked_before_the_renderer_runs(): void
    {
        config(['reports.export_max_rows' => 1]);
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Primero');
        $this->document($creator, $career, 'Segundo');
        $this->mock(DocumentReportXlsxExporter::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('render');
        });

        $this->actingAs($admin)->get(route('reports.documents.xlsx'))
            ->assertRedirect()->assertSessionHasErrors('export');
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    public function test_xlsx_audit_records_format_and_count_without_report_content_or_search_text(): void
    {
        [$admin, $career, $creator] = $this->context();
        $document = $this->document($creator, $career, 'Búsqueda sensible que no debe auditarse');

        $this->actingAs($admin)->get(route('reports.documents.xlsx', [
            'q' => $document->subject, 'priority' => DocumentPriority::NORMAL->value,
        ]))->assertOk();

        $log = AuditLog::query()->where('action', AuditAction::REPORT_EXPORTED->value)->sole();
        $encoded = json_encode($log->new_values, JSON_THROW_ON_ERROR);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('xlsx', $log->new_values['format']);
        $this->assertSame('success', $log->new_values['result']);
        $this->assertSame(1, $log->new_values['row_count']);
        $this->assertSame('normal', $log->new_values['filters']['priority']);
        $this->assertArrayNotHasKey('q', $log->new_values['filters']);
        $this->assertStringNotContainsString($document->subject, $encoded);
        $this->assertStringNotContainsString($document->code, $encoded);
    }

    public function test_renderer_errors_show_generic_message_without_sql_paths_or_success_audit(): void
    {
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Documento autorizado');
        $this->mock(DocumentReportXlsxExporter::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->once()->andThrow(new RuntimeException('SQLSTATE[XXXXX] C:\\private\\exports\\report.xlsx'));
        });

        $this->actingAs($admin)->get(route('reports.documents.xlsx'))
            ->assertRedirect(route('reports.documents.index'))
            ->assertSessionHasErrors(['export' => 'No fue posible generar el reporte. Intente nuevamente.']);
        // Separate test requests do not automatically retain the browser's session cookie.
        $index = $this->withCookie(session()->getName(), session()->getId())
            ->get(route('reports.documents.index'));
        $index
            ->assertOk()
            ->assertDontSee('SQLSTATE')->assertDontSee('C:\\private\\exports');
        $this->assertTrue(
            str_contains($index->getContent(), 'No fue posible generar el reporte. Intente nuevamente.'),
            'La página de reportes debe mostrar el error de exportación tras la redirección.',
        );
        $this->assertFalse(AuditLog::query()->where('action', AuditAction::REPORT_EXPORTED->value)->get()
            ->contains(fn (AuditLog $log): bool => ($log->new_values['result'] ?? null) === 'success'));
    }

    public function test_pdf_download_is_a_real_pdf_with_administrative_filename_and_content_type(): void
    {
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Reporte institucional con acentos: á é í ó ú ñ');
        $draft = $this->document($creator, $career, 'Borrador sin código oficial', DocumentStatus::DRAFT);

        $response = $this->actingAs($admin)->get(route('reports.documents.pdf'));
        $response->assertOk()
            ->assertDownload('reporte-documentos-20261021-112000.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(1_000, strlen($content));
        $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Subtype\s*\/Image\b/', $content), 'El PDF debe embeber ambos logos oficiales.');
        $this->assertNull($draft->fresh()->code);
    }

    public function test_pdf_uses_the_same_authorized_filtered_dataset_as_csv_and_xlsx(): void
    {
        $career = $this->career('Construcciones Civiles', 'CC');
        $foreign = $this->career('Electricidad Industrial', 'EI');
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $foreignCreator = $this->user(RoleName::SECRETARY, $foreign);
        $visible = $this->document($creator, $career, 'Único urgente autorizado', priority: DocumentPriority::URGENT);
        $this->document($creator, $career, 'Excluido por prioridad');
        $this->document($foreignCreator, $foreign, 'Ajeno que nunca debe exportarse', priority: DocumentPriority::URGENT);
        $filters = ['priority' => DocumentPriority::URGENT->value];

        $csv = $this->actingAs($head)->get(route('reports.documents.csv', $filters));
        $csv->assertOk();
        $csvRows = $this->csvRows($csv->streamedContent());
        $this->assertCount(2, $csvRows);
        $this->assertSame($visible->code, $csvRows[1][0]);

        $xlsx = $this->actingAs($head)->get(route('reports.documents.xlsx', $filters));
        $xlsx->assertOk();
        $this->inspectWorkbook($xlsx->streamedContent(), function (Spreadsheet $workbook) use ($csvRows): void {
            $sheet = $workbook->getActiveSheet();
            $header = $this->headerRow($sheet);
            $this->assertSame($header + 1, $sheet->getHighestDataRow());
            $this->assertSame($csvRows[1][0], $sheet->getCell('A'.($header + 1))->getValue());
        });

        $this->mock(DocumentReportPdfExporter::class, function (MockInterface $mock) use ($visible, $head, $csvRows): void {
            $mock->shouldReceive('render')->once()->andReturnUsing(function (Collection $documents, array $metadata) use ($visible, $head, $csvRows): string {
                $this->assertSame([$visible->id], $documents->pluck('id')->all());
                $this->assertSame([$csvRows[1][0]], $documents->pluck('code')->all());
                $this->assertSame(1, $metadata['total']);
                $this->assertSame($head->name, $metadata['generated_by']);
                $this->assertSame(['Prioridad' => 'Urgente'], $metadata['filters']);

                return "%PDF-1.4\n% Focal renderer fixture\n%%EOF";
            });
        });
        $this->actingAs($head)->get(route('reports.documents.pdf', $filters))
            ->assertOk()->assertDownload('reporte-documentos-20261021-112000.pdf');
    }

    public function test_pdf_template_escapes_document_content_and_omits_internal_identifiers_and_paths(): void
    {
        [$admin, $career, $creator] = $this->context();
        $subject = '<img src="file:///secret"> & <script>x</script>';
        $document = $this->document($creator, $career, $subject);
        $viewData = null;
        View::composer('reports.pdf', function (ViewInstance $view) use (&$viewData): void {
            $viewData = $view->getData();
        });

        $response = $this->actingAs($admin)->get(route('reports.documents.pdf', [
            'origin_unit' => $career->public_id,
        ]));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertIsArray($viewData);
        foreach (['umsa' => 'umsa-logo.png', 'ft' => 'ft-logo.png'] as $key => $file) {
            $this->assertSame('data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/'.$file))), $viewData['logos'][$key]);
        }
        $html = view('reports.pdf', $viewData)->render();
        $this->assertStringNotContainsString($subject, $html);
        $this->assertStringNotContainsString($document->public_id, $html);
        $this->assertStringNotContainsString($career->public_id, $html);

        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $this->assertSame(7, $xpath->query('//thead/tr/th')->length);
        $this->assertSame(0, $xpath->query('//img[@src="file:///secret"]')->length);
        $this->assertSame(0, $xpath->query('//script')->length);
        $subjectLines = $xpath->query('//table[@class="records"]/tbody/tr[1]/td[3]/div[@class="line"]');
        $reconstructedSubject = '';
        foreach ($subjectLines as $line) {
            $this->assertStringContainsString(e($line->textContent), $html);
            $reconstructedSubject .= $line->textContent;
        }
        $this->assertSame($subject, $reconstructedSubject);
        $body = $dom->getElementsByTagName('body')->item(0)->textContent;
        $this->assertStringNotContainsString(base_path(), $body);
        $this->assertStringNotContainsString(storage_path(), $body);
        $this->assertStringContainsString($career->name, $body);
    }

    public function test_pdf_requires_authentication_and_rejects_extra_and_users_without_export_permission(): void
    {
        $this->get(route('reports.documents.pdf'))->assertRedirect(route('login'));
        $career = $this->career('Geodesia, Topografía y Geomática', 'GTG');
        $extra = $this->user(RoleName::EXTRA, $career);
        $readOnly = User::factory()->create();
        $readOnly->givePermissionTo(PermissionName::REPORTS_VIEW_GLOBAL->value);

        foreach ([$extra, $readOnly] as $user) {
            $this->actingAs($user)->get(route('reports.documents.pdf'))->assertForbidden();
        }
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    public function test_pdf_limit_is_checked_before_the_renderer_runs(): void
    {
        config(['reports.export_max_rows' => 1]);
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Primero');
        $this->document($creator, $career, 'Segundo');
        $this->mock(DocumentReportPdfExporter::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('render');
        });

        $this->actingAs($admin)->get(route('reports.documents.pdf'))
            ->assertRedirect()->assertSessionHasErrors('export');
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    public function test_pdf_audit_records_format_and_count_without_report_content_or_search_text(): void
    {
        [$admin, $career, $creator] = $this->context();
        $document = $this->document($creator, $career, 'Contenido privado que no debe quedar en auditoría');
        $this->mock(DocumentReportPdfExporter::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->once()->andReturn("%PDF-1.4\n% Audit fixture\n%%EOF");
        });

        $this->actingAs($admin)->get(route('reports.documents.pdf', [
            'q' => $document->subject, 'priority' => DocumentPriority::NORMAL->value,
        ]))->assertOk();

        $log = AuditLog::query()->where('action', AuditAction::REPORT_EXPORTED->value)->sole();
        $encoded = json_encode($log->new_values, JSON_THROW_ON_ERROR);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('pdf', $log->new_values['format']);
        $this->assertSame('success', $log->new_values['result']);
        $this->assertSame(1, $log->new_values['row_count']);
        $this->assertSame('normal', $log->new_values['filters']['priority']);
        $this->assertArrayNotHasKey('q', $log->new_values['filters']);
        $this->assertStringNotContainsString($document->subject, $encoded);
        $this->assertStringNotContainsString($document->code, $encoded);
    }

    public function test_pdf_renderer_errors_show_generic_feedback_without_technical_details_or_success_audit(): void
    {
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Documento autorizado');
        $this->mock(DocumentReportPdfExporter::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->once()->andThrow(new RuntimeException('SQLSTATE[XXXXX] C:\\private\\exports\\report.pdf'));
        });

        $this->actingAs($admin)->get(route('reports.documents.pdf'))
            ->assertRedirect(route('reports.documents.index'))
            ->assertSessionHasErrors(['export' => 'No fue posible generar el reporte. Intente nuevamente.']);
        $index = $this->withCookie(session()->getName(), session()->getId())
            ->get(route('reports.documents.index'));
        $index->assertOk()->assertDontSee('SQLSTATE')->assertDontSee('C:\\private\\exports');
        $this->assertTrue(
            str_contains($index->getContent(), 'No fue posible generar el reporte. Intente nuevamente.'),
            'La página de reportes debe mostrar el error de exportación PDF tras la redirección.',
        );
        $this->assertFalse(AuditLog::query()->where('action', AuditAction::REPORT_EXPORTED->value)->get()
            ->contains(fn (AuditLog $log): bool => ($log->new_values['result'] ?? null) === 'success'));
    }

    public function test_export_limit_rechecks_the_actual_dataset_before_csv_or_renderers_and_audit(): void
    {
        config(['reports.export_max_rows' => 1]);
        [$admin, $career, $creator] = $this->context();
        $document = $this->document($creator, $career, 'Único documento del conteo inicial');
        $this->assertDatabaseCount('documentos', 1);
        $reports = \Mockery::mock(app(DocumentReportService::class))->makePartial();
        $reports->shouldReceive('exportDocuments')->times(3)
            ->andReturn(collect([$document, $document->replicate()]));
        $reports->shouldNotReceive('csvDocuments');
        $this->instance(DocumentReportService::class, $reports);
        foreach ([DocumentReportXlsxExporter::class, DocumentReportPdfExporter::class] as $renderer) {
            $this->mock($renderer, function (MockInterface $mock): void {
                $mock->shouldNotReceive('render');
            });
        }

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->actingAs($admin)->get(route('reports.documents.'.$format))
                ->assertRedirect()->assertSessionHasErrors([
                    'export' => 'La exportación contiene 2 filas. Reduzca el período o aplique filtros para no superar 1.',
                ]);
        }
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    /** @return array{User, OrganizationalUnit, User} */
    private function context(): array
    {
        $career = $this->career('Electromecánica', 'EM');

        return [$this->user(RoleName::ADMINISTRATOR), $career, $this->user(RoleName::SECRETARY, $career)];
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'type' => 'career', 'name' => $name.' '.Str::random(5),
            'acronym' => $acronym.Str::upper(Str::random(3)), 'is_active' => true,
        ]);
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function document(
        User $creator,
        OrganizationalUnit $origin,
        string $subject,
        DocumentStatus $status = DocumentStatus::SENT,
        DocumentPriority $priority = DocumentPriority::NORMAL,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id, 'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null, 'subject' => $subject,
            'general_status' => $status, 'priority' => $priority,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'code' => $official ? 'FTEC-EXP-2026-'.Str::upper(Str::random(6)) : null,
            'lock_version' => $official ? 1 : 0,
        ]);
        if ($official) {
            $document->forceFill(['sent_at' => now()->subHour()])->save();
        }

        return $document;
    }

    private function recipient(Document $document, OrganizationalUnit $unit): void
    {
        DocumentRecipient::query()->create([
            'document_id' => $document->id, 'unit_id' => $unit->id,
            'requires_response' => true, 'status' => DocumentRecipientStatus::SENT, 'lock_version' => 1,
        ]);
    }

    private function inspectWorkbook(string $content, callable $inspect): void
    {
        $path = tempnam(sys_get_temp_dir(), 'report-test-');
        $this->assertNotFalse($path);
        $workbook = null;

        try {
            $this->assertNotFalse(file_put_contents($path, $content));
            $workbook = IOFactory::load($path);
            $inspect($workbook, $path);
        } finally {
            $workbook?->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function headerRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            if ($sheet->getCell('A'.$row)->getValue() === 'Código') {
                return $row;
            }
        }

        $this->fail('El archivo XLSX no contiene la fila de encabezados documentales.');
    }

    private function subjectRow(Worksheet $sheet, string $subject): int
    {
        for ($row = $this->headerRow($sheet) + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            if ($sheet->getCell('C'.$row)->getValue() === $subject) {
                return $row;
            }
        }

        $this->fail('El documento esperado no está presente en el XLSX.');
    }

    private function sheetText(Worksheet $sheet): string
    {
        return implode("\n", array_map(
            fn (array $row): string => implode(' ', array_map(fn (mixed $value): string => (string) $value, $row)),
            $sheet->toArray(null, false, true),
        ));
    }

    private function assertOriginalLocalLogosEmbedded(string $path): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        try {
            $embedded = [];
            for ($entry = 0; $entry < $zip->numFiles; $entry++) {
                if (preg_match('#^xl/media/[^/]+\.png$#', $zip->getNameIndex($entry)) === 1) {
                    $embedded[] = hash('sha256', $zip->getFromIndex($entry));
                }
            }
            $this->assertEqualsCanonicalizing([
                hash_file('sha256', public_path('images/brand/umsa-logo.png')),
                hash_file('sha256', public_path('images/brand/ft-logo.png')),
            ], $embedded);
        } finally {
            $zip->close();
        }
    }

    /** @return list<array<int, string|null>> */
    private function csvRows(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, substr($content, 3));
            rewind($stream);
            $rows = [];
            while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
