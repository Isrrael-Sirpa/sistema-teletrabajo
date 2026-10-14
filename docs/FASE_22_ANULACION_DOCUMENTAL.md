# Fase 22: Anulación documental

## Resultado

La Fase 22 implementa la anulación formal, irreversible y trazable de documentos oficiales. Anular no elimina el expediente, no equivale a rechazar o devolver y no altera los estados históricos de sus destinatarios. El documento permanece consultable por quienes continúan autorizados, pero queda sin efecto institucional y fuera de todo trabajo operativo.

No se inició la Fase 23.

## Estructura reutilizada

La inspección previa confirmó que `documents` ya incluía todos los campos necesarios:

- `annulled_at TIMESTAMPTZ NULL`;
- `annulled_by` con FK `RESTRICT` hacia `users`;
- `annulment_reason TEXT NULL`;
- índice de soporte para `annulled_by`;
- estado `annulled` en la restricción de estados;
- `docs_annul_check`, que exige conjuntamente actor, fecha y motivo no vacío solo cuando el estado general es `annulled`.

Por ello no se creó ni ejecutó una migración. Se conservaron las 19 migraciones existentes y no se usó `migrate:fresh` ni `db:wipe`.

## Autorización

Se reutilizó el permiso RBAC existente `documents.annul`. La capacidad `annul` de `DocumentPolicy` exige simultáneamente:

- permiso explícito;
- rol Administrador o Co-administrador;
- documento oficial con código y fecha de envío;
- documento no descartado;
- estado distinto de `draft` y `annulled`.

Jefe, Secretaria, Auxiliar y Extra no pueden anular. Una delegación temporal de Jefatura tampoco concede esta capacidad porque `documents.annul` no forma parte del catálogo delegable.

Se permite anular desde `sent`, `received`, `in_review`, `approved`, `returned` y `rejected`. No existe desanulación ni anulación masiva.

## Operación transaccional y concurrencia

`DocumentWorkflowService::annul()` ejecuta la operación dentro de una transacción:

1. autoriza al actor antes de iniciar el caso de uso;
2. bloquea la fila del documento con `SELECT ... FOR UPDATE`;
3. vuelve a autorizar sobre el estado bloqueado;
4. compara el `lock_version` recibido;
5. valida y normaliza el motivo;
6. actualiza estado, actor, fecha, motivo y `lock_version`;
7. registra historial y auditoría en la misma transacción;
8. despacha la solicitud de notificación después del commit.

La prueba real con dos procesos PostgreSQL enfrenta una anulación y una aprobación sobre la misma versión. Solo una operación confirma; la otra recibe conflicto. Si la aprobación gana primero, una anulación posterior solo puede realizarse con el nuevo `lock_version`.

## Inmutabilidad y expediente histórico

Después de anular:

- `general_status` queda en `annulled` y `deleted_at` permanece nulo;
- código oficial, correlativo y `sent_at` no cambian;
- destinatarios y sus estados individuales no se modifican;
- archivos, hashes y versiones permanecen;
- eventos, decisiones, motivos anteriores, grants y notificaciones previas permanecen;
- una corrección preparada se conserva, pero no puede enviarse;
- recepción, revisión, aprobación, devolución, rechazo, corrección, nuevas versiones y reenvío quedan bloqueados.

Los grants no se revocan. Un Extra con una concesión vigente conserva la consulta o descarga histórica permitida por `DocumentPolicy`. Toda descarga continúa pasando por almacenamiento privado y autorización backend.

## Historial y auditoría

La anulación crea el evento append-only `document_annulled` con actor, estado anterior, estado nuevo, motivo y fecha. Los eventos anteriores no se actualizan ni eliminan.

También crea `documents.annulled` en `audit_logs`, asociado al documento y al actor, con estado anterior, estado nuevo, identificador público, clasificación, fecha y motivo administrativo. El servicio central de auditoría conserva además IP y contexto de solicitud cuando están disponibles.

