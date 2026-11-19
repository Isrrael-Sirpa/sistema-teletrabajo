<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
@page { margin: 1.25cm 1cm 1.2cm; }
body { font-family: "Times New Roman", Times, serif; font-size: 8.5pt; color: #111827; }
.institution { text-align: center; font-size: 9pt; font-weight: bold; margin: 0; }
h1 { margin: 8px 0 3px; text-align: center; font-size: 15pt; text-transform: uppercase; }
.period { margin: 0; text-align: center; font-size: 10pt; font-weight: bold; }
.metadata { margin: 12px 0 9px; border-top: 1px solid #334155; border-bottom: 1px solid #334155; padding: 5px 0; font-size: 8pt; }
.metadata span:last-child { float: right; } table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #475569; padding: 5px 4px; vertical-align: top; } th { background: #dbe4ee; font-size: 7.5pt; text-align: center; font-weight: bold; }
td { line-height: 1.25; } tbody tr:nth-child(even) { background: #f8fafc; } .muted { color: #475569; font-size: 7.5pt; }
</style></head><body>
<p class="institution">UNIVERSIDAD MAYOR DE SAN ANDRÉS</p>
<p class="institution">FACULTAD DE TECNOLOGÍA</p>
<h1>Libro de correspondencia</h1>
<p class="period">Gestión {{ $year }}{{ $month ? ' · Mes '.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '' }}</p>
<p class="metadata"><span>{{ $selectedUnit?->name ?? 'Facultad de Tecnología' }}</span><span>Emitido: {{ now()->format('d/m/Y H:i') }} · {{ $entries->count() }} registros</span></p>
<table><thead><tr><th>N°</th><th>CITE</th><th>Referencia</th><th>Remitente / procedencia</th><th>Fecha de envío</th><th>Destinatario</th><th>Fecha de recepción</th><th>Observaciones</th></tr></thead><tbody>
@forelse($entries as $entry) @php($document=$entry->document)
<tr><td>{{ $loop->iteration }}</td><td>{{ $document->code }}</td><td>{{ $document->subject }}<br><span class="muted">{{ $document->documentType->name }}</span></td><td>{{ $document->sender?->name }}<br><span class="muted">{{ $document->originUnit->name }}</span></td><td>{{ $document->sent_at->format('d/m/Y H:i') }}</td><td>{{ $entry->user?->name ?? $entry->unit->name }}</td><td>{{ $entry->received_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</td><td></td></tr>
@empty <tr><td colspan="8">No existen registros para los filtros seleccionados.</td></tr> @endforelse
</tbody></table></body></html>
