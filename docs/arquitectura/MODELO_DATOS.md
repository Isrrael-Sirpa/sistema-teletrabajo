# Modelo de datos inicial
# Modelo de Datos Institucional (MySQL)

Este diseño lógico fue convertido a migraciones y validado sobre PostgreSQL 18.3 durante la Fase 2. Los nombres físicos siguen convenciones Laravel en inglés; este documento conserva nombres funcionales en español para facilitar su revisión institucional.
Este diseño lógico representa la estructura oficial de la base de datos del sistema, implementada y validada sobre **MySQL 8.4+ (InnoDB)** con codificación `utf8mb4` e intercolación `utf8mb4_unicode_ci`.

## 1. Diagrama entidad-relación conceptual
Todas las **tablas físicas están nombradas en español** para facilitar su identificación y administración institucional directa, manteniendo los atributos y columnas en convención estándar de Laravel para preservar la estabilidad de controladores, servicios y vistas de usuario.

---

## 1. Diagrama Entidad-Relación Conceptual

```mermaid
erDiagram
    UNIDADES ||--o{ USUARIOS : agrupa
    USUARIOS }o--o{ ROLES : posee
    ROLES }o--o{ PERMISOS : concede
    UNIDADES ||--o{ DELEGACIONES : aplica_en
    USUARIOS ||--o{ DELEGACIONES : recibe
    DELEGACIONES ||--o{ DELEGACION_PERMISOS : habilita
    PERMISOS ||--o{ DELEGACION_PERMISOS : limita
    USUARIOS ||--o{ DOCUMENTOS : crea
    UNIDADES ||--o{ DOCUMENTOS : origina
    TIPOS_DOCUMENTO ||--o{ DOCUMENTOS : clasifica
    DOCUMENTOS ||--o{ DOCUMENTO_DESTINATARIOS : distribuye
    UNIDADES ||--o{ DOCUMENTO_DESTINATARIOS : recibe_como_unidad
    USUARIOS ||--o{ DOCUMENTO_DESTINATARIOS : recibe_como_persona
    DOCUMENTOS ||--o{ DOCUMENTO_VERSIONES : conserva
    DOCUMENTO_VERSIONES ||--o{ DOCUMENTO_ARCHIVOS : contiene
    DOCUMENTOS ||--o{ DOCUMENTO_EVENTOS : registra
    DOCUMENTO_DESTINATARIOS ||--o{ DOCUMENTO_EVENTOS : contextualiza
    DOCUMENTOS ||--o{ DOCUMENTO_OBSERVACIONES : comenta
    DOCUMENTOS ||--o{ DOCUMENTO_ACCESOS : autoriza
    USUARIOS ||--o{ DOCUMENTO_ACCESOS : obtiene
    DOCUMENTOS ||--o{ AUDITORIAS : audita
    USUARIOS ||--o{ AUDITORIAS : ejecuta
    UNIDADES ||--o{ CORRELATIVOS_DOCUMENTO : numera
    unidades_organizacionales ||--o{ usuarios : agrupa
    usuarios }o--o{ roles : posee
    roles }o--o{ permisos : concede
    unidades_organizacionales ||--o{ delegaciones_temporales : aplica_en
    usuarios ||--o{ delegaciones_temporales : recibe
    delegaciones_temporales ||--o{ permisos_delegaciones_temporales : habilita
    permisos ||--o{ permisos_delegaciones_temporales : limita
    usuarios ||--o{ documentos : crea
    unidades_organizacionales ||--o{ documentos : origina
    tipos_documentos ||--o{ documentos : clasifica
    documentos ||--o{ destinatarios_documentos : distribuye
    unidades_organizacionales ||--o{ destinatarios_documentos : recibe_como_unidad
    usuarios ||--o{ destinatarios_documentos : recibe_como_persona
    destinatarios_documentos ||--o{ tareas_revision_documentos : asigna_revision
    documentos ||--o{ versiones_documentos : conserva
    versiones_documentos ||--o{ archivos_documentos : contiene
    documentos ||--o{ eventos_documentos : registra
    destinatarios_documentos ||--o{ eventos_documentos : contextualiza
    documentos ||--o{ observaciones_documentos : comenta
    documentos ||--o{ concesiones_acceso_documentos : autoriza
    usuarios ||--o{ concesiones_acceso_documentos : obtiene
    documentos ||--o{ registros_auditoria : audita
    usuarios ||--o{ registros_auditoria : ejecuta
    unidades_organizacionales ||--o{ contadores_documentos : numera
```