Para documentos `restricted` no se copian asunto, descripción ni código en la auditoría. El motivo administrativo autorizado sí se conserva como fundamento de la decisión.

## Notificaciones internas

Después del commit se emite una notificación `database` titulada “Documento anulado” para las cuentas activas participantes:

- creador y remitente;
- Jefe y Secretaria de la unidad de origen;
- destinatarios personales formalizados;
- Jefe y Secretaria de unidades destinatarias formalizadas.

El actor se excluye y los usuarios se deduplican. Para `restricted`, el mensaje es genérico y no incluye código, asunto ni motivo. No se implementó correo.

## Interfaz y operación posterior

El detalle muestra a Administrador y Co-administrador una acción roja diferenciada de eliminar, con advertencia, código, estado actual y motivo obligatorio. El máximo inicial es configurable mediante:

```dotenv
DOCUMENT_ANNULMENT_REASON_MAX_LENGTH=2000
```

Un expediente anulado presenta un aviso visible “DOCUMENTO ANULADO”, actor, fecha y motivo inmutable. Los controles ordinarios desaparecen, pero archivos e historial siguen disponibles conforme a la Policy.

Los anulados:

- pueden localizarse por código y por el filtro `annulled`;
- se incluyen en el resumen de reportes por estado;
- no aparecen como pendientes, en revisión, asignados, correcciones pendientes, vencidos o próximos a vencer;
- no generan nuevos recordatorios de fecha límite.

## Archivos principales

### Creados

- `app/Http/Requests/AnnulDocumentRequest.php`
- `app/Notifications/DocumentAnnulledNotification.php`
- `tests/Feature/DocumentAnnulmentTest.php`
- `docs/FASE_22_ANULACION_DOCUMENTAL.md`

### Modificados

- `.env.example`
- `README.md`
- `app/Enums/AuditAction.php`
- `app/Enums/DocumentEventType.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/DocumentWorkflowController.php`
- `app/Listeners/SendDocumentLifecycleNotifications.php`
- `app/Models/Document.php`
- `app/Models/DocumentEvent.php`
- `app/Policies/DocumentPolicy.php`
- `app/Services/DocumentNotificationRecipientResolver.php`
- `app/Services/DocumentWorkflowService.php`
- `app/Services/DocumentWorkQueryService.php`
- `config/documents.php`
- `resources/views/documents/show.blade.php`
- `routes/web.php`
- `tests/PostgreSQL/PostgreSqlDocumentWorkflowTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentReportTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentSearchTest.php`
- `docs/PLAN_IMPLEMENTACION.md`

## Cobertura y verificación

La prueba funcional específica ejecuta 16 casos parametrizados y 138 aserciones, cubriendo los 35 escenarios mínimos de autorización, estados permitidos, motivo, conservación, historial, auditoría, confidencialidad, notificaciones, dashboard, fechas límite, búsqueda, reportes, corrección pendiente, descarga y grants.

Las comprobaciones de planes de búsqueda y reportes aceptan tanto el índice específico por unidad/estado como el índice único de destinatario cuando PostgreSQL 18 elige este último mediante su planificador. Ambos índices se comprueban previamente como existentes; el ajuste evita asumir un único plan válido para la misma consulta.

Resultados finales del 12 de octubre de 2026:

- pruebas funcionales específicas: **16 pruebas, 138 aserciones**;
- pruebas PostgreSQL específicas de integridad y concurrencia: **2 pruebas, 15 aserciones**;
- regresión documental relacionada: **106 pruebas, 727 aserciones**;
- suite general: **271 pruebas, 1657 aserciones**;
- suite PostgreSQL completa: **69 pruebas, 290 aserciones**.

También quedaron aprobados Pint, compilación de Blade, registro de ruta, build Vite y `php artisan migrate:status`. Las 19 migraciones existentes continúan en estado `Ran`.
