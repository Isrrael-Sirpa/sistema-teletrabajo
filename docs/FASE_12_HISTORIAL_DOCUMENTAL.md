# Fase 12 — Historial documental

> **Estado:** implementada y validada el 10 de octubre de 2026.

## 1. Resultado

Se implementó la línea de tiempo append-only de cada documento reutilizando exclusivamente `document_events`. El historial distingue preparación y eventos oficiales, registra actor, hora exacta, destinatario relacionado, transición de estado, motivo y metadatos administrativos moderados.

No se implementaron auditoría transversal, notificaciones completas, reportes, versión 2, corrección tras devolución, firma digital ni correo institucional.

## 2. Estructura reutilizada

`document_events` ya proporcionaba:

- FK restrictiva al documento;
- FK nullable y restrictiva al destinatario;
- FK nullable al actor;
- tipo de evento;
- estado anterior y nuevo;
- motivo;
- `jsonb` para metadatos;
- `occurred_at timestamptz`;
- índices por documento/fecha, actor/fecha, tipo/fecha y destinatario.

No se modificó esta tabla.

## 3. Migración incremental autorizada

La inmutabilidad del historial era incompatible con las eliminaciones físicas previas. Se agregó `deleted_at timestamptz`, con índice, a:

- `documents`;
- `document_recipients`.

`Document` y `DocumentRecipient` utilizan `SoftDeletes`. Los listados, relaciones y el route model binding excluyen registros retirados automáticamente. No existe interfaz de restauración.

Un destinatario preparado retirado puede volver a agregarse. El servicio restaura internamente la misma fila, conserva su identificador histórico, aplica la nueva finalidad, devuelve el estado a `prepared` y limpia datos de recepción, responsable y decisión. Un duplicado activo continúa rechazándose.

Solo borradores propios nunca enviados pueden descartarse. Los documentos enviados y los destinatarios formalizados no pueden eliminarse mediante el flujo ordinario.

## 4. Eventos implementados

### Preparación

- `document_created`;
- `draft_updated`, solo cuando cambian campos relevantes;
- `draft_discarded`;
- `recipient_added`;
- `recipient_updated`;
- `recipient_removed`;
- `file_added`;
- `file_removed`.

### Ciclo oficial

- `document_sent`;
- `document_received`;
- `review_started`;
- `document_approved`;
- `document_returned`;
- `document_rejected`.

Las lecturas simples no generan eventos.

## 5. Consistencia transaccional

`DocumentEventService` solo registra hechos; no calcula estados. Fue integrado dentro de las transacciones de:

- `DocumentDraftService`;
- `DocumentRecipientService`;
- `DocumentFileService`;
- `DocumentWorkflowService`.

Si una operación falla, el evento se revierte con ella. Si el registro del evento falla, la operación principal también se revierte. Las cargas y retiros de archivos mantienen además su compensación de almacenamiento físico.

## 6. Metadatos

Los eventos guardan únicamente datos complementarios útiles:

- campos modificados del borrador;
- tipo, nombre de unidad y finalidad del destinatario;
- indicador de restauración interna;
- nombre original, tamaño y versión del archivo;
- código, remitente, origen y cantidad de destinatarios al enviar;
- estado general anterior y nuevo.

No se guardan contraseñas, tokens, rutas físicas, nombres internos de archivo ni hashes en el historial visible.

## 7. Inmutabilidad

`DocumentEvent` rechaza actualizaciones y eliminaciones mediante eventos del modelo. No existen rutas o controladores CRUD del historial. Las FKs `RESTRICT` protegen documento, destinatario y actor.

Los borradores y destinatarios retirados permanecen en base de datos, de modo que el evento conserva su relación estructurada. El historial usa `withTrashed()` solamente para resolver esas relaciones históricas.

## 8. Interfaz, autorización y rendimiento

El historial aparece dentro del detalle ya protegido por `DocumentPolicy::view`; no se creó una ruta separada. Un usuario sin acceso al documento tampoco puede consultar su línea de tiempo y Extra no obtiene acceso automático.

La vista muestra:

- evento y clasificación “Preparación” o “Evento oficial”;
- descripción comprensible;
- actor;
- fecha y hora con segundos en `America/La_Paz`;
- motivo visible para devolución y rechazo;
- metadatos administrativos seleccionados.

La convención es más reciente primero. Se cargan 15 eventos por página, configurable mediante `DOCUMENT_HISTORY_PER_PAGE`, con paginación tradicional.

## 9. Archivos principales

- `app/Enums/DocumentEventType.php`
- `app/Models/DocumentEvent.php`
- `app/Services/DocumentEventService.php`
- `database/migrations/2026_10_10_000200_add_soft_deletes_to_documents_and_recipients.php`
- `resources/views/documents/show.blade.php`
- `tests/Feature/DocumentHistoryTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentHistoryTest.php`

## 10. Validación final

La validación se ejecutó con PHP 8.3.33 y PostgreSQL como base oficial:

- suite funcional completa: **140 pruebas, 864 aserciones**;
- suite PostgreSQL completa: **51 pruebas, 173 aserciones**;
- historial documental específico: **8 pruebas, 63 aserciones**;
- historial documental específico en PostgreSQL: **4 pruebas, 18 aserciones**;
- las **18 migraciones** aparecen con estado `Ran`, incluida la migración incremental de eliminación lógica;
- Laravel Pint no detectó problemas de formato;
- las plantillas Blade compilaron correctamente;
- la construcción de recursos con Vite finalizó correctamente;
- `composer.json` fue validado;
- se comprobaron las 15 rutas documentales existentes, sin agregar una ruta pública de restauración.

No se utilizó `migrate:fresh`, `db:wipe` ni otra operación destructiva.

## 11. Límites

- `document_events` explica el ciclo documental.
- `audit_logs` continúa reservado para auditoría transversal.
- No existe restauración pública.
- No se implementó la Fase 13.

