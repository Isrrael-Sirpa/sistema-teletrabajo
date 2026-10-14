# Fase 9 — Destinatarios

> **Estado:** implementada y validada el 10 de octubre de 2026.

## 1. Alcance completado

Se implementó la preparación de destinatarios para documentos que continúan en estado `draft`. Un borrador puede combinar:

- una o varias carreras activas;
- Kardex activo;
- una o varias personas activas pertenecientes a una carrera o Kardex activo;
- destinatarios que requieren respuesta;
- copias informativas.

Cada destino es una fila independiente en `document_recipients`. No se generan cadenas separadas por comas ni nombres libres.

No se implementaron envío oficial, código documental, correlativos, archivos, versiones, recepción, revisión, decisiones, eventos de envío ni notificaciones.

## 2. Migración incremental justificada

La tabla existente obligaba a que toda fila comenzara con estado `sent`. Utilizar ese valor durante la preparación habría representado falsamente que el documento ya fue enviado.

La migración incremental `2026_10_10_000100_prepare_document_recipients_for_drafts`:

- agrega `prepared` al dominio permitido por `recipients_status_check`;
- cambia el valor predeterminado de `status` a `prepared` en PostgreSQL;
- permite copias informativas mientras el estado sea `prepared`;
- conserva sin cambios los estados futuros `sent`, `received`, `in_review`, `approved`, `returned` y `rejected`;
- conserva índices, FKs restrictivas y la unicidad existente;
- no transforma ni elimina registros existentes.

La reversión se niega si existen destinatarios preparados para evitar convertirlos silenciosamente en enviados.

## 3. Modelo de destinatarios

### Destino por unidad

- `unit_id`: carrera o Kardex activo;
- `user_id`: nulo.

### Destino personal

- `user_id`: usuario activo;
- `unit_id`: se deriva en el servidor de la unidad activa del usuario;
- no se admite un usuario global sin unidad como destinatario personal.

La elegibilidad personal quedó precisada al cerrar la Fase 11:

- Jefe de Carrera y Secretaria pueden agregarse como destino formal (`requires_response=true`) o copia informativa;
- Auxiliar de Secretaría puede agregarse únicamente como copia informativa (`requires_response=false`);
- Extra no se ofrece ni se acepta como destinatario documental normal; su acceso futuro se concede mediante `document_access_grants`;
- la validación se ejecuta en el servicio, por lo que una solicitud manipulada no puede eludir el selector.

La Facultad se excluye del selector ordinario. Se permite que la unidad de origen también sea destinataria porque pueden existir trámites internos.

## 4. Duplicados

La aplicación valida duplicados antes de escribir y PostgreSQL conserva la segunda barrera mediante:

```text
UNIQUE NULLS NOT DISTINCT (document_id, unit_id, user_id)
```

Por tanto:

- la misma carrera no puede agregarse dos veces;
- la misma persona no puede agregarse dos veces;
- una carrera y una persona concreta de esa carrera sí pueden coexistir, pues expresan intenciones diferentes.

Una violación concurrente de unicidad revierte toda la transacción y se presenta como error de validación en español.

Desde la Fase 12, retirar un destino `prepared` aplica eliminación lógica. Las relaciones ordinarias lo ocultan, pero sus eventos conservan la FK. Si vuelve a agregarse el mismo destino, el servicio restaura la misma fila, limpia cualquier dato de transición, actualiza `requires_response` y conserva el identificador; no crea un duplicado ni expone una acción pública de restauración. Los destinos formalizados nunca pueden retirarse por este flujo.

## 5. Autorización

`DocumentPolicy::manageRecipients` reutiliza exactamente la regla de edición del borrador:

- exige `documents.update_draft`;
- exige estado `draft`;
- Jefe y Secretaria solo administran su propio borrador y su ámbito;
- Administrador y Co-administrador conservan supervisión global;
- Auxiliar, Extra y usuarios sin relación reciben `403`.

La consulta de destinatarios solo ocurre después de autorizar la visualización del documento. Los selectores de personas y unidades únicamente se cargan para quien puede administrar la lista.

El documento se resuelve en rutas mediante su `public_id`. El identificador interno del destinatario viaja en el cuerpo del formulario y el servicio siempre lo vuelve a buscar dentro del documento autorizado, evitando acceso horizontal.

## 6. Concurrencia y transacciones

Agregar, reclasificar o quitar un destinatario se ejecuta dentro de una transacción. Cada operación actualiza el documento únicamente si coinciden simultáneamente:

```text
documento + status draft + lock_version recibido
```

