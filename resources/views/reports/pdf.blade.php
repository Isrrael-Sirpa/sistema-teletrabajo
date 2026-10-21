<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de documentos</title>
    <style>
        @page { size: A4 landscape; margin: 30pt 32pt 46pt; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.3; color: #233247; }
        .brand { width: 100%; border-collapse: collapse; background: #0b2e59; color: #ffffff; margin-bottom: 13pt; }
        .brand td { border: 0; padding: 11pt 13pt; vertical-align: middle; }
        .brand .logo { width: 65pt; text-align: center; }
        .brand .identity { text-align: center; }
        .logo-umsa { width: 45pt; height: 45pt; }
        .logo-ft { width: 48pt; height: 48pt; }
        .university { margin: 0 0 2pt; font-size: 12pt; font-weight: bold; }
        .faculty { margin: 0 0 2pt; font-size: 11pt; font-weight: bold; }
        .system { margin: 0 0 5pt; font-size: 9pt; }
        h1 { margin: 0; font-size: 15pt; line-height: 1.2; }
        .metadata { margin-bottom: 10pt; }
        .metadata p { margin: 0 0 3pt; word-wrap: break-word; }
        .label { font-weight: bold; color: #0b2e59; }
        h2 { margin: 11pt 0 6pt; padding: 5pt 7pt; background: #edf2f7; color: #0b2e59; font-size: 9pt; }
        .filters { margin-bottom: 12pt; }
        .filters p { margin: 0 0 3pt; word-wrap: break-word; }
        .records { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .records thead { display: table-header-group; }
        .records tbody { display: table-row-group; }
        .records tr { page-break-inside: avoid; }
        .records th { padding: 7pt 4pt; text-align: left; vertical-align: middle; background: #0b2e59; color: #ffffff; font-size: 9pt; font-weight: bold; border-bottom: 1pt solid #c8d3e0; }
        .records td { padding: 7pt 4pt; text-align: left; vertical-align: top; border-bottom: 0.5pt solid #dce3eb; }
        .records .line { line-height: 11.8pt; min-height: 11.8pt; white-space: pre-wrap; word-wrap: break-word; }
        .secondary { margin-top: 5pt; color: #566579; font-size: 8pt; }
        .secondary .line { line-height: 10.5pt; min-height: 10.5pt; }
        .continuation td { border-top: 0.5pt dotted #dce3eb; }
        .continuation-label { margin-top: 3pt; color: #566579; font-size: 8pt; }
        .empty { padding: 16pt 7pt; color: #566579; }
    </style>
</head>
<body>
    <table class="brand" role="presentation">
        <tr>
            <td class="logo"><img class="logo-umsa" src="{{ $logos['umsa'] }}" alt="UMSA"></td>
            <td class="identity">
                <p class="university">UNIVERSIDAD MAYOR DE SAN ANDRÉS</p>
                <p class="faculty">FACULTAD DE TECNOLOGÍA</p>
                <p class="system">Sistema de Gestión Documental</p>
                <h1>REPORTE DE DOCUMENTOS</h1>
            </td>
            <td class="logo"><img class="logo-ft" src="{{ $logos['ft'] }}" alt="Facultad de Tecnología"></td>
        </tr>
    </table>

    <section class="metadata">
        <p><span class="label">Fecha de generación:</span> {{ $metadata['generated_at']->format('d/m/Y H:i') }} (America/La_Paz)</p>
        <p><span class="label">Generado por:</span> {{ $metadata['generated_by'] }}</p>
        @if ($metadata['generated_role'] !== null && $metadata['generated_role'] !== '')
            <p><span class="label">Rol:</span> {{ $metadata['generated_role'] }}</p>
        @endif
        <p><span class="label">Cantidad de documentos:</span> {{ $metadata['total'] }}</p>
    </section>

    <h2>FILTROS APLICADOS</h2>
    <section class="filters">
        @forelse ($metadata['filters'] as $label => $value)
            <p><span class="label">{{ $label }}:</span> {{ $value }}</p>
        @empty
            <p>Todos los documentos autorizados.</p>
        @endforelse
    </section>

    <table class="records">
        <thead>
            <tr>
                <th style="width: 13%;">Código</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 27%;">Asunto</th>
                <th style="width: 16%;">Origen</th>
                <th style="width: 9%;">Estado</th>
                <th style="width: 11%;">Fecha de envío</th>
                <th style="width: 14%;">Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class(['continuation' => $row['continuation']])>
                    @foreach ($row['cells'] as $index => $lines)
                        <td>
                            @foreach ($lines as $line)
                                <div class="line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
                            @endforeach
                            @if ($index === 0 && $row['continuation'])
                                <div class="continuation-label">(continuación)</div>
                            @endif
                            @if ($index === 2 && $row['secondary'] !== [])
                                <div class="secondary">
                                    @foreach ($row['secondary'] as $line)
                                        <div class="line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No se encontraron documentos con los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
