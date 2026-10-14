# Fase 14: notificaciones internas

Fecha de cierre técnico: 10 de octubre de 2026.

## Resultado

La aplicación dispone de notificaciones internas basadas en el sistema oficial de Laravel y en la tabla `notifications` creada en la Fase 2. No se agregó ninguna migración ni tabla paralela. El único canal activo es `database`; no existe envío SMTP, SMS, push ni polling.

La implementación cubre:

- documento enviado;
- documento recibido;
- revisión iniciada;
- documento aprobado, devuelto o rechazado;
- corrección enviada y nueva versión disponible para el destinatario devuelto.

## Arquitectura y transacciones

`DocumentWorkflowService` registra primero el evento inmutable del expediente y despacha `DocumentNotificationRequested` dentro de la misma operación. Este evento implementa `ShouldDispatchAfterCommit`, por lo que Laravel ejecuta `SendDocumentLifecycleNotifications` únicamente cuando se confirma la transacción exterior.

La separación es intencional:

1. la acción documental modifica estado y escribe `document_events`;
2. el commit confirma la operación;
3. el listener resuelve destinatarios reales;
4. una clase `Notification` persiste el aviso.

Una prueba PostgreSQL abre una transacción exterior, ejecuta el envío y confirma que no existe notificación antes del commit. Otra revierte la transacción y comprueba que no permanecen ni el cambio documental, ni su evento, ni el aviso. El listener se registra una sola vez mediante el descubrimiento automático de eventos de Laravel.

## Destinatarios

`DocumentNotificationRecipientResolver` aplica estas reglas:

- destino personal: usuario activo indicado directamente;
- destino por unidad: Jefe de Carrera y Secretaria activos de esa unidad;
- envío de corrección: destinatarios formales que regresan al flujo;
- recepción e inicio de revisión: creador y remitente activos, evitando avisos al propio actor;
- aprobación, devolución y rechazo: creador/remitente y Jefe/Secretaria activos de la unidad de origen;
- Extra y cuentas inactivas: excluidos del flujo ordinario.

La colección final se deduplica por identificador de usuario. Una persona que coincide como destinatario directo y responsable de unidad recibe un solo aviso del evento.

## Clases de notificación

- `DocumentSentNotification`: nuevo documento enviado.
- `DocumentDecisionNotification`: recepción, revisión, aprobación, devolución y rechazo.
- `CorrectionSentNotification`: corrección y número de la nueva versión disponible.

Las clases devuelven actualmente `['database']` desde `via()`. La lógica de destinatarios no depende del canal, de modo que `mail` podrá agregarse más adelante sin trasladarla a controladores ni duplicarla.

Cada registro contiene título, mensaje, identificador público del documento, código cuando corresponde, tipo y fecha del evento, y una ruta relativa con parámetros. La URL se construye al renderizar; no se persisten URLs absolutas.

## Confidencialidad y autorización

Para un documento `restricted`, el registro usa mensajes genéricos. No persiste asunto, motivo ni código documental. El identificador público y la ruta solo permiten intentar abrir el recurso.

La notificación no concede acceso. El enlace utiliza `documents.show` y toda solicitud vuelve a pasar por `DocumentPolicy`; si el usuario ya no posee visibilidad recibe HTTP 403. Las operaciones de lectura buscan el UUID de la notificación a través de la relación del usuario autenticado, por lo que una cuenta no puede marcar avisos ajenos.

## Centro de notificaciones

La ruta autenticada `/notificaciones` presenta dos listas independientes:

- no leídas;
- leídas.

Cada lista muestra fecha y hora en `America/La_Paz`, título, mensaje y acceso contextual al documento. Se puede marcar un aviso o todos como leídos. Ambas secciones tienen paginación propia y el tamaño se configura con `DOCUMENT_NOTIFICATIONS_PER_PAGE`, inicialmente 15.

La navegación muestra `Notificaciones (N)` con el total no leído. El contador se calcula al renderizar la página y no realiza polling.

## Rutas

| Método | Ruta | Nombre | Función |
|---|---|---|---|
| GET | `/notificaciones` | `notifications.index` | Centro de notificaciones. |
| PATCH | `/notificaciones/leer-todas` | `notifications.read-all` | Marcar todos los avisos propios. |
| PATCH | `/notificaciones/{notification}/leer` | `notifications.read` | Marcar un aviso propio. |

Todas requieren `auth` y `active.user`, además de CSRF en operaciones `PATCH`.

## Pruebas

`tests/Feature/DocumentNotificationTest.php` cubre destinos personales y por unidad, exclusión de Extra e inactivos, decisiones al origen, motivo de devolución, rechazo, corrección y versión, deduplicación, confidencialidad, Policy, lectura individual/masiva, aislamiento entre usuarios, contador y paginación.

`tests/PostgreSQL/PostgreSqlDocumentNotificationTest.php` comprueba persistencia real PostgreSQL y semántica after-commit/rollback.

Resultados de cierre:

- específicas de Fase 14: 7 pruebas, 47 aserciones;
- integración específica PostgreSQL: 2 pruebas, 9 aserciones;
- suite funcional completa: 156 pruebas, 979 aserciones;
- suite PostgreSQL completa: 54 pruebas, 188 aserciones;
- Pint: correcto;
- rutas, Blade y Vite: correctos;
- `migrate:status`: todas las migraciones aplicadas.

## Archivos principales

### Creados

- `app/Events/DocumentNotificationRequested.php`
- `app/Listeners/SendDocumentLifecycleNotifications.php`
- `app/Services/DocumentNotificationRecipientResolver.php`
- `app/Notifications/Concerns/BuildsDocumentNotificationData.php`
- `app/Notifications/DocumentSentNotification.php`
- `app/Notifications/DocumentDecisionNotification.php`
- `app/Notifications/CorrectionSentNotification.php`
- `app/Http/Controllers/NotificationController.php`
- `resources/views/notifications/index.blade.php`
- `resources/views/notifications/partials/item.blade.php`
- `tests/Feature/DocumentNotificationTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentNotificationTest.php`
- `docs/FASE_14_NOTIFICACIONES_INTERNAS.md`

### Modificados

- `app/Services/DocumentWorkflowService.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `routes/web.php`
- `config/documents.php`
- `.env.example`
- `README.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/arquitectura/FLUJO_DOCUMENTAL.md`

## Límite de fase

No se implementaron correo real, SMS, push, recordatorios, auditoría general, reportes ni dashboards definitivos. La Fase 15 no fue iniciada.
