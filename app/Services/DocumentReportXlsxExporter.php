<?php

namespace App\Services;

use App\Models\Document;
use App\Support\DocumentReportPresentation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class DocumentReportXlsxExporter
{
    private const BLUE = '0B2E59';

    private const DATE_FORMAT = 'dd/mm/yyyy hh:mm';

    /** @var array<string, int> */
    private const WIDTHS = [
        'A' => 28, 'B' => 25, 'C' => 60, 'D' => 36, 'E' => 30, 'F' => 18,
        'G' => 15, 'H' => 21, 'I' => 23, 'J' => 23, 'K' => 25, 'L' => 17,
    ];

    public function __construct(
        private readonly DocumentReportPresentation $presentation,
    ) {}

    /**
     * Format the already-authorized dataset without issuing another query.
     *
     * @param  Collection<int, Document>  $documents
     * @param  array{generated_at: CarbonImmutable, generated_by: string, generated_role: ?string, filters: array<string, string>, total: int}  $metadata
     */
    public function render(Collection $documents, array $metadata): string
    {
        $spreadsheet = new Spreadsheet;
        $stream = null;

        try {
            $spreadsheet->getProperties()
                ->setCreator('Facultad de Tecnología — UMSA')
                ->setTitle('Reporte de documentos')
                ->setSubject('Sistema de Gestión Documental');
            $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Documentos');
            $sheet->setShowGridlines(false);
            $sheet->getSheetView()->setZoomScale(85);

            foreach (self::WIDTHS as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $this->brandHeader($sheet);
            $headerRow = $this->metadata($sheet, $metadata);
            $this->table($sheet, $documents, $headerRow);

            // This resource is request-local; it is closed even if writing fails.
            $stream = fopen('php://temp/maxmemory:16777216', 'w+b');
            if ($stream === false) {
                throw new RuntimeException('No fue posible generar el reporte.');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($stream);
            rewind($stream);
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                throw new RuntimeException('No fue posible generar el reporte.');
            }

            return $contents;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function brandHeader(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:L4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BLUE]],
            'font' => ['color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach ([
            1 => ['UNIVERSIDAD MAYOR DE SAN ANDRÉS', 14, 25],
            2 => ['FACULTAD DE TECNOLOGÍA', 13, 23],
            3 => ['SISTEMA DE GESTIÓN DOCUMENTAL', 11, 23],
            4 => ['REPORTE DE DOCUMENTOS', 17, 30],
        ] as $row => [$title, $size, $height]) {
            $sheet->mergeCells("B{$row}:K{$row}");
            $this->text($sheet, "B{$row}", $title);
            $sheet->getStyle("B{$row}")->getFont()->setSize($size)->setBold($row !== 3);
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
        $sheet->getRowDimension(5)->setRowHeight(12);

        foreach ([
            ['umsa-logo.png', 'Universidad Mayor de San Andrés', 'A1', 60],
            ['ft-logo.png', 'Facultad de Tecnología', 'L1', 64],
        ] as [$filename, $name, $coordinates, $height]) {
            $drawing = new Drawing;
            $drawing->setName($name);
            $drawing->setDescription($name);
            $drawing->setPath(public_path('images/brand/'.$filename));
            $drawing->setResizeProportional(true);
            $drawing->setHeight($height);
            $drawing->setCoordinates($coordinates);
            $drawing->setOffsetX(24);
            $drawing->setOffsetY(30);
            $drawing->setWorksheet($sheet);
        }
    }

    /**
     * @param  array{generated_at: CarbonImmutable, generated_by: string, generated_role: ?string, filters: array<string, string>, total: int}  $metadata
     */
    private function metadata(Worksheet $sheet, array $metadata): int
    {
        $sheet->mergeCells('A6:B6');
        $this->text($sheet, 'A6', 'Fecha de generación:');
        $sheet->mergeCells('C6:F6');
        $this->date($sheet, 'C6', $metadata['generated_at']);
        $sheet->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->mergeCells('G6:H6');
        $this->text($sheet, 'G6', 'Documentos:');
        $sheet->mergeCells('I6:L6');
        $sheet->setCellValueExplicit('I6', $metadata['total'], DataType::TYPE_NUMERIC);
        $sheet->getStyle('I6')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension(6)->setRowHeight(25);

        $sheet->mergeCells('A7:B7');
        $this->text($sheet, 'A7', 'Generado por:');
        $sheet->mergeCells('C7:L7');
        $this->text($sheet, 'C7', $metadata['generated_by']);
        $sheet->getRowDimension(7)->setRowHeight($this->textHeight($metadata['generated_by'], 240));

        $row = 8;
        if ($metadata['generated_role'] !== null && $metadata['generated_role'] !== '') {
            $sheet->mergeCells("A{$row}:B{$row}");
            $this->text($sheet, "A{$row}", 'Rol:');
            $sheet->mergeCells("C{$row}:L{$row}");
            $this->text($sheet, "C{$row}", $metadata['generated_role']);
            $sheet->getRowDimension($row)->setRowHeight($this->textHeight($metadata['generated_role'], 240));
            $row++;
        }

        $sheet->getStyle('A6:L'.($row - 1))->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A6:B'.($row - 1))->getFont()->setBold(true)->getColor()->setRGB(self::BLUE);
        $sheet->getStyle('G6:H6')->getFont()->setBold(true)->getColor()->setRGB(self::BLUE);

        $row++;
        $sheet->mergeCells("A{$row}:L{$row}");
        $this->text($sheet, "A{$row}", 'FILTROS APLICADOS');
        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::BLUE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDF2F7']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row++;

        if ($metadata['filters'] === []) {
            $sheet->mergeCells("A{$row}:L{$row}");
            $this->text($sheet, "A{$row}", 'Todos los documentos autorizados.');
            $sheet->getRowDimension($row)->setRowHeight(25);
            $row++;
        } else {
            foreach ($metadata['filters'] as $label => $value) {
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->mergeCells("C{$row}:L{$row}");
                $this->text($sheet, "A{$row}", $label.':');
                $this->text($sheet, "C{$row}", $value);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:L{$row}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension($row)->setRowHeight(max(
                    $this->textHeight($label.':', 50),
                    $this->textHeight($value, 240),
                ));
                $row++;
            }
        }

        $sheet->getRowDimension($row)->setRowHeight(12);

        return $row + 1;
    }

    /** @param Collection<int, Document> $documents */
    private function table(Worksheet $sheet, Collection $documents, int $headerRow): void
    {
        foreach ($this->presentation->headers() as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $this->text($sheet, $column.$headerRow, $header);
        }

        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BLUE]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C8D3E0']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(34);

        $row = $headerRow + 1;
        foreach ($documents as $document) {
            $height = 30.0;
            foreach ($this->presentation->values($document) as $index => $value) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $cell = $column.$row;

                if ($value instanceof CarbonInterface) {
                    $this->date($sheet, $cell, $value);
                } elseif (is_int($value)) {
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                } else {
                    $this->text($sheet, $cell, (string) $value);
                    $height = max($height, $this->textHeight((string) $value, self::WIDTHS[$column]));
                }
            }
            $sheet->getRowDimension($row)->setRowHeight($height);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow > $headerRow) {
            $firstRow = $headerRow + 1;
            $sheet->getStyle("A{$firstRow}:L{$lastRow}")->applyFromArray([
                'font' => ['color' => ['rgb' => '233247']],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'wrapText' => true,
                ],
                'borders' => [
                    'horizontal' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'DCE3EB']],
                    'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DCE3EB']],
                ],
            ]);
            $sheet->getStyle("L{$firstRow}:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->setAutoFilter("A{$headerRow}:L{$lastRow}");
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setSelectedCell('A'.($headerRow + 1));
    }

    private function text(Worksheet $sheet, string $cell, string $value): void
    {
        // No binder inference: even =, +, -, @ and URLs remain literal text.
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function date(Worksheet $sheet, string $cell, CarbonInterface $value): void
    {
        // Presentation supplies the local wall time; Excel has no timezone.
        $sheet->setCellValueExplicit($cell, Date::dateTimeToExcel($value), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::DATE_FORMAT);
    }

    private function textHeight(string $value, int $width): float
    {
        $capacity = max(1, (int) floor($width * 0.85));
        $lines = 0;
        foreach (preg_split('/\R/u', $value) ?: [''] as $line) {
            $lines += max(1, (int) ceil(mb_strwidth($line, 'UTF-8') / $capacity));
        }

        return min(409.0, max(25.0, $lines * 16.0 + 9.0));
    }
}
