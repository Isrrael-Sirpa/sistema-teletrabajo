# Fase 2 — Base de Datos Institucional (MySQL)

> **Estado:** Implementada y validada sobre **MySQL 8.4+ (Laragon)** con tablas nombradas en español y codificación `utf8mb4_unicode_ci`.

---

## 1. Objetivo

Implementar el esquema relacional institucional en **MySQL**, con integridad referencial completa, restricciones de negocio, índices de cobertura para consultas y concurrencia transaccional, trazabilidad separada y todas las **tablas del dominio con nombres en español**.

---

## 2. Conexión Verificada

| Elemento | Configuración / Resultado |
|---|---|
| Driver de Laravel | `mysql` |
| Base de datos | `sistema_teletrabajo` |
| Servidor | MySQL 8.4 (Laragon local) |
| Puerto | `3306` |
| Juego de caracteres / Collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Motor de almacenamiento | `InnoDB` |
| Migraciones aplicadas | 20 ejecutadas exitosamente |

La contraseña permanece únicamente en el archivo `.env` local; nunca se sube al repositorio ni al control de versiones.

---

## 3. Catálogo Oficial de Tablas en Español

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

Tablas internas de framework Laravel para cache y colas (`cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`) se mantienen con convención estándar.

---

## 4. Adaptaciones Técnicas de Integridad en MySQL

### Unicidad sin Índices Parciales (Columnas Virtuales Generadas)
MySQL no admite la cláusula `WHERE` dentro de índices `CREATE UNIQUE INDEX`. Para resolver los requerimientos de negocio se implementaron columnas virtuales generadas y almacenadas (`GENERATED ALWAYS AS (...) STORED`):
- **Facultad y Kardex Únicos:** Columna `singleton_type = (CASE WHEN type IN ('faculty', 'kardex') THEN type ELSE NULL END) STORED` con índice único en `unidades_organizacionales`.
- **Destinatarios sin duplicados:** En MySQL, los valores `NULL` no colisionan en índices únicos estándar. Se implementó `target_user_id = COALESCE(user_id, 0) STORED` con índice único sobre `(document_id, unit_id, target_user_id)` en `destinatarios_documentos`.
- **Concesión de acceso activa única:** Columna `is_active_grant = (CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END) STORED` en `concesiones_acceso_documentos`.

### Jerarquía Institucional (Triggers MySQL)
Para evitar ciclos o jerarquías inválidas (carreras asignadas como padre de la Facultad o dependencias cruzadas), se implementaron los triggers `unidades_parent_type_trigger_ins` y `unidades_parent_type_trigger_upd` en MySQL con `SIGNAL SQLSTATE '45000'`.

### Longitud de Identificadores
MySQL restringe los nombres de tablas, columnas e índices a **64 caracteres**. Todos los nombres generados de índices compuestos y llaves foráneas se acotaron explícitamente para cumplir esta regla en InnoDB.

---

## 5. Carga Inicial y Seeders

El comando institucional de migración y siembra:
```bash
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

---

## 6. Creación de la Primera Cuenta de Administrador

Para dar de alta el primer usuario del sistema se ejecuta:
```bash
php artisan app:create-initial-admin
```
El comando solicitará de forma interactiva nombre, correo institucional y contraseña segura con confirmación.
