# Fase 2 — Base de datos inicial
# Fase 2 — Base de Datos Institucional (MySQL)

> **Estado:** implementada y validada el 8 de octubre de 2026 sobre PostgreSQL 18.3.
> **Estado:** Implementada y validada sobre **MySQL 8.4+ (Laragon)** con tablas nombradas en español y codificación `utf8mb4_unicode_ci`.

---

## 1. Objetivo

Implementar el esquema relacional inicial aprobado en la Fase 0, con integridad referencial, restricciones de negocio, índices para consultas y concurrencia, trazabilidad separada y compatibilidad real con PostgreSQL.
Implementar el esquema relacional institucional en **MySQL**, con integridad referencial completa, restricciones de negocio, índices de cobertura para consultas y concurrencia transaccional, trazabilidad separada y todas las **tablas del dominio con nombres en español**.

No se ejecutaron `migrate:fresh`, `db:wipe`, `schema:drop` ni otras operaciones destructivas. Todas las migraciones se aplicaron incrementalmente.
---

## 2. Conexión verificada
## 2. Conexión Verificada

| Elemento | Resultado |
| Elemento | Configuración / Resultado |
|---|---|
| Driver efectivo de Laravel | `pgsql` |
| Driver de Laravel | `mysql` |
| Base de datos | `sistema_teletrabajo` |
| Servidor | PostgreSQL 18.3 |
| Esquema | `public` |
| SQLite principal | No utilizado |
| Migraciones | 14 ejecutadas |
| Servidor | MySQL 8.4 (Laragon local) |
| Puerto | `3306` |
| Juego de caracteres / Collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Motor de almacenamiento | `InnoDB` |
| Migraciones aplicadas | 20 ejecutadas exitosamente |

La contraseña permanece únicamente en `.env`; no está registrada en documentación ni control de versiones.
La contraseña permanece únicamente en el archivo `.env` local; nunca se sube al repositorio ni al control de versiones.

## 3. Migraciones implementadas
---

| Migración | Responsabilidad |
|---|---|
| `0001_01_01_000000_create_users_table.php` | Usuarios, recuperación y sesiones, con timestamps con zona. |
| `0001_01_01_000001_create_cache_table.php` | Cache y bloqueos de cache. |
| `0001_01_01_000002_create_jobs_table.php` | Colas, lotes y fallos. |
| `2026_10_08_000100_create_organizational_units_table.php` | Facultad, carreras y Kardex en una jerarquía común. |
| `2026_10_08_000200_add_institutional_fields_to_users_table.php` | UUID público, unidad, estado y último ingreso. |
| `2026_10_08_000300_create_document_catalog_tables.php` | Tipos documentales y correlativos por unidad/año. |
| `2026_10_08_000400_create_documents_table.php` | Núcleo documental, borrador, estado, prioridad, confidencialidad y anulación. |
| `2026_10_08_000500_create_document_recipients_table.php` | Estados independientes, recepción y decisión por destinatario. |
| `2026_10_08_000600_create_document_version_tables.php` | Versiones inmutables y metadatos seguros de archivos. |
| `2026_10_08_000700_create_document_collaboration_tables.php` | Observaciones y autorizaciones explícitas de acceso. |
| `2026_10_08_000800_create_traceability_tables.php` | Historial documental y auditoría general separados. |
| `2026_10_08_000900_create_temporary_delegations_table.php` | Vigencia y revocación de delegaciones temporales. |
| `2026_10_08_001000_create_notifications_table.php` | Notificaciones internas de Laravel. |
| `2026_10_08_001100_add_foreign_key_supporting_indexes.php` | Índices de soporte para todas las claves foráneas. |
## 3. Catálogo Oficial de Tablas en Español

