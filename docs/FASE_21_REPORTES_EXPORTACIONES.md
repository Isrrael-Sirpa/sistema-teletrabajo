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

1. Código;
2. Tipo de documento;
3. Asunto;
4. Unidad de origen;
5. Remitente;
6. Estado;
7. Prioridad;
8. Clasificación;
9. Fecha de envío;
10. Fecha límite;
11. Situación del plazo;
12. Destinatarios (cantidad numérica de destinatarios activos).

El archivo incluye BOM UTF-8 para compatibilidad con Excel. Usa `;` como delimitador, acorde con la configuración regional local `es-BO` (separador de listas `;` y decimal `,`), en lugar de la coma predeterminada de PHP que hacía que Excel abriera cada fila en una sola columna. `fputcsv` recibe delimitador, comillas, escape vacío y fin de línea CRLF explícitos; conserva separadores, comillas dobles, saltos de línea y acentos dentro de los campos. No se agrega una fila no estándar `sep=`. Toda celda cuyo primer contenido significativo sea `=`, `+`, `-` o `@` recibe un apóstrofo protector para impedir CSV injection.

El máximo inicial es `REPORT_EXPORT_MAX_ROWS=5000`. Si el conjunto autorizado supera el límite, no se genera el archivo y se solicita reducir período o filtros. El límite se acota defensivamente entre 1 y 100.000 filas.

CSV se conserva como intercambio de datos. La ampliación autorizada del 21 de octubre de 2026 agrega XLSX para trabajo administrativo editable y PDF para impresión, en tres etapas secuenciales verificadas. No modifica las reglas documentales ni agrega migraciones.

## Ajuste de presentación — 21 de octubre de 2026

La mejora conserva las consultas, filtros, agregaciones, `visibleTo`, permisos y límite de `DocumentReportService`. El controlador comparte el flujo de exportación entre formatos; las nuevas rutas utilizan las mismas protecciones. No cambia la autorización documental ni la mitigación de fórmulas del CSV.

- Fechas CSV/web: `dd/mm/aaaa hh:mm`, en `America/La_Paz`, sin actualizar valores almacenados.
- Código, remitente y fecha de envío ausentes: `—`. No se asignan códigos a borradores. Una fecha límite configurada en un borrador se sigue mostrando.
- La fecha límite ausente se representa como `—`, conservando la distinción semántica de la columna Situación del plazo: `Sin fecha límite`, `No aplicable`, `Vigente`, `Próximo a vencer` o `Vencido`.
- Los asuntos, nombres y su capitalización se conservan tal cual; únicamente continúa aplicándose el escape de seguridad del CSV.
- La pantalla presenta Buscar, Estado, Carrera/unidad de origen y Tipo documental como filtros principales, y el resto de criterios existentes en «Más filtros». Presenta los filtros aplicados con etiquetas administrativas y fechas legibles. Los filtros de URL que no tienen un control visible se conservan al aplicar el formulario; no se amplía su validación ni significado.
- Resultados encontrados, Enviado, Borrador y Anulado reutilizan el total paginado y las agregaciones completas ya disponibles; no cuentan solo las filas de la página ni agregan consultas.
- El desglose existente permanece plegable. La tabla prioriza código, asunto, origen, estado, fecha y plazo, conservando la presentación responsive y los badges actuales.
- La zona «Exportar resultados» ofrece CSV, Excel y PDF con propósitos diferenciados y botones apilables en móvil. Todos exportan el conjunto filtrado completo, no solo la página. El primer clic mantiene la descarga nativa; una protección local de cuatro segundos por botón evita clics repetidos y muestra `Preparando archivo…`, sin afirmar que el servidor terminó.

### XLSX institucional

Se utiliza `phpoffice/phpspreadsheet:5.10.0` directamente. La hoja única «Documentos» incorpora los dos logos PNG originales de `public/images/brand`, cabecera UMSA/FT, fecha de generación, usuario/rol, cantidad y filtros. La tabla conserva las 12 columnas del CSV con cabecera azul, tipografía legible, ajuste de textos, alturas/anchos controlados, autofiltro y encabezados inmovilizados.

Los conteos y fechas se escriben como valores numéricos Excel con formato `dd/mm/yyyy hh:mm`. Todos los textos, incluidos asuntos, filtros y usuario generador, se escriben con tipo `TYPE_STRING`: no se ejecutan fórmulas ni se infieren enlaces desde contenido aportado por usuarios. Los borradores conservan `—` en código y fecha de envío.

### PDF institucional

Se utiliza `dompdf/dompdf:3.1.6` directamente, con una vista Blade de impresión independiente. A4 horizontal, tipografía DejaVu Sans, siete columnas prioritarias, datos secundarios agrupados, filtros previos a la tabla, logos locales y pie «Página X de Y». La cabecera de tabla se repite. Los textos largos se distribuyen en continuaciones medidas, sin truncar su contenido, para evitar la limitación de Dompdf que impide partir una celda entre páginas.