## 2. Identidad, organización y acceso
---

### `organizational_units` (`unidades`)
## 2. Catálogo Oficial de Tablas Físicas en Español

| Campo | Tipo conceptual | Regla |
| Tabla en Español | Entidad / Modelo Eloquent | Propósito Institucional |
|---|---|---|
| `id` | bigint | PK interna. |
| `public_id` | uuid | Único; se usa en URLs. |
| `parent_id` | FK nullable | Nulo para Facultad; carreras y Kardex dependen de la Facultad. |
| `type` | string/enum | `faculty`, `career`, `kardex`. |
| `name` | varchar(160) | Obligatorio. |
| `acronym` | varchar(20) | Único, normalizado en mayúsculas. |
| `description` | text nullable | Descripción institucional. |
| `is_active` | boolean | Índice para listados. |
| `created_at`, `updated_at` | timestamptz | Trazabilidad. |
| `deleted_at` | timestamptz nullable | Descarte lógico exclusivo de borradores nunca enviados. |
| `unidades_organizacionales` | `App\Models\OrganizationalUnit` | Estructura jerárquica: Facultad de Tecnología, Kardex y Carreras. |
| `usuarios` | `App\Models\User` | Cuentas institucionales de usuarios y funcionarios. |
| `roles` | `App\Models\Role` | Roles RBAC (Administrador, Jefe, Secretaria, Auxiliar, Extra, etc.). |
| `permisos` | `App\Models\Permission` | Permisos atómicos del sistema (`documents.approve`, etc.). |
| `modelo_tiene_roles` | Pivote Spatie Permission | Asignación de roles a usuarios. |
| `modelo_tiene_permisos` | Pivote Spatie Permission | Asignación directa de permisos extraordinarios. |
| `rol_tiene_permisos` | Pivote Spatie Permission | Matriz de permisos concedidos a cada rol. |
| `tipos_documentos` | `App\Models\DocumentType` | Catálogo de tipos documentales (Carta, Informe, Memorándum, etc.). |
| `contadores_documentos` | `App\Models\DocumentCounter` | Correlativos secuenciales atómicos por unidad y año. |
| `documentos` | `App\Models\Document` | Entidad principal de expedientes y trámites documentales. |
| `destinatarios_documentos` | `App\Models\DocumentRecipient` | Destinos de unidad o persona con estado y decisión independiente. |
| `tareas_revision_documentos` | `App\Models\DocumentReviewTask` | Tareas internas de revisión delegadas a secretaría o auxiliares. |
| `versiones_documentos` | `App\Models\DocumentVersion` | Historial de versiones e iteraciones ante correcciones/devoluciones. |
| `archivos_documentos` | `App\Models\DocumentFile` | Metadatos y hashes de archivos adjuntos en almacenamiento privado. |
| `observaciones_documentos` | `App\Models\DocumentObservation` | Anotaciones y observaciones internas entre revisores. |
| `concesiones_acceso_documentos` | `App\Models\DocumentAccessGrant` | Permisos explícitos de lectura y descarga para usuarios Extra. |
| `eventos_documentos` | `App\Models\DocumentEvent` | Trazabilidad documental inmutable (append-only). |
| `delegaciones_temporales` | `App\Models\TemporaryDelegation` | Transferencia temporal de facultades de firma de Jefatura. |
| `permisos_delegaciones_temporales`| Pivote Delegaciones | Capacidades específicas transferidas durante la delegación. |
| `registros_auditoria` | `App\Models\AuditLog` | Auditoría de seguridad y acciones transversales del sistema. |
| `notificaciones` | `App\Models\Notification` | Bandeja de avisos y alertas transaccionales en base de datos. |
| `sesiones` | Sesiones HTTP | Almacenamiento seguro de sesiones web activas. |

