# Fase 8 — Gestión documental básica

> **Estado:** implementada y validada el 9 de octubre de 2026.

## 1. Alcance completado

Se implementó el núcleo documental limitado a documentos en estado `draft`: creación, listado paginado, consulta, edición posterior y descarte seguro. El código oficial permanece nulo hasta el futuro envío.

No se implementaron destinatarios, envío, correlativos, archivos, versiones, recepción, aprobación, devolución, rechazo, historial completo, notificaciones, reportes ni auditoría completa.

## 2. Revisión de la estructura existente

No fue necesaria una migración incremental. Se reutilizó la tabla `documents` de la Fase 2, que ya contiene:

- clave interna `id` y UUID público `public_id`;
- FKs restrictivas hacia tipo documental, unidad de origen, creador y remitente;
- asunto, descripción, estado, prioridad, confidencialidad y fecha límite;
- código y fecha de envío anulables durante el borrador;
- `lock_version` para concurrencia optimista;
- índices por origen/estado/creación, estado/prioridad, vencimiento/estado y usuarios;
- restricciones PostgreSQL para estados, prioridad, confidencialidad, coherencia de envío, anulación y versión de bloqueo no negativa.

Las tablas documentales dependientes también utilizan `RESTRICT`, no borrado en cascada.

## 3. Creación segura

`DocumentDraftService` crea el borrador dentro de una transacción y controla desde el backend:

- `created_by`: usuario autenticado;
- `general_status`: siempre `draft`;
- `code`, `sender_user_id` y `sent_at`: nulos;
- `lock_version`: cero;
- tipo documental: debe continuar activo;
- unidad de origen: activa y válida.

Los atributos sensibles enviados por el cliente se descartan. No se consume ningún correlativo.

Para Jefe de Carrera y Secretaria, el origen se deriva exclusivamente de la carrera activa asignada al usuario. Un valor diferente enviado por el navegador se ignora. Administrador y Co-administrador seleccionan una unidad institucional activa validada por servidor.

La fecha límite es opcional. Cuando se informa, no puede estar en el pasado y se conserva como fin del día en la zona horaria institucional.

## 4. Autorización y visibilidad

Las rutas combinan `auth`, control de usuario activo, middleware RBAC y `DocumentPolicy`.

| Actor | Listar/ver | Crear | Editar | Descartar |
|---|---|---|---|---|
| Administrador / Co-administrador | Ámbito global | Sí | Cualquier borrador permitido globalmente | Solo su propio borrador |
| Jefe de Carrera | Documentos de su carrera | Sí | Solo su propio borrador de la carrera | Solo su propio borrador |
| Secretaria | Sus propios documentos de su carrera | Sí | Solo su propio borrador | Solo su propio borrador |
| Auxiliar / Extra | Sin ampliación de acceso en esta fase | No | No | No |

La decisión de edición combina el permiso `documents.update_draft`, estado `draft`, propiedad y ámbito. Los documentos restringidos siguen exactamente la misma regla restrictiva: no abren acceso a terceros.

La consulta de listados aplica el ámbito directamente en SQL mediante `visibleTo()` antes de paginar; no carga documentos ajenos para filtrarlos en memoria.

## 5. Concurrencia optimista

El formulario de edición envía la versión que abrió. La actualización se realiza con una única operación SQL condicionada por:

```text
id = documento AND general_status = draft AND lock_version = versión_recibida
```

La misma operación incrementa `lock_version`. Si ninguna fila coincide, no se sobrescribe nada y se pide recargar el borrador. La condición de estado también impide que una pestaña antigua edite un documento que dejó de ser borrador.

## 6. Descarte

Desde la Fase 12, el descarte utiliza eliminación lógica para conservar la línea de tiempo. Antes de descartarlo, el servicio:

1. autoriza `discardDraft`;
2. bloquea la fila dentro de una transacción;
3. vuelve a comprobar estado `draft`, código y fecha de envío nulos, y propiedad del creador;
4. verifica que no existan destinatarios, versiones, observaciones ni concesiones de acceso;
5. registra `draft_discarded` y asigna `deleted_at` dentro de la misma transacción.