Blade escapa los datos; no evalúa HTML aportado por usuarios. Se deshabilitan recursos remotos, PHP y JavaScript. Solo se permiten archivos locales y datos embebidos; `chroot` se limita a los originales de marca y las fuentes de Dompdf. Los dos PNG locales fijos se incorporan como datos base64 internos, conservando sus bytes, para evitar la ambigüedad de las URI `file://` de Windows. No se aceptan imágenes ni HTML desde filtros o contenido documental.

### Dataset, temporales y errores

`DocumentReportService::exportDocuments()` contiene la misma selección, relaciones, conteo y orden que usaba CSV, extraída sin cambiar su consulta. Los tres formatos parten de `query()` con `visibleTo()` y filtros validados. Se carga una sola colección por petición: tras el conteo preliminar se valida también su tamaño real, y ese mismo tamaño se utiliza en metadatos y auditoría. CSV recibe la colección mediante `csvDocuments()`; `csv(Builder)` permanece como envoltorio compatible. Esto no introduce bloqueos, transacciones ni cambios en la concurrencia documental. `DocumentReportPresentation` comparte columnas, valores y nombres administrativos de filtros sin consultas adicionales. No hay autorización ni búsqueda alternativa en los renderizadores.

Las nuevas rutas `/reportes/documentos/exportar/xlsx` y `/reportes/documentos/exportar/pdf` repiten autenticación, cuenta activa, permiso/delegación y limitación de solicitudes de CSV; el máximo de filas es común. Los archivos se preparan en memoria/temporales de petición y se descargan con el mismo nombre descriptivo cambiando únicamente la extensión. Los recursos XLSX se cierran en `finally`; no se crea un repositorio de reportes.

Si falla la generación, se redirige al reporte mostrando «No fue posible generar el reporte. Intente nuevamente.», sin exponer SQL, rutas ni trazas. La validación del límite mantiene su mensaje específico. No se registra éxito si falla el renderizador.

### Dependencias y compatibilidad

Se añadieron únicamente los dos paquetes y sus dependencias necesarias mediante instalaciones limitadas, sin `composer update` global ni actualizar paquetes existentes. Se verificaron las extensiones locales necesarias, incluidos GD, ZIP, XML y mbstring.

La comparación de `composer.lock` con la versión base de `HEAD` confirma 111 paquetes anteriores conservados con las mismas versiones y referencias de origen/distribución. El archivo actual contiene 121 paquetes: se agregaron exactamente estas 10 dependencias, dos directas y ocho transitivas:

**Directas**, para generar los formatos autorizados:

- `phpoffice/phpspreadsheet` **5.10.0**: creación y formato del XLSX institucional.
- `dompdf/dompdf` **3.1.6** (tag `v3.1.6`): conversión de la vista de impresión a PDF.

**Transitivas de PhpSpreadsheet**, necesarias para su motor de hojas de cálculo y la escritura del contenedor ZIP de XLSX, aunque el reporte no utilice fórmulas:

- `composer/pcre` **3.4.0**: operaciones de expresiones regulares del motor.
- `maennchen/zipstream-php` **3.2.2**: escritura del contenedor ZIP del archivo XLSX.
- `markbaker/complex` **3.0.2**: soporte matemático requerido por el motor.
- `markbaker/matrix` **3.0.1**: operaciones matriciales requeridas por el motor.

**Transitivas de Dompdf**, necesarias para interpretar la vista y sus estilos, fuentes e imágenes:

- `dompdf/php-font-lib` **1.0.2**: lectura y tratamiento de fuentes.
- `dompdf/php-svg-lib` **1.0.2**: soporte de imágenes SVG del motor PDF; el reporte utiliza únicamente los PNG locales de marca.
- `masterminds/html5` **2.11.0**: análisis del HTML de impresión.
- `sabberworm/php-css-parser` **9.5.0** (tag `v9.5.0`): análisis de los estilos CSS utilizados por el motor.