Restricciones: un solo registro de tipo `faculty`; un solo registro de tipo `kardex`; nombres y siglas únicos sin distinguir mayúsculas; las carreras y Kardex requieren una Facultad como padre; la FK restringe eliminar una unidad con descendientes. Las carreras se agregan sin modificar código y el modelo expone scopes para carreras, unidades activas y destinos futuros disponibles.
---

### `users`
## 3. Identidad, Organización y Acceso

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | bigint | PK. |
| `public_id` | uuid | Único para URLs. |
| `organizational_unit_id` | FK nullable | Nulo para ámbito global; requerido para roles de unidad. |
| `name` | varchar(255) | Nombre completo. |
| `email` | varchar(255) | Único sin distinguir mayúsculas, normalizado en minúsculas. |
| `password` | varchar | Hash, nunca auditable. |
| `status` | string/enum | `active`, `inactive`. |
| `email_verified_at` | timestamptz nullable | Preparado para verificación futura. |
| `last_login_at` | timestamptz nullable | Información de seguridad. |
| `remember_token` | varchar nullable | Convención Laravel. |
| `created_at`, `updated_at` | timestamptz | Trazabilidad. |
### `unidades_organizacionales`
- **Campos principales:** `id` (bigint PK), `public_id` (uuid), `parent_id` (FK nullable), `type` (faculty, career, kardex), `name` (varchar 160), `acronym` (varchar 20), `description` (text nullable), `is_active` (boolean), `singleton_type` (columna virtual generada STORED), `created_at`, `updated_at`, `deleted_at`.
- **Integridad y Unicidad en MySQL:**
  - Columna virtual generada `singleton_type = (CASE WHEN type IN ('faculty', 'kardex') THEN type ELSE NULL END) STORED` con índice único para garantizar que solo pueda existir una Facultad y un Kardex en todo el sistema.
  - Triggers MySQL `unidades_parent_type_trigger_ins` y `unidades_parent_type_trigger_upd` para validar la jerarquía institucional (las Carreras y el Kardex deben descender de la Facultad).
  - Nombres y siglas únicos insensibles a mayúsculas/minúsculas.

No se borran usuarios que participaron en trámites. El estado inactivo bloquea autenticación y conserva relaciones históricas. Administrador y Co-administrador tienen unidad nula; Jefe, Secretaria, Auxiliar y Extra requieren una carrera activa. La aplicación impide más de un Jefe principal activo por carrera mediante una transacción que bloquea la unidad.
### `usuarios`
- **Campos principales:** `id` (bigint PK), `public_id` (uuid), `organizational_unit_id` (FK nullable hacia `unidades_organizacionales`), `name` (varchar 255), `email` (varchar 255 unique), `password` (varchar hash), `status` (active, inactive), `last_login_at`, `remember_token`, timestamps.
- **Reglas:**
  - Clave foránea con `RESTRICT` para evitar la eliminación accidental de unidades con funcionarios asignados.
  - Usuarios inactivos quedan inhabilitados para inicio de sesión pero preservan la integridad histórica de sus firmas y trámites.
  - Administradores y Co-administradores tienen `organizational_unit_id = NULL` (ámbito global).

### RBAC (`roles`, `permissions` y pivotes)
### RBAC (`roles`, `permisos`, `modelo_tiene_roles`, `modelo_tiene_permisos`, `rol_tiene_permisos`)
- Implementado mediante `spatie/laravel-permission` con tablas mapeadas al español.
- Matriz institucional con roles predefinidos: Administrador, Co-administrador, Jefe de Carrera, Encargado de Kardex, Secretaria, Auxiliar y Extra.

