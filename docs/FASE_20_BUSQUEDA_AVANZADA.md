# Fase 20: Búsqueda avanzada

## Resultado

Se amplió la bandeja documental existente con búsqueda avanzada, filtros combinables, orden controlado y paginación persistente. La consulta reutiliza la autorización documental vigente y no crea un índice paralelo de permisos, una ruta pública ni una fuente alternativa de datos.

No se inició la Fase 21.

## Regla de seguridad principal

`DocumentSearchService` inicia toda consulta con:

```php
Document::query()->visibleTo($user)
```

Solo después aplica texto, campos, relaciones, fechas, vencimientos, correcciones y orden. La misma consulta autorizada produce las filas y el total del paginador. Esto impide que un filtro, un contador o una coincidencia textual revele documentos fuera del ámbito.

La visibilidad continúa diferenciando:

- Administrador y Co-administrador: ámbito institucional global.
- Jefe: documentos de su carrera y participación formal aplicable.
- Secretaria: documentos propios y participación formal de su carrera.
- Auxiliar: participación personal o de unidad formalizada, sin decisiones adicionales.
- Extra: únicamente concesiones explícitas `view` o `download` vigentes. No participa en `document_recipients` ni obtiene visibilidad por pertenecer a una carrera.
- Secretaria delegada: capacidades y ámbito de la carrera solo durante una delegación efectiva.

Los documentos con `deleted_at` y los destinatarios retirados lógicamente quedan excluidos por los scopes normales de Eloquent. La búsqueda no usa `withTrashed()`.

## Criterios disponibles

El formulario permite combinar mediante semántica AND:

- código oficial exacto o prefijo;
- asunto y descripción, sin distinguir mayúsculas;
- tipo documental;
- unidad de origen y unidad destinataria;
- destinatario personal;
- estado general y estado del destinatario;
- prioridad y clasificación normal/restringida;
- creador y remitente;
- rangos inclusivos de fecha de envío, límite y creación;
- próximo a vencer, vencido, con fecha o sin fecha;
- corrección preparada aún no enviada;
- versión mínima existente;
- vistas operativas enlazadas desde el dashboard.

El texto se normaliza, se limita a 120 caracteres, utiliza parámetros enlazados y trata `%`, `_` y barras como texto literal. La coincidencia exacta con el formato de código oficial usa la igualdad sobre `documents.code`.

## Validación y orden

`SearchDocumentsRequest` valida enums, UUID públicos existentes, booleanos, enteros y fechas `Y-m-d`. Cada fecha final debe ser igual o posterior a su fecha inicial.

El orden solo acepta `created_at`, `sent_at`, `due_at`, `priority` o `code`, con dirección `asc` o `desc`. Los valores no permitidos se rechazan antes de construir SQL. Todos los órdenes añaden `documents.id DESC` como desempate estable.

Las fechas finales se traducen a un límite exclusivo del día siguiente. Así, un filtro “hasta” incluye todo el día local sin convertir la columna `TIMESTAMPTZ` en la consulta.

## Interfaz

La búsqueda vive en `/documentos`, sin agregar rutas ni módulos duplicados. Los filtros frecuentes aparecen primero y “Más filtros” agrupa los criterios especializados. La pantalla:

- mantiene los filtros en la URL y en los enlaces de paginación;
- informa el total autorizado encontrado;
- ofrece una acción visible para limpiar filtros;
- conserva la vista de trabajo proveniente del dashboard;
- muestra un estado vacío específico;
- utiliza controles etiquetados, utilizables con teclado y adaptables a pantallas pequeñas.

No se implementaron búsqueda dentro de archivos, OCR, motor externo, autocompletado remoto, exportaciones ni reportes.

## Corrección pendiente y relaciones

La condición de corrección pendiente reutiliza los eventos append-only existentes: el documento debe continuar `returned` y el evento más reciente `document_version_created` debe ser posterior al último `correction_sent`. No se modifica `document_events` ni se crea una bandera duplicada.

Los filtros de destinatarios usan `whereHas`; por tanto, varias coincidencias relacionadas no duplican documentos. Tipo, unidad, creador y remitente se cargan anticipadamente para evitar consultas por fila.

## Rendimiento e índices

Se revisaron los índices existentes y se ejecutaron planes `EXPLAIN` reales en PostgreSQL. Quedaron confirmados:

- `documents_code_unique` para código exacto;
- `documents_origin_unit_id_general_status_created_at_index` para ámbito, estado y fecha;
- `documents_general_status_priority_index` para estado y prioridad;
- `documents_due_open_index` para vencimientos accionables;
- `document_recipients_unit_id_status_index` para unidad y estado destinatario;
- los índices existentes de creador, remitente, tipo, usuario destinatario y relaciones foráneas.

No se agregó una migración. En el alcance y volumen actuales, los filtros estructurados reducen el conjunto antes de la búsqueda textual y los planes confirman las rutas indexadas principales. Tampoco se habilitó `pg_trgm`, porque supondría una extensión y un cambio de esquema que no son necesarios para cerrar esta fase. Si el volumen real futuro muestra degradación, debe medirse antes de proponer índices trigram o un motor externo.

## Archivos principales

- `app/Http/Requests/SearchDocumentsRequest.php`: validación y lista permitida.
- `app/Services/DocumentSearchService.php`: composición autorizada de la consulta.
- `app/Http/Controllers/DocumentController.php`: paginación y catálogos acotados al actor.
- `resources/views/documents/index.blade.php`: formulario, resultados y estados de interfaz.
- `config/documents.php` y `.env.example`: cantidad configurable por página.
- `tests/Feature/DocumentSearchTest.php`: comportamiento, autorización, validación, seguridad y N+1.
- `tests/PostgreSQL/PostgreSqlDocumentSearchTest.php`: compatibilidad real, `TIMESTAMPTZ` e índices.

## Verificación

Resultados finales del 12 de octubre de 2026:

- pruebas funcionales específicas: 18 pruebas, 115 aserciones;
- integración PostgreSQL específica: 2 pruebas, 15 aserciones;
- suite general: 241 pruebas, 1424 aserciones;
- suite PostgreSQL completa: 65 pruebas, 261 aserciones;
- los planes PostgreSQL usan los índices de código, destinatario y vencimiento previstos;
- no se creó ni aplicó ninguna migración en esta fase.

También se verificaron formato PHP, compilación de Blade, rutas, build de producción y estado de migraciones. Todas las migraciones existentes permanecen en `Ran`.
