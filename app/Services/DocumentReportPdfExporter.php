<?php

namespace App\Services;

use App\Models\Document;
use App\Support\DocumentReportPresentation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Collection;
use RuntimeException;

class DocumentReportPdfExporter
{
    private const FONT_SIZE = 9.0;

    private const LINES_PER_SECTION = 8;

    /** Text widths in points, with padding allowed for the seven A4-landscape columns. */
    private const COLUMN_WIDTHS = [90.0, 67.0, 198.0, 112.0, 59.0, 75.0, 98.0];

    public function __construct(
        private readonly DocumentReportPresentation $presentation,
    ) {}

    /**
     * Render only the dataset already selected by the authorized report query.
     *
     * @param  Collection<int, Document>  $documents
     * @param  array{generated_at: CarbonImmutable, generated_by: string, generated_role: ?string, filters: array<string, string>, total: int}  $metadata
     */
    public function render(Collection $documents, array $metadata): string
    {
        $options = new Options;
        $brandDirectory = realpath(public_path('images/brand'));
        $fontDirectory = realpath($options->getFontDir());

        if ($brandDirectory === false || $fontDirectory === false) {
            throw new RuntimeException('No fue posible generar el reporte.');
        }

        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setAllowedProtocols(['file://', 'data://']);
        $options->setChroot([$brandDirectory, $fontDirectory]);
        $options->setLogOutputFile('');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');

        try {
            $rows = $this->rows($documents, $dompdf->getFontMetrics());
            $logos = [
                'umsa' => $this->localLogo($brandDirectory.DIRECTORY_SEPARATOR.'umsa-logo.png'),
                'ft' => $this->localLogo($brandDirectory.DIRECTORY_SEPARATOR.'ft-logo.png'),
            ];
            $html = view('reports.pdf', compact('rows', 'metadata', 'logos'))->render();

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();
            $dompdf->getCanvas()->page_script(function (
                int $pageNumber,
                int $pageCount,
                Canvas $canvas,
                FontMetrics $fontMetrics,
            ): void {
                $font = $fontMetrics->getFont('DejaVu Sans');
                $size = 8;
                $text = "Página {$pageNumber} de {$pageCount}";
                $textWidth = $fontMetrics->getTextWidth($text, $font, $size);
                $bottom = $canvas->get_height() - 25;
                $canvas->line(32, $bottom - 7, $canvas->get_width() - 32, $bottom - 7, [0.82, 0.86, 0.90], 0.5);
                $canvas->text(32, $bottom, 'Facultad de Tecnología - UMSA', $font, $size, [0.30, 0.36, 0.43]);
                $canvas->text($canvas->get_width() - 32 - $textWidth, $bottom, $text, $font, $size, [0.30, 0.36, 0.43]);
            });

            $pdf = $dompdf->output(['compress' => true]);
            if (! is_string($pdf)) {
                throw new RuntimeException('No fue posible generar el reporte.');
            }

            return $pdf;
        } finally {
            // No report file, stream or reusable Dompdf instance is retained.
            unset($dompdf);
        }
    }

    /**
     * Dompdf cannot paginate table cells. Bound each visual row, continuing the
     * same document in subsequent rows rather than truncating lengthy values.
     *
     * @param  Collection<int, Document>  $documents
     * @return list<array{cells: list<list<string>>, secondary: list<string>, continuation: bool}>
     */
    private function rows(Collection $documents, FontMetrics $fontMetrics): array
    {
        $rows = [];
        $font = $fontMetrics->getFont('DejaVu Sans');

        foreach ($documents as $document) {
            $values = array_map(
                fn (mixed $value): string => $value instanceof CarbonInterface
                    ? $value->format('d/m/Y H:i')
                    : (string) $value,
                $this->presentation->values($document),
            );
            $columns = [
                $values[0], $values[1], $values[2], $values[3], $values[5], $values[8],
                $values[10]."\nFecha límite: ".$values[9],
            ];
            $lines = [];
            foreach ($columns as $index => $value) {
                $lines[] = $this->wrap($value, self::COLUMN_WIDTHS[$index], $fontMetrics, $font, self::FONT_SIZE);
            }
            $secondary = $this->wrap(
                'Remitente: '.$values[4]."\nPrioridad: ".$values[6]
                    ."\nClasificación: ".$values[7]."\nDestinatarios: ".$values[11],
                self::COLUMN_WIDTHS[2], $fontMetrics, $font, 8.0,
            );
            $sectionCount = (int) ceil(max(array_map('count', [...$lines, $secondary])) / self::LINES_PER_SECTION);

            for ($section = 0; $section < $sectionCount; $section++) {
                $offset = $section * self::LINES_PER_SECTION;
                $cells = array_map(fn (array $cell): array => array_slice($cell, $offset, self::LINES_PER_SECTION), $lines);
                if ($section > 0 && $cells[0] === []) {
                    $cells[0] = [$lines[0][0]];
                }
                $rows[] = [
                    'cells' => $cells,
                    'secondary' => array_slice($secondary, $offset, self::LINES_PER_SECTION),
                    'continuation' => $section > 0,
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function wrap(string $value, float $width, FontMetrics $metrics, string $font, float $fontSize): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', str_replace("\t", '    ', $value)) ?: [''] as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';

                continue;
            }

            while ($paragraph !== '') {
                $length = mb_strlen($paragraph, 'UTF-8');
                $low = 1;
                $high = $length;
                $fit = 1;
                while ($low <= $high) {
                    $middle = (int) floor(($low + $high) / 2);
                    $candidate = mb_substr($paragraph, 0, $middle, 'UTF-8');
                    if ($metrics->getTextWidth($candidate, $font, $fontSize) <= $width) {
                        $fit = $middle;
                        $low = $middle + 1;
                    } else {
                        $high = $middle - 1;
                    }
                }

                if ($fit < $length) {
                    $prefix = mb_substr($paragraph, 0, $fit, 'UTF-8');
                    $lastSpace = mb_strrpos($prefix, ' ', 0, 'UTF-8');
                    $lastHyphen = mb_strrpos($prefix, '-', 0, 'UTF-8');
                    $breakAt = max($lastSpace === false ? -1 : $lastSpace, $lastHyphen === false ? -1 : $lastHyphen);
                    if ($breakAt >= $fit / 2) {
                        $fit = $breakAt + 1;
                    }
                }

                $lines[] = mb_substr($paragraph, 0, $fit, 'UTF-8');
                $paragraph = mb_substr($paragraph, $fit, null, 'UTF-8');
            }
        }

        return $lines;
    }

    private function localLogo(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('No fue posible generar el reporte.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('No fue posible generar el reporte.');
        }

        // Embed only these fixed local PNGs: no remote URLs and no Windows
        // file-URI ambiguity. Blade never accepts user-provided image markup.
        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
