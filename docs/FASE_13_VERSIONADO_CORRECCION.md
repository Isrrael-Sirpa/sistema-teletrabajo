# Fase 13 — Versionado y corrección de documentos devueltos

> **Estado:** implementada y validada el 10 de octubre de 2026.

## 1. Resultado

Los documentos formalmente devueltos pueden corregirse sin sobrescribir archivos, versiones, hashes, decisiones ni eventos anteriores. El flujo se divide en dos acciones deliberadas:

1. **Preparar corrección:** crea una versión completa y no vacía con uno o varios archivos.
2. **Enviar corrección:** reincorpora al flujo únicamente a los destinatarios formales que habían devuelto el documento.

No se implementaron reapertura de rechazos, auditoría general, reportes, correo institucional ni firma digital.

## 2. Modelo de datos reutilizado

Se reutilizan `document_versions`, `document_files`, `document_recipients` y `document_events`. No se agregó una tabla ni migración para esta fase.

La versión vigente se obtiene con el mayor `version_number`; no se creó `current_version_id`. La corrección pendiente se deriva del evento más reciente `document_version_created` frente al evento más reciente `correction_sent`, evitando duplicar estado en `documents` o `document_versions`.

La restricción única existente `(document_id, version_number)` permanece como segunda barrera de concurrencia.

## 3. Reglas de preparación

Una nueva versión solo puede prepararse cuando:

- `general_status = returned`;
- existe al menos un destinatario formal `returned`;
- no existe otra corrección preparada y pendiente de envío;
- coincide el `lock_version` recibido;
- el actor tiene `documents.version` y pertenece al lado de origen, o posee supervisión institucional global.

Jefe y Secretaria de la unidad de origen pueden corregir. Administrador y Co-administrador pueden supervisar globalmente. Un usuario del destino no puede modificar los archivos del remitente.

El número se calcula en backend como `MAX(version_number) + 1` después de bloquear el documento. El frontend nunca decide el número.

## 4. Archivos e inmutabilidad

La preparación reutiliza las validaciones de `DocumentFileService`:

- almacenamiento privado;
- formatos y MIME permitidos;
- máximo de 20 MB por archivo y lote configurable;
- validación estructural de PDF, Office, imágenes y ZIP;
- nombre interno UUID;
- SHA-256 calculado desde el archivo almacenado;
- ZIP sin extracción.

La versión y todos sus archivos se crean dentro de una transacción. Si falla la persistencia, los archivos físicos escritos durante el intento se eliminan como compensación.

Las versiones ya enviadas no se pueden editar, retirar ni reemplazar por rutas ordinarias, incluso con roles globales. Todas continúan disponibles para descarga autorizada.

## 5. Envío de corrección

Al enviar la corrección:

- el código y el correlativo originales permanecen intactos;
- solo destinatarios `returned` con `requires_response = true` pasan a `sent`;
- se limpian `received_by`, `received_at`, `current_handler_id`, `decided_by`, `decided_at` y `decision_reason` para el nuevo ciclo;
- destinatarios `approved` conservan su aprobación;
- copias informativas conservan su estado y no bloquean;
- el estado general se recalcula con el algoritmo existente;
- la decisión anterior permanece en `document_events`.

El flujo admite ciclos repetidos: versión 1 → devolución → versión 2 → devolución → versión 3, sin límite artificial.

## 6. Historial

Se agregaron dos tipos de evento:

- `document_version_created`, con número de versión y cantidad de archivos;
- `correction_sent`, con versión, cantidad de archivos y destinatarios reincorporados.

No se guardan rutas privadas ni hashes en los metadatos visibles.

## 7. Concurrencia

Preparar y enviar una corrección bloquean primero la fila de `documents`. Después verifican `lock_version` dentro de la transacción.

- Dos preparaciones simultáneas con la misma versión esperada producen un solo ganador.
- Una transición concurrente invalida el `lock_version` de la preparación, o viceversa.
- Dos envíos simultáneos producen un solo evento y un único cambio de ciclo.
- La restricción única de PostgreSQL sobre `(document_id, version_number)` evita duplicados aun ante un fallo de la primera barrera.

## 8. Interfaz

El detalle muestra:

- versión actual derivada;
- motivo de cada devolución pendiente;
- formulario para preparar el conjunto documental corregido;
- confirmación separada para enviar la corrección;
- versiones ordenadas de la más reciente a la más antigua;
- archivos, autor y fecha de cada versión;
- descargas privadas de cualquier versión autorizada.

Los metadatos institucionales ya enviados —origen, tipo, asunto, destinatarios y código— no se modifican durante la corrección.

## 9. Archivos principales

- `app/Services/DocumentFileService.php`
- `app/Services/DocumentWorkflowService.php`
- `app/Policies/DocumentPolicy.php`
- `app/Http/Requests/StoreCorrectionVersionRequest.php`
- `app/Http/Requests/SendCorrectionRequest.php`
- `resources/views/documents/show.blade.php`
- `tests/Feature/DocumentVersioningTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentWorkflowTest.php`

## 10. Validación

La validación se ejecutó con PHP 8.3.33 y PostgreSQL como base oficial:

- pruebas específicas de versionado: **9 pruebas, 68 aserciones**;
- regresión de versionado, archivos, flujo e historial: **46 pruebas, 352 aserciones**;
- integración PostgreSQL de flujo y concurrencia: **4 pruebas, 22 aserciones**;
- suite funcional completa: **149 pruebas, 932 aserciones**;
- suite PostgreSQL completa: **52 pruebas, 179 aserciones**;
- Laravel Pint sin problemas de formato;
- plantillas Blade compiladas correctamente;
- recursos frontend construidos con Vite;
- `composer.json` válido;
- 17 rutas documentales verificadas.

No se ejecutó `migrate:fresh`, `db:wipe` ni otra operación destructiva. Esta fase no requirió una migración adicional.

## 11. Límites

- No existe reapertura de documentos `rejected`.
- No existe modificación de versiones históricas.
- No se implementó la Fase 14.