La actualización incrementa `documents.lock_version`. Si otra pestaña ya modificó los datos generales o los destinatarios, la operación obsoleta no escribe ninguna fila y solicita recargar la página.

El mismo contador coordina las ediciones de Fase 8 y la lista de Fase 9; no existen dos controles paralelos.

## 7. Ausencia de envío

Después de cualquier operación de destinatarios:

- `documents.general_status` continúa en `draft`;
- `documents.code` continúa nulo;
- `documents.sent_at` continúa nulo;
- el destinatario continúa en `prepared`;
- no se crean notificaciones;
- no se crean eventos documentales.

En la futura operación transaccional de envío, los destinatarios preparados pasarán a `sent` junto con la asignación del código oficial.

## 8. Interfaz

El detalle del borrador contiene una sección independiente y responsive con:

- listado de destinatarios preparados;
- distinción entre persona específica y carrera/unidad completa;
- clasificación “Requiere respuesta” o “Copia informativa”;
- formulario “Enviar a una carrera o unidad”;
- formulario “Enviar a una persona específica”;
- actualización de la finalidad;
- retiro confirmado del destinatario;
- aviso permanente de que el documento todavía no fue enviado.

El selector personal muestra el rol institucional, excluye Extra y, al seleccionar un Auxiliar, limita la finalidad a copia informativa. La regla visual es solo una ayuda: el backend vuelve a validarla al preparar y al enviar.

No se añadieron controles de envío, archivos, recepción ni decisiones.

## 9. Rutas añadidas

| Método | Ruta | Nombre | Protección |
|---|---|---|---|
| `POST` | `/documentos/{document}/destinatarios` | `documents.recipients.store` | `documents.update_draft` + Policy |
| `PATCH` | `/documentos/{document}/destinatarios` | `documents.recipients.update` | `documents.update_draft` + Policy |
| `DELETE` | `/documentos/{document}/destinatarios` | `documents.recipients.destroy` | `documents.update_draft` + Policy |

## 10. Pruebas y verificaciones

Las pruebas específicas verifican unidades, personas, varios destinos, Kardex, mismo origen, destinos inactivos, Facultad excluida, usuarios inactivos o globales sin unidad, RBAC, Policy, supervisión global, duplicados, respuesta/copia, modificación, retiro, documento no borrador, concurrencia y acceso horizontal.

La integración PostgreSQL verifica el valor predeterminado `prepared`, copia informativa, dominio de estados, FKs, unicidad con nulos, coexistencia unidad/persona, origen personal derivado, ausencia de efectos de envío y bloqueo optimista.

- Pruebas específicas de Fase 9: **13 pruebas, 70 aserciones**.
- Integración PostgreSQL específica: **5 pruebas, 18 aserciones**.
- Suite completa local: **100 pruebas, 543 aserciones**.
- Integración PostgreSQL completa: **40 pruebas, 118 aserciones**.
- Pint sobre todos los archivos PHP creados o modificados: aprobado.
- Plantillas Blade: compilación aprobada.
- Build de Vite: aprobado, 58 módulos transformados.
- Rutas documentales: 10 verificadas, incluidas las 3 rutas de destinatarios.
- Migraciones PostgreSQL: 17 de 17 con estado `Ran`.

## 11. Archivos creados

- `database/migrations/2026_10_10_000100_prepare_document_recipients_for_drafts.php`
- `app/Enums/DocumentRecipientStatus.php`
- `app/Models/DocumentRecipient.php`
- `app/Services/DocumentRecipientService.php`
- `app/Http/Controllers/DocumentRecipientController.php`
- `app/Http/Requests/StoreDocumentRecipientRequest.php`
- `app/Http/Requests/UpdateDocumentRecipientRequest.php`
- `app/Http/Requests/DestroyDocumentRecipientRequest.php`
- `tests/Feature/DocumentRecipientManagementTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentRecipientTest.php`
- `docs/FASE_9_DESTINATARIOS.md`

## 12. Archivos modificados

- `app/Models/Document.php`
- `app/Policies/DocumentPolicy.php`
- `app/Http/Controllers/DocumentController.php`
- `resources/views/documents/show.blade.php`
- `routes/web.php`
- `tests/Feature/DocumentDraftManagementTest.php`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/arquitectura/FLUJO_DOCUMENTAL.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 13. Límites de la fase

- No existe acción de envío.
- No se genera código ni correlativo.
- No se crean archivos o versiones.
- No se cambian estados formales del flujo.
- No se generan notificaciones ni historial.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 10 no fue iniciada.
