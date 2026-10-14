# Fase 21: Reportes y exportaciones

## Resultado

La Fase 21 incorpora reportes administrativos en `/reportes/documentos` y exportación CSV controlada. El módulo reutiliza `DocumentSearchService`, `Document::visibleTo()` y los scopes de `DocumentDeadlineService`; no existe una consulta paralela capaz de ampliar el acceso documental.

No se inició la Fase 22.

## Reportes disponibles

La pantalla permite consultar:

- resumen por estado, prioridad, tipo documental y unidad de origen;
- documentos enviados y recibidos por período;
- documentos aprobados, devueltos y rechazados;
- documentos vencidos y próximos a vencer;
- detalle paginado del conjunto autorizado.

Los filtros reutilizados de la búsqueda avanzada incluyen estado, prioridad, tipo documental, origen, destino, clasificación, creador, remitente y condición de vencimiento. El reporte seleccionado agrega su semántica específica. `Desde` comienza al inicio del día y `Hasta` utiliza el inicio del día siguiente como límite superior exclusivo, siempre en `America/La_Paz` y sin convertir `TIMESTAMPTZ` a texto en SQL.

## Autorización y ámbitos

Todas las consultas parten de `Document::visibleTo($user)`:

- Administrador y Co-administrador consultan el ámbito institucional global;
- Jefe consulta únicamente su ámbito documental de carrera;
- Secretaria consulta sus documentos y su participación autorizada;
- Auxiliar conserva exactamente su visibilidad documental;
- una delegación efectiva solo agrega las unidades y capacidades explícitas durante su vigencia;
- Extra recibe `403` en consulta y exportación.

Extra tampoco obtiene documentos por pertenecer a una carrera ni por una fila anómala en `document_recipients`. Su única vía de consulta continúa siendo una concesión efectiva en `document_access_grants`, pero esa concesión no habilita el módulo administrativo de reportes.

La navegación muestra “Reportes” solo a roles con permiso. Las rutas aplican autenticación, cuenta activa y permisos en backend; ocultar el enlace no se utiliza como control de seguridad.

## Confidencialidad

Un documento no visible nunca interviene en filas, totales, selectores ni exportaciones, incluso si se manipulan parámetros de la URL. Los resúmenes presentan cantidades y etiquetas administrativas, no asuntos. El detalle y el CSV solo incluyen campos del documento que el actor ya puede consultar legítimamente.

El módulo no consulta `audit_logs` como fuente estadística y no exporta motivos, contenido de archivos, hashes, rutas privadas, IDs internos, eventos ni metadatos técnicos.

## Agregaciones y rendimiento

Estados, prioridades, tipos y unidades se agregan en PostgreSQL mediante `COUNT` y `GROUP BY`. El conjunto autorizado se encapsula como subconsulta de IDs antes de unir catálogos; así se evitan columnas ambiguas y un documento con varios destinatarios se cuenta una sola vez. Los filtros relacionales usan `EXISTS` mediante `whereHas`.

La vista detallada pagina de forma configurable y carga relaciones y conteos por lote. La prueba de consultas confirma que el número de consultas no crece por documento. Los planes `EXPLAIN` de PostgreSQL verifican los índices existentes para estado/prioridad, origen/estado/fecha y destinatario/unidad/estado. No fue necesaria una migración.

## Exportación CSV

La descarga autenticada conserva exactamente el reporte y los filtros activos. Se genera en memoria para la respuesta y no deja archivos permanentes ni rutas reutilizables en almacenamiento.

La lista explícita de columnas es:

1. código;
2. tipo documental;
3. asunto autorizado;
4. unidad de origen;
5. estado;
6. prioridad;
7. clasificación;
8. fecha de envío;
9. fecha límite;
10. condición de vencimiento;
11. remitente;
12. cantidad de destinatarios activos.

El archivo incluye BOM UTF-8 para compatibilidad con Excel. `fputcsv` escapa separadores y comillas. Toda celda cuyo primer contenido significativo sea `=`, `+`, `-` o `@` recibe un apóstrofo protector para impedir CSV injection.

El máximo inicial es `REPORT_EXPORT_MAX_ROWS=5000`. Si el conjunto autorizado supera el límite, no se genera el archivo y se solicita reducir período o filtros. El límite se acota defensivamente entre 1 y 100.000 filas.

XLSX y PDF no se implementaron. CSV no necesita dependencias externas; incorporar esos formatos exigiría presentar y obtener aprobación previa para una biblioteca compatible, tal como establece la decisión de fase.

## Auditoría

Cada exportación completada registra `reports.exported` en `audit_logs` con actor, formato, tipo de reporte, resultado, cantidad de filas, filtros administrativos permitidos y fecha propia del registro. La búsqueda textual libre se excluye de auditoría para evitar copiar asuntos o contenido sensible. No se guarda el archivo ni sus filas.

## Configuración

```dotenv
REPORTS_PER_PAGE=25
REPORT_EXPORT_MAX_ROWS=5000
```

Ambos valores se validan además en ejecución: entre 5 y 100 elementos por página, y entre 1 y 100.000 filas por exportación.

## Archivos principales

- `app/Http/Requests/DocumentReportRequest.php`
- `app/Http/Controllers/DocumentReportController.php`
- `app/Services/DocumentReportService.php`
- `app/Enums/AuditAction.php`
- `app/Support/RbacCatalog.php`
- `app/Support/CareerHeadDelegationCatalog.php`
- `config/reports.php`
- `resources/views/reports/documents.blade.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `routes/web.php`
- `tests/Feature/DocumentReportTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentReportTest.php`

También se reforzó `Document::visibleTo()` para que Extra ignore cualquier participación ordinaria y solo considere concesiones efectivas. La prueba de búsqueda avanzada se adaptó para preservar la cobertura de destinatarios personales con un rol elegible.

## Cobertura y verificación

Las pruebas específicas cubren los 26 escenarios solicitados: acceso global, ámbitos de Jefe/Secretaria/Auxiliar, denegación de Extra, delegación vigente y expirada, confidencialidad, agregados, fechas, vencimientos, múltiples destinatarios, filtros combinados, paginación, CSV, inyección de fórmulas, límite, auditoría segura, manipulación de parámetros, N+1 y PostgreSQL real.

Resultados finales del 12 de octubre de 2026:

- pruebas funcionales específicas: **14 pruebas, 95 aserciones**;
- integración PostgreSQL específica: **2 pruebas, 14 aserciones**;
- suite general: **255 pruebas, 1519 aserciones**;
- suite PostgreSQL completa: **67 pruebas, 275 aserciones**.

También quedaron aprobados Pint, compilación de Blade, las dos rutas del módulo y el build Vite. `php artisan migrate:status` confirmó las 19 migraciones existentes en estado `Ran`. No se creó ni ejecutó ninguna migración y no se utilizó `migrate:fresh` ni `db:wipe`.