- PhpSpreadsheet 5.10.0 requiere PHP `^8.2` y su [matriz oficial](https://raw.githubusercontent.com/PHPOffice/PhpSpreadsheet/5.10.0/.github/workflows/main.yml) incluye PHP 8.3 y 8.5. [Manifiesto de versión](https://raw.githubusercontent.com/PHPOffice/PhpSpreadsheet/5.10.0/composer.json).
- Dompdf 3.1.6 admite PHP `^7.1 || ^8.0` e incluye las correcciones explícitas de PHP 8.5 incorporadas desde 3.1.1. El workflow de ese tag no contiene PHP 8.5; no se afirma una ejecución local bajo esa versión. [Manifiesto](https://raw.githubusercontent.com/dompdf/dompdf/v3.1.6/composer.json), [cambios PHP 8.5](https://github.com/dompdf/dompdf/pull/3654).

Ambas bibliotecas son independientes de Illuminate y se integran mediante servicios de Laravel 13. La ejecución local se valida en PHP 8.3.28; migrar todo el entorno a PHP 8.5 queda fuera de esta intervención.

## Auditoría

Cada exportación completada registra el evento existente `reports.exported` en `audit_logs` con actor, formato (`csv`, `xlsx` o `pdf`), tipo de reporte, resultado, cantidad de filas, filtros administrativos permitidos y fecha propia del registro. La búsqueda textual libre se excluye de auditoría para evitar copiar asuntos o contenido sensible. No se guarda el archivo ni sus filas.

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

Archivos de la ampliación controlada del 21 de octubre de 2026:

- Modificados: `app/Http/Controllers/DocumentReportController.php`, `app/Services/DocumentReportService.php`, `resources/views/reports/documents.blade.php`, `resources/views/reports/partials/filters-applied.blade.php`, `resources/js/report-export.js`, `routes/web.php`, `tests/Feature/DocumentReportTest.php`, `composer.json`, `composer.lock` y este informe.
- Creados: `app/Support/DocumentReportPresentation.php`, `app/Services/DocumentReportXlsxExporter.php`, `app/Services/DocumentReportPdfExporter.php`, `resources/views/reports/pdf.blade.php` y `tests/Feature/DocumentReportExportTest.php`.
- No se modificaron migraciones, PostgreSQL, políticas, roles, permisos ni servicios de flujo documental en esta ampliación.

También se reforzó `Document::visibleTo()` para que Extra ignore cualquier participación ordinaria y solo considere concesiones efectivas. La prueba de búsqueda avanzada se adaptó para preservar la cobertura de destinatarios personales con un rol elegible.

## Cobertura y verificación

Las pruebas específicas cubren los 26 escenarios solicitados: acceso global, ámbitos de Jefe/Secretaria/Auxiliar, denegación de Extra, delegación vigente y expirada, confidencialidad, agregados, fechas, vencimientos, múltiples destinatarios, filtros combinados, paginación, CSV, inyección de fórmulas, límite, auditoría segura, manipulación de parámetros, N+1 y PostgreSQL real.

Resultados históricos del 12 de octubre de 2026 (no reejecutados en la ampliación del 21 de octubre):

- pruebas funcionales específicas: **14 pruebas, 95 aserciones**;
- integración PostgreSQL específica: **2 pruebas, 14 aserciones**;
- suite general: **255 pruebas, 1519 aserciones**;
- suite PostgreSQL completa: **67 pruebas, 275 aserciones**.

También quedaron aprobados Pint, compilación de Blade, las dos rutas del módulo y el build Vite. `php artisan migrate:status` confirmó las 19 migraciones existentes en estado `Ran`. No se creó ni ejecutó ninguna migración y no se utilizó `migrate:fresh` ni `db:wipe`.

## Verificación focalizada de la ampliación — 21 de octubre de 2026

Las tres etapas se completaron secuencialmente. Se mantuvo el alcance exclusivamente en reportes/exportaciones; los resultados generales anteriores son históricos y no corresponden a esta intervención.

- `DocumentReportTest.php`: **17 pruebas, 127 aserciones** aprobadas.
- `DocumentReportExportTest.php`: **15 pruebas, 185 aserciones** aprobadas en su verificación final, después del ajuste visual de logos PDF. Total de escenarios focalizados distintos: **32 pruebas, 312 aserciones**.
- La cobertura incluye filtros, ámbitos, denegación de Extra, confidencialidad, BOM/delimitador/escape CSV, tipos literales XLSX, fechas reales, logos originales, dataset común, límite previo y tamaño real, auditoría segura y errores genéricos.
- CSV inspeccionado con el separador regional real y lectura estructural de sus 12 columnas, incluidos comillas, punto y coma, saltos de línea y `á é í ó ú ñ`.
- XLSX ficticio de 35 filas reabierto con PhpSpreadsheet, inspeccionado y renderizado: una hoja, 12 columnas, logos originales embebidos, filtros, encabezados y fechas legibles. La revisión visual ajustó la alineación de la fecha de generación.
- PDF ficticio de 35 documentos abierto y renderizado en sus **8 páginas A4 horizontales**. Se inspeccionaron todas: siete columnas, tabla repetida, numeración correcta, acentos/fechas y los 34 códigos de documentos enviados íntegros. El documento restante es un borrador sin código. No se persistieron modelos ni se cargaron datos en PostgreSQL para estas muestras.
- La inspección visual detectó y corrigió la lectura de logos por URI `file://` en Windows: los dos PNG originales ahora se embeben internamente, sin descargas ni alteración de sus bytes. Render final sin advertencias; se añadió cobertura para evitar la regresión.
- Rutas del módulo: índice y descargas CSV/XLSX/PDF, con los mismos middleware de acceso y `throttle:10,1` en exportación.
- Compilación de Blade, build Vite y sintaxis de `report-export.js`: correctos. Se comprobó el formato PHP únicamente en los siete archivos PHP de reportes/pruebas implicados.
- No se lanzó Microsoft Excel de escritorio: la apertura XLSX se verificó con un lector real y render, y el CSV mediante estructura y configuración regional. La ejecución local fue en PHP **8.3.28**, no en PHP 8.5.
- Las pruebas usan el entorno efímero ya configurado de PHPUnit; no sustituyen PostgreSQL como base oficial ni cambian `.env`. No se ejecutó suite general, suite PostgreSQL, QA masivo, pruebas de otros módulos ni migraciones sobre la base del proyecto.
- Se retiró el script auxiliar de generación de muestras; no queda un comando de exportación/demo adicional en el repositorio.