Un Administrador o Co-administrador no puede usar esta acción para descartar el borrador de otra persona. Ningún documento que haya salido de `draft` puede descartarse por este flujo. El borrador desaparece de listados y rutas normales, pero su fila y eventos permanecen. No existe restauración desde la interfaz. El registro de auditoría transversal permanece reservado para su fase específica.

## 7. Interfaz

La interfaz Blade/Tailwind en español ofrece:

- formulario simple con tipo, asunto, descripción, prioridad, confidencialidad y fecha límite;
- selector de origen solo para usuarios globales;
- origen de carrera visible pero no editable para usuarios locales;
- indicador destacado **BORRADOR**;
- listado paginado de 15 filas con estado, tipo, asunto, origen, creador, prioridad y fechas;
- detalle con código indicado como “Se asignará al enviar”;
- edición y descarte solo cuando la Policy los autoriza;
- mensajes de validación y conflicto de concurrencia comprensibles.

No aparecen controles de destinatarios, archivos, envío ni decisiones.

## 8. Rutas

| Método | Ruta | Nombre | Permiso |
|---|---|---|---|
| `GET` | `/documentos` | `documents.index` | `documents.view` + Policy |
| `GET` | `/documentos/crear` | `documents.create` | `documents.create` |
| `POST` | `/documentos` | `documents.store` | `documents.create` |
| `GET` | `/documentos/{document}` | `documents.show` | `documents.view` + Policy |
| `GET` | `/documentos/{document}/editar` | `documents.edit` | `documents.update_draft` + Policy |
| `PUT` | `/documentos/{document}` | `documents.update` | `documents.update_draft` + Policy |
| `DELETE` | `/documentos/{document}/descartar` | `documents.discard` | `documents.discard_draft` + Policy |

`{document}` se resuelve por `public_id`, no por el identificador numérico interno. No existen rutas para enviar, agregar destinatarios o subir archivos.

## 9. Pruebas y verificaciones

Las pruebas específicas cubren permisos positivos y negativos, ámbitos globales y por carrera, origen no falsificable, tipos activos, validación, atributos controlados, confidencialidad, consulta, edición, estado no editable, fecha opcional, paginación, concurrencia y descarte.

La integración PostgreSQL cubre creación real, FKs, dominios restringidos, coherencia entre `draft`/código/envío, bloqueo optimista y protección frente a dependencias.

- Pruebas específicas de Fase 8: **15 pruebas, 106 aserciones**.
- Suite completa local: **87 pruebas, 474 aserciones**.
- Integración PostgreSQL específica: **6 pruebas, 20 aserciones**.
- Integración PostgreSQL completa: **35 pruebas, 100 aserciones**.
- Pint sobre todos los archivos PHP creados o modificados: aprobado.
- Build de Vite: aprobado, 58 módulos transformados.
- Rutas documentales: 7 rutas verificadas.
- Migraciones: 16 de 16 con estado `Ran`; no se aplicaron migraciones nuevas.

## 10. Archivos creados

- `app/Enums/DocumentStatus.php`
- `app/Enums/DocumentPriority.php`
- `app/Enums/DocumentConfidentiality.php`
- `app/Models/Document.php`
- `app/Policies/DocumentPolicy.php`
- `app/Services/DocumentDraftService.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Http/Requests/StoreDocumentRequest.php`
- `app/Http/Requests/UpdateDocumentRequest.php`
- `resources/views/documents/index.blade.php`
- `resources/views/documents/create.blade.php`
- `resources/views/documents/edit.blade.php`
- `resources/views/documents/show.blade.php`
- `resources/views/documents/_form.blade.php`
- `tests/Feature/DocumentDraftManagementTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentDraftTest.php`
- `docs/FASE_8_GESTION_DOCUMENTAL_BASICA.md`

## 11. Archivos modificados

- `app/Providers/AppServiceProvider.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `routes/web.php`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 12. Límites de la fase

- No se modificó ninguna migración anterior ni se creó una nueva.
- No se crean registros en tablas de destinatarios, versiones, archivos, eventos o notificaciones.
- No existe acción funcional de cambio de estado o asignación de código oficial.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas sobre la base existente.
- La Fase 9 no fue iniciada.