Se usarán las tablas convencionales del paquete de permisos elegido en Fase 4: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. Los nombres de permisos serán acciones estables como `documents.approve`, no textos de interfaz.
### `delegaciones_temporales` y `permisos_delegaciones_temporales`
- **`delegaciones_temporales`:** `id`, `public_id`, `delegator_user_id` (FK `usuarios`), `delegate_user_id` (FK `usuarios`), `organizational_unit_id` (FK `unidades_organizacionales`), `authorized_by` (FK `usuarios`), `starts_at`, `ends_at`, `reason`, `status` (scheduled, active, revoked, expired), `revoked_by`, `revoked_at`, `revocation_reason`, timestamps.
- **`permisos_delegaciones_temporales`:** `temporary_delegation_id` (FK `delegaciones_temporales`), `permission_id` (FK `permisos`), `created_at`.
- Restricciones `CHECK`: `ends_at > starts_at`, coherencia en revocación y motivo obligatorio.

### `temporary_delegations`
---

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | bigint | PK. |
| `delegator_user_id` | FK users | Jefe titular activo cuya autoridad se delega; `RESTRICT`. |
| `delegate_user_id` | FK users | Usuario delegado. |
| `organizational_unit_id` | FK units | Debe ser carrera. |
| `authorized_by` | FK users | Actor autorizador. |
| `starts_at`, `ends_at` | timestamptz | `ends_at > starts_at`. |
| `reason` | text | Obligatorio. |
| `status` | enum | `scheduled`, `active`, `revoked`, `expired`. |
| `revoked_by`, `revoked_at`, `revocation_reason` | nullable | Revocación auditable. |
| `created_at`, `updated_at` | timestamptz | Trazabilidad. |
## 4. Núcleo Documental y Trámites

Índices: delegado+fechas, unidad+fechas y estado. La autorización efectiva exige intervalo vigente y no revocado; no depende de que un job cambie el estado a tiempo.
### `tipos_documentos`
- **Campos:** `id`, `public_id`, `name`, `code` (varchar 50 unique), `description` (text nullable), `is_active`, `created_by` (FK `usuarios`), timestamps.
- Creados con 12 tipos base institucionales (Carta, Informe, Memorándum, Circular, Solicitud, Resolución, Nota, Formulario, Certificación, Correspondencia, Documento de Kardex, Otros).

### `temporary_delegation_permissions`
### `contadores_documentos`
- **Campos:** `id`, `origin_unit_id` (FK `unidades_organizacionales`), `year` (smallint unsigned), `last_number` (int unsigned), timestamps.
- **Unicidad:** `UNIQUE (origin_unit_id, year)`.
- Bloqueo transaccional `lockForUpdate()` con incremento atómico para asegurar correlativos correlativos sin huecos ni colisiones.

Campos: `temporary_delegation_id`, `permission_id`, `created_at`. La clave primaria compuesta evita permisos duplicados y ambas FKs usan `RESTRICT`. Esta tabla concede únicamente las capacidades seleccionadas de un catálogo cerrado de Jefatura. No asigna roles ni permisos directos de Spatie; las Policies consultan la delegación efectiva con ámbito de carrera.
### `documentos`
- **Campos:** `id`, `public_id` (uuid), `code` (varchar 80 nullable unique), `document_type_id` (FK `tipos_documentos`), `origin_unit_id` (FK `unidades_organizacionales`), `created_by` (FK `usuarios`), `sender_user_id` (FK `usuarios` nullable), `subject` (varchar 255), `description` (text nullable), `general_status` (draft, sent, in_review, approved, rejected, returned, annulled), `priority` (normal, important, urgent), `confidentiality` (normal, restricted), `due_at` (datetime nullable), `sent_at` (datetime nullable), `annulled_at`, `annulled_by`, `annulment_reason`, `lock_version` (int), `deleted_at`, timestamps.
- Restricciones `CHECK`: estados, prioridades, confidencialidad y consistencia de anulación.
- SoftDeletes para borradores descartados que nunca fueron enviados oficialmente.