## 4. Tablas de dominio
| Tabla en Español | Tabla Anterior (Inglés) | Modelo Eloquent / Componente |
|---|---|---|
| `unidades_organizacionales` | `organizational_units` | `App\Models\OrganizationalUnit` |
| `usuarios` | `users` | `App\Models\User` |
| `tipos_documentos` | `document_types` | `App\Models\DocumentType` |
| `contadores_documentos` | `document_counters` | `App\Models\DocumentCounter` |
| `documentos` | `documents` | `App\Models\Document` |
| `destinatarios_documentos` | `document_recipients` | `App\Models\DocumentRecipient` |
| `tareas_revision_documentos` | `document_review_tasks` | `App\Models\DocumentReviewTask` |
| `versiones_documentos` | `document_versions` | `App\Models\DocumentVersion` |
| `archivos_documentos` | `document_files` | `App\Models\DocumentFile` |
| `observaciones_documentos` | `document_observations` | `App\Models\DocumentObservation` |
| `concesiones_acceso_documentos` | `document_access_grants` | `App\Models\DocumentAccessGrant` |
| `eventos_documentos` | `document_events` | `App\Models\DocumentEvent` |
| `delegaciones_temporales` | `temporary_delegations` | `App\Models\TemporaryDelegation` |
| `permisos_delegaciones_temporales`| `temporary_delegation_permissions` | Tabla pivote de delegaciones |
| `registros_auditoria` | `audit_logs` | `App\Models\AuditLog` |
| `roles` | `roles` | `App\Models\Role` |
| `permisos` | `permissions` | `App\Models\Permission` |
| `modelo_tiene_roles` | `model_has_roles` | Pivote Spatie Permission |
| `modelo_tiene_permisos` | `model_has_permissions` | Pivote Spatie Permission |
| `rol_tiene_permisos` | `role_has_permissions` | Pivote Spatie Permission |
| `notificaciones` | `notifications` | `App\Models\Notification` |
| `sesiones` | `sessions` | Sesiones HTTP en base de datos |

- `organizational_units`
- `users`
- `document_types`
- `document_counters`
- `documents`
- `document_recipients`
- `document_versions`
- `document_files`
- `document_observations`
- `document_access_grants`
- `document_events`
- `audit_logs`
- `temporary_delegations`
- `notifications`
Tablas internas de framework Laravel para cache y colas (`cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`) se mantienen con convención estándar.

Además se conservan las tablas técnicas oficiales de Laravel para sesiones, recuperación, cache y colas.
---

## 5. Integridad implementada
## 4. Adaptaciones Técnicas de Integridad en MySQL

### Unidades
### Unicidad sin Índices Parciales (Columnas Virtuales Generadas)
MySQL no admite la cláusula `WHERE` dentro de índices `CREATE UNIQUE INDEX`. Para resolver los requerimientos de negocio se implementaron columnas virtuales generadas y almacenadas (`GENERATED ALWAYS AS (...) STORED`):
- **Facultad y Kardex Únicos:** Columna `singleton_type = (CASE WHEN type IN ('faculty', 'kardex') THEN type ELSE NULL END) STORED` con índice único en `unidades_organizacionales`.
- **Destinatarios sin duplicados:** En MySQL, los valores `NULL` no colisionan en índices únicos estándar. Se implementó `target_user_id = COALESCE(user_id, 0) STORED` con índice único sobre `(document_id, unit_id, target_user_id)` en `destinatarios_documentos`.
- **Concesión de acceso activa única:** Columna `is_active_grant = (CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END) STORED` en `concesiones_acceso_documentos`.

- Tipos permitidos: `faculty`, `career`, `kardex`.
- Un único registro de Facultad y un único Kardex mediante índice parcial.
- Nombre y sigla únicos sin distinguir mayúsculas/minúsculas.
- No se eliminan unidades referenciadas.
### Jerarquía Institucional (Triggers MySQL)
Para evitar ciclos o jerarquías inválidas (carreras asignadas como padre de la Facultad o dependencias cruzadas), se implementaron los triggers `unidades_parent_type_trigger_ins` y `unidades_parent_type_trigger_upd` en MySQL con `SIGNAL SQLSTATE '45000'`.

### Usuarios
### Longitud de Identificadores
MySQL restringe los nombres de tablas, columnas e índices a **64 caracteres**. Todos los nombres generados de índices compuestos y llaves foráneas se acotaron explícitamente para cumplir esta regla en InnoDB.

- UUID público separado de la clave bigint interna.
- Estados limitados a `active` e `inactive`.
- Correo único sin distinguir mayúsculas/minúsculas.
- Unidad opcional para permitir ámbito global de Admin/Co-admin.
- Relación con unidad protegida mediante `RESTRICT`.
---

### Documentos
## 5. Carga Inicial y Seeders

- Código único y nulo mientras sea borrador.
- Un documento no puede salir de borrador sin código y fecha de envío.
- Estados, prioridades y confidencialidad restringidos a valores aprobados.
- Anulación válida solo con actor, fecha y motivo.
- `lock_version` no negativo para control de concurrencia.
- Índice parcial para documentos abiertos con fecha límite.

### Destinatarios

- Destinatario por unidad o persona con `requires_response`.
- Unicidad `NULLS NOT DISTINCT` para impedir duplicar un destino de unidad.
- Recepción exige usuario y fecha conjuntamente.
- Devolución/rechazo exige decisión y motivo.
- Copias informativas no pueden alcanzar estados de decisión formal.

