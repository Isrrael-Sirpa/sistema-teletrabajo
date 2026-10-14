# Fase 2 — Base de datos inicial

> **Estado:** implementada y validada el 8 de octubre de 2026 sobre PostgreSQL 18.3.

## 1. Objetivo

Implementar el esquema relacional inicial aprobado en la Fase 0, con integridad referencial, restricciones de negocio, índices para consultas y concurrencia, trazabilidad separada y compatibilidad real con PostgreSQL.

No se ejecutaron `migrate:fresh`, `db:wipe`, `schema:drop` ni otras operaciones destructivas. Todas las migraciones se aplicaron incrementalmente.

## 2. Conexión verificada

| Elemento | Resultado |
|---|---|
| Driver efectivo de Laravel | `pgsql` |
| Base de datos | `sistema_teletrabajo` |
| Servidor | PostgreSQL 18.3 |
| Esquema | `public` |
| SQLite principal | No utilizado |
| Migraciones | 14 ejecutadas |

La contraseña permanece únicamente en `.env`; no está registrada en documentación ni control de versiones.

## 3. Migraciones implementadas

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

## 4. Tablas de dominio

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

Además se conservan las tablas técnicas oficiales de Laravel para sesiones, recuperación, cache y colas.

## 5. Integridad implementada

### Unidades

- Tipos permitidos: `faculty`, `career`, `kardex`.
- Un único registro de Facultad y un único Kardex mediante índice parcial.
- Nombre y sigla únicos sin distinguir mayúsculas/minúsculas.
- No se eliminan unidades referenciadas.

### Usuarios

- UUID público separado de la clave bigint interna.
- Estados limitados a `active` e `inactive`.
- Correo único sin distinguir mayúsculas/minúsculas.
- Unidad opcional para permitir ámbito global de Admin/Co-admin.
- Relación con unidad protegida mediante `RESTRICT`.

### Documentos

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

```bash
php artisan test
```

Resultado: 10 pruebas y 35 aserciones aprobadas.

### Integración PostgreSQL

```bash
vendor/bin/phpunit --configuration phpunit.postgresql.xml
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