## 3. Catálogos documentales
### `destinatarios_documentos`
- **Campos:** `id`, `document_id` (FK `documentos`), `unit_id` (FK `unidades_organizacionales`), `user_id` (FK `usuarios` nullable), `requires_response` (boolean), `status` (prepared, sent, received, in_review, approved, rejected, returned), `received_by`, `received_at`, `current_handler_id`, `decided_by`, `decided_at`, `decision_reason`, `lock_version`, `target_user_id` (columna virtual generada STORED), `deleted_at`, timestamps.
- **Unicidad en MySQL:** Columna virtual generada `target_user_id = COALESCE(user_id, 0) STORED` con índice único sobre `(document_id, unit_id, target_user_id)` para impedir destinatarios duplicados sin que los valores `NULL` evadan la restricción única en MySQL.

### `document_types`
### `tareas_revision_documentos`
- **Campos:** `id`, `public_id`, `document_recipient_id` (FK `destinatarios_documentos`), `assignment_key` (uuid), `document_version_id` (FK `versiones_documentos`), `assigned_to` (FK `usuarios`), `assigned_by` (FK `usuarios`), `instructions` (text), `recommendation` (varchar 20 nullable), `message` (text nullable), `submitted_at`, `closed_at`, timestamps.
- Restricción `CHECK`: valida que al enviarse la recomendación pertenezca a `('approve', 'reject')` y cuente con mensaje de justificación no vacío.

Campos: `id`, `public_id`, `name`, `code` único y estable, `description` nullable, `is_active`, `created_by`, timestamps. Nombre y código son únicos sin distinguir mayúsculas en PostgreSQL. El código se genera al crear y permanece estable aunque se edite el nombre. Desactivar no rompe documentos históricos; el scope `availableForNewDocuments` excluye los registros inactivos de operaciones nuevas.
---

Los estados, prioridades y confidencialidad iniciales se implementarán como Enums PHP con cadenas en la base, no como catálogos editables, porque gobiernan lógica estable. Si la institución necesita flujos configurables en el futuro, se hará una migración deliberada.
## 5. Versiones, Archivos y Acceso Privado

## 4. Núcleo documental
### `versiones_documentos`
- **Campos:** `id`, `document_id` (FK `documentos`), `version_number` (int unsigned), `uploaded_by` (FK `usuarios`), `note` (text nullable), timestamps.
- **Unicidad:** `UNIQUE (document_id, version_number)`.