### Versiones y archivos

- Número de versión único por documento y mayor o igual a uno.
- Ruta interna única por disco.
- Tamaño positivo.
- Hash SHA-256 validado con 64 caracteres hexadecimales.
- Relaciones con `RESTRICT`; una versión histórica no desaparece por cascada.

### Acceso, historial y auditoría

- Concesiones limitadas a `view` y `download`.
- Una sola concesión activa equivalente por documento, usuario y permiso.
- Vigencias y revocaciones coherentes.
- Eventos documentales separados de auditoría general.
- `metadata`, `old_values` y `new_values` utilizan `jsonb`.
- Direcciones de auditoría utilizan el tipo PostgreSQL `inet`.
- Fechas relevantes utilizan `timestamp with time zone`.

### Delegaciones

- Intervalo final posterior al inicial.
- Estados limitados a `scheduled`, `active`, `revoked`, `expired`.
- Revocación exige actor, fecha y motivo.
- La tabla puente con permisos se agregará en la Fase 19, una vez que la Fase 4 haya creado el catálogo RBAC. No se adelanta el módulo de permisos.

## 6. Resumen verificado en PostgreSQL

- 22 tablas totales, incluyendo infraestructura Laravel.
- 34 claves foráneas.
- 29 restricciones `CHECK`.
- 15 restricciones únicas.
- 22 claves primarias.
- 0 claves foráneas con `ON DELETE CASCADE`.
- Todas las claves foráneas tienen un índice de soporte.

## 7. Modelos base

- `OrganizationalUnit` genera UUID público, usa `public_id` para rutas y expone la relación con usuarios.
- `User` genera UUID público, pertenece opcionalmente a una unidad y convierte fechas/contraseña mediante casts seguros.
- El seeder general ya no crea una cuenta de prueba con contraseña conocida. Los seeders explícitos se incorporarán en sus fases funcionales.

## 8. Pruebas

### Suite aislada

El comando institucional de migración y siembra:
```bash
php artisan test
php artisan migrate:fresh --seed
```
Carga limpiamente:
1. **Roles y Permisos:** 7 roles institucionales y 42 permisos RBAC.
2. **11 Unidades Institucionales:**
   - 1 Facultad: Facultad de Tecnología (`FTEC`).
   - 1 Kardex: Kardex de la Facultad de Tecnología (`KARDEX`).
   - 9 Carreras oficiales de la Facultad.
3. **12 Tipos de Documentos Oficiales:** Carta, Informe, Memorándum, Circular, Solicitud, Resolución, Nota, Formulario, Certificación, Correspondencia, Documento de Kardex y Otros.
4. **0 Usuarios:** Base de datos limpia sin usuarios demo de prueba.

Resultado: 10 pruebas y 35 aserciones aprobadas.
---

### Integración PostgreSQL
## 6. Creación de la Primera Cuenta de Administrador

Para dar de alta el primer usuario del sistema se ejecuta:
```bash
vendor/bin/phpunit --configuration phpunit.postgresql.xml
php artisan app:create-initial-admin
```

Resultado: 7 pruebas y 21 aserciones aprobadas. Esta suite no limpia ni recrea la base; usa consultas y una transacción revertida para comprobar una restricción real.

### Estado de migraciones

```bash
php artisan migrate:status
```

Resultado: las 14 migraciones aparecen como `Ran`.

## 9. Advertencia de entorno

La extensión PHP `intl` no está habilitada. No afecta PDO/PostgreSQL, migraciones ni pruebas, pero el comando visual `php artisan db:table` la requiere para formatear cifras. La validación equivalente se realizó directamente contra los catálogos de PostgreSQL. Se recomienda habilitar `intl` antes de las fases de interfaz y reportes.

El entorno mantiene más de una instalación de PHP. Al cierre de la Fase 24, el ejecutable canónico del proyecto es `C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe`: carga `PDO`, `pdo_pgsql` y `pgsql` y ejecuta correctamente tanto Artisan como la suite PostgreSQL. La versión realmente validada en el cierre técnico es PHP 8.3.28.

## 10. Límites de la fase

- No se instalaron ni crearon roles/permisos; corresponden a la Fase 4.
- No se creó `temporary_delegation_permissions`; depende del catálogo de permisos y corresponde a la Fase 19.
- No se agregaron tareas, correo, firma digital ni integraciones futuras.
- No se crearon datos institucionales definitivos ni usuarios de producción.
- La Fase 3 no ha comenzado.

El comando solicitará de forma interactiva nombre, correo institucional y contraseña segura con confirmación.