### `documents`
### `archivos_documentos`
- **Campos:** `id`, `public_id`, `document_version_id` (FK `versiones_documentos`), `disk` (varchar 50), `path` (varchar 500 unique), `original_name` (varchar 255), `stored_name` (varchar 255), `extension` (varchar 20), `detected_mime_type` (varchar 120), `size_bytes` (bigint unsigned), `sha256` (char 64), `uploaded_by` (FK `usuarios`), `created_at`.
- Restricciones `CHECK`: `size_bytes > 0` y validación de formato hexadecimal del SHA-256 (`REGEXP '^[0-9a-f]{64}$'`).

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | bigint | PK interna. |
| `public_id` | uuid | Único para URLs y enlaces. |
| `code` | varchar(80) nullable | Único y automático al enviar; nulo mientras sea borrador. |
| `document_type_id` | FK | Tipo activo al crear; permanece referenciado si se desactiva. |
| `origin_unit_id` | FK units | Carrera/Kardex/Facultad de origen. |
| `created_by` | FK users | Autor del registro. |
| `sender_user_id` | FK users nullable | Remitente efectivo; puede permanecer nulo en borrador. |
| `subject` | varchar(255) | Obligatorio e indexable. |
| `description` | text nullable | Contenido descriptivo. |
| `general_status` | enum string | Estado agregado de bandeja. |
| `priority` | enum string | `normal`, `important`, `urgent`. |
| `confidentiality` | enum string | `normal`, `restricted`. |
| `due_at` | timestamptz nullable | Fecha límite exacta. |
| `sent_at` | timestamptz nullable | Nulo mientras sea borrador. |
| `annulled_at`, `annulled_by`, `annulment_reason` | nullable | Solo para documentos ya enviados y anulados por Admin/Co-admin. |
| `lock_version` | integer | Control de concurrencia. |
| `created_at`, `updated_at` | timestamptz | Trazabilidad. |
### `concesiones_acceso_documentos`
- **Campos:** `id`, `document_id` (FK `documentos`), `user_id` (FK `usuarios`), `granted_by` (FK `usuarios`), `permission` (view, download), `starts_at`, `ends_at`, `revoked_at`, `revoked_by`, `revocation_reason`, `reason`, `is_active_grant` (columna virtual generada STORED), timestamps.
- Columna virtual generada `is_active_grant = (CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END) STORED` con índice único para permitir una sola concesión activa por documento, usuario y tipo de permiso.

Índices: código único; origen+estado+fecha; tipo; estado+prioridad; vencimiento parcial; creador; remitente. Para búsqueda textual se evaluará un índice PostgreSQL `GIN` cuando existan volúmenes representativos.
---

La Fase 15 reutiliza `due_at` sin agregar otro campo ni un estado documental. El índice parcial `documents_due_open_index` cubre documentos con fecha y estado no terminal. La condición se calcula en `America/La_Paz`: `sent`, `received`, `in_review` y `returned` requieren actuación; `draft`, `approved`, `rejected` y `annulled` no generan una alerta activa. Una corrección no modifica la fecha límite.
## 6. Historial, Auditoría y Notificaciones

Un borrador propio con `sent_at` y `code` nulos y sin dependencias operativas puede descartarse lógicamente. Queda oculto por el scope de `SoftDeletes`, mientras su fila y eventos permanecen. Una vez enviado, las restricciones impiden la eliminación ordinaria.
### `eventos_documentos`
- **Campos:** `id` (bigint auto-incremental), `document_id` (FK `documentos`), `document_recipient_id` (FK `destinatarios_documentos` nullable), `actor_id` (FK `usuarios` nullable), `event_type` (varchar 60), `from_status`, `to_status`, `reason`, `metadata` (JSON), `occurred_at` (datetime).
- Trazabilidad append-only del expediente institucional.

### `document_recipients`
### `registros_auditoria`
- **Campos:** `id` (bigint PK), `actor_id` (FK `usuarios` nullable), `action` (varchar 80), `auditable_type`, `auditable_id`, `ip_address` (varchar 45), `user_agent` (text nullable), `old_values` (JSON nullable), `new_values` (JSON nullable), `request_id` (char 36 nullable), `occurred_at` (datetime).
- Trazabilidad de seguridad global y cumplimiento normativo.

| Campo | Tipo conceptual | Regla |
|---|---|---|
| `id` | bigint | PK. |
| `document_id` | FK | Documento. |
| `unit_id` | FK | Unidad destinataria. |
| `user_id` | FK nullable | Nulo para destino a unidad; presente para persona específica. |
| `requires_response` | boolean | Distingue trámite de copia informativa. |
| `status` | enum string | `prepared` durante el borrador; estados formales desde el envío. |
| `received_by`, `received_at` | nullable | Recepción explícita. |
| `current_handler_id` | FK nullable | Responsable actual. |
| `decided_by`, `decided_at` | nullable | Decisión final. |
| `decision_reason` | text nullable | Obligatorio para devolución/rechazo. |
| `lock_version` | integer | Evita cambios concurrentes obsoletos. |
| `deleted_at` | timestamptz nullable | Retiro lógico permitido únicamente en `prepared`. |
| timestamps | timestamptz | Trazabilidad. |

Restricción única por documento+unidad+usuario (tratando nulo de forma segura en PostgreSQL). Un destinatario personal conserva la relación real con su usuario y registra la unidad activa derivada del usuario durante la preparación. Jefe y Secretaria pueden ser destinos personales formales; Auxiliar únicamente copia informativa; Extra queda fuera del flujo normal y utiliza concesiones explícitas. Esta elegibilidad se valida al preparar y nuevamente al enviar. Una unidad y una persona de esa misma unidad pueden coexistir porque representan destinos distintos. La unidad de origen puede utilizarse también como destino para trámites internos.

### `document_counters`

Campos: `id`, `origin_unit_id`, `year`, `last_number`, timestamps. Restricción única `(origin_unit_id, year)`. Se actualiza con bloqueo de fila dentro de transacción.

Desde la Fase 11, el contador se crea de forma idempotente y se bloquea antes de incrementar. El código usa las siglas persistidas de Facultad y unidad, el año y un correlativo relleno inicialmente a seis dígitos. La asignación ocurre únicamente al enviar y se revierte junto con la transacción si el envío falla.

## 5. Versiones, archivos y observaciones

### `document_versions`

Campos: `id`, `document_id`, `version_number`, `uploaded_by`, `note` nullable, `created_at`. Único `(document_id, version_number)`. En la Fase 10 todos los archivos preliminares comparten la versión inicial `1`; si se retira su último archivo mientras el documento continúa en borrador, el contenedor vacío también se elimina. La Fase 13 crea versiones posteriores completas únicamente ante una devolución formal. La versión vigente se deriva con `MAX(version_number)` y las versiones ya enviadas son inmutables por el flujo ordinario.

### `document_files`

Campos: `id`, `public_id`, `document_version_id`, `disk`, `path`, `original_name`, `stored_name`, `extension`, `detected_mime_type`, `size_bytes`, `sha256`, `uploaded_by`, `created_at`.

`path` nunca se expone como URL pública. El nombre físico es un UUID y no depende del nombre proporcionado por el usuario. Índices en versión y hash. La coincidencia de hash no impide adjuntar el mismo contenido, pero ayuda a integridad y análisis. La carga y el retiro preliminar usan compensación entre filesystem y transacción de base de datos para evitar archivos huérfanos o metadatos sin archivo.

### `document_observations`

Campos: `id`, `document_id`, `recipient_id` nullable, `author_id`, `visibility` (`internal`, `participants`), `body`, `created_at`, `updated_at` nullable. Las observaciones oficiales ligadas a decisiones también quedan congeladas en el evento de estado.

### `document_access_grants`

Campos: `id`, `document_id`, `user_id`, `granted_by`, `permission` (`view`, `download`), `starts_at` nullable, `ends_at` nullable, `revoked_at` nullable, `revoked_by` nullable, `revocation_reason` nullable, `reason`, timestamps. Único lógico para concesiones activas equivalentes.

Para usuarios Extra, la concesión solo puede ser creada por Admin, Co-admin, Jefe de la unidad relacionada o Secretaria que posea el permiso de concesión en esa unidad. La vigencia se evalúa en cada consulta/descarga. Esta misma estructura soporta las autorizaciones explícitas necesarias para otros usuarios en documentos confidenciales.

## 6. Historial, auditoría y notificaciones

### `document_events`

Campos: `id` bigint secuencial, `document_id`, `recipient_id` nullable, `actor_id` nullable, `event_type`, `from_status` nullable, `to_status` nullable, `reason` nullable, `metadata` jsonb filtrado, `occurred_at` timestamptz.

Es append-only. No incluye secretos, contraseñas, rutas físicas, hashes ni contenido binario. Índices `(document_id, occurred_at, id)` y actor+fecha. La Fase 12 centraliza su escritura en `DocumentEventService`; las relaciones históricas a documentos y destinatarios retirados se resuelven explícitamente con `withTrashed()`.

### `audit_logs`

Campos: `id`, `actor_id` nullable, `action`, `auditable_type`, `auditable_id`, `ip_address` (`inet` en PostgreSQL), `user_agent` nullable, `old_values` jsonb nullable, `new_values` jsonb nullable, `request_id` uuid, `occurred_at` timestamptz.

Es append-only y visible solo con permiso de auditoría. Se aplicará una lista explícita de campos auditables para excluir credenciales y tokens.

### `notifications`

Se usa la tabla oficial de notificaciones de Laravel (`uuid`, `type`, `notifiable`, `data`, `read_at`, timestamps), sin tabla paralela. `data` conserva título, mensaje breve, `document_public_id`, código cuando no es restringido, tipo y fecha del evento, además del nombre y parámetros de una ruta relativa. Nunca guarda una URL absoluta.

La Fase 14 genera avisos del flujo desde un evento de dominio que implementa `ShouldDispatchAfterCommit`. La Fase 15 agrega recordatorios `document_due_soon` y `document_overdue` mediante el mismo canal `database`. La resolución de destinatarios admite destino personal y destino por unidad, conserva solo usuarios activos capaces de actuar y deduplica por usuario. Una notificación no participa en la autorización: abrir el enlace vuelve a ejecutar `DocumentPolicy`.

La propia fila de notificación funciona como marcador del hito. Su UUID v5 se deriva de `document_public_id + user_public_id + due_at + milestone`; la PK aporta unicidad PostgreSQL sin una tabla adicional. La creación ocurre dentro de una transacción que bloquea el documento, por lo que la notificación y el marcador son una sola escritura consistente.

Para documentos restringidos, el contenido persistido es genérico; se omiten asunto, motivo y código. El canal inicial es exclusivamente `database`, aunque las clases permanecen separadas por intención para incorporar `mail` en una fase futura.

## 7. Tareas (módulo futuro)

`tasks` y `task_assignments` quedan fuera de las primeras fases. Su diseño no condicionará las tablas documentales. Solo se crearán cuando se apruebe el módulo secundario.

## 8. Cardinalidades principales

- Unidad 1 — N usuarios.
- Usuario N — N roles.
- Rol N — N permisos.
- Unidad/usuario 1 — N delegaciones según su función.
- Delegación N — N permisos temporales.
- Unidad 1 — N documentos originados.
- Documento N — 1 tipo documental.
- Documento 1 — N destinatarios.
- Documento 1 — N versiones.
- Versión 1 — N archivos.
- Documento 1 — N eventos, observaciones y concesiones.
- Usuario 1 — N acciones de auditoría.

## 9. Integridad y concurrencia

- Todas las claves foráneas tienen índices.
- Los cambios de estado verifican `lock_version` y bloquean destinatario/documento durante la transacción.
- Crear y enviar documento guarda documento, destinatarios, versión, archivos y eventos en una unidad atómica; los archivos se preparan antes y se limpian si la transacción falla.
- El evento de notificación queda pendiente durante la transacción y su listener persiste el aviso solo después del commit exterior. Un rollback descarta tanto el cambio documental como el evento pendiente.
- Los campos de devolución, rechazo y anulación tienen constraints coherentes con estado cuando PostgreSQL permita expresarlos con claridad.
- No se usan listas separadas por comas ni IDs confiados desde el navegador.

## 10. Decisiones institucionales pendientes al cierre

1. El formato técnico de código ya está implementado; antes de cargar carreras reales deben confirmarse sus nombres y siglas oficiales.
2. Debe aprobarse una política institucional de retención, privacidad y clasificación de información personal antes de abrir producción.

### `notificaciones`
- Mapeada al modelo `App\Models\Notification` sobre la tabla `notificaciones`.
- Almacena avisos internos del flujo documental, vencimientos y tareas de revisión.
