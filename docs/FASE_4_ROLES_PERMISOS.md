# Fase 4 — Roles y permisos

> **Estado:** implementada y validada el 8 de octubre de 2026.

## 1. Alcance completado

Se implementó un RBAC real con seis roles institucionales, 42 permisos atómicos, asignaciones idempotentes y comprobación backend mediante Gate y middleware. No se implementaron CRUD, documentos, dashboards por rol ni Policies de ámbito.

## 2. Dependencia seleccionada

Se instaló `spatie/laravel-permission` 8.3.0.

La versión 8 declara compatibilidad con Laravel 12/13 y PHP 8.3+. La aplicación usa Laravel 13, PHP 8.3.33, clave primaria numérica en `users`, guard `web` y una tabla de caché existente, por lo que cumple los requisitos. PostgreSQL funciona mediante las relaciones y migraciones estándar de Laravel.

Ventajas utilizadas:

- integración automática con Laravel Gate y `$user->can()`;
- trait `HasRoles` sobre el modelo `User` existente;
- middleware oficiales `role`, `permission` y `role_or_permission`;
- caché de permisos con invalidación oficial;
- sincronización segura de permisos de cada rol;
- soporte para permisos directos que podrá extenderse con vigencia y ámbito en la Fase 19.

No se habilitaron permisos comodín, teams ni una excepción de superadministrador.

Fuentes evaluadas:

- https://spatie.be/docs/laravel-permission/v8/prerequisites
- https://github.com/spatie/laravel-permission/blob/main/composer.json

## 3. Modelo de datos

La migración incremental `2026_10_08_001200_create_permission_tables` agregó:

- `roles`: slug, nombre visible en español, descripción y guard;
- `permissions`: slug, nombre visible en español y guard;
- `model_has_roles`: relación polimórfica usuario/rol;
- `model_has_permissions`: permisos directos, disponibles para extensiones futuras;
- `role_has_permissions`: matriz rol/permiso.

PostgreSQL valida el formato de los slugs mediante restricciones `CHECK`. Las eliminaciones en cascada solo existen entre catálogos RBAC y sus tablas pivote, donde evitan asignaciones huérfanas. No se añadió cascada a información documental.

## 4. Separación entre capacidad y ámbito

El catálogo responde qué puede hacer un usuario. No incluye una unidad dentro del nombre de permiso ni utiliza la opción `teams` del paquete como sustituto de las unidades institucionales.

Las Policies de fases posteriores combinarán permiso con `organizational_unit_id`, participación, destinatarios, confidencialidad, concesiones de acceso y delegaciones vigentes. Por ello, `documents.approve` nunca significa automáticamente “aprobar cualquier documento”.

## 5. Catálogo y matriz

Los slugs y etiquetas se centralizan en:

- `App\Enums\RoleName`;
- `App\Enums\PermissionName`;
- `App\Support\RbacCatalog`.

La asignación exacta de los seis roles está documentada en `docs/seguridad/MATRIZ_PERMISOS.md` y es la misma fuente que consumen los seeders y las pruebas.

Resumen:

| Rol | Permisos | Restricción principal futura |
|---|---:|---|
| Administrador | 42 | Reglas para operaciones extremadamente sensibles. |
| Co-administrador | 42 | No operar sobre Administrador protegido cuando se modele esa condición. |
| Jefe de Carrera | 29 | Solo su unidad y recursos relacionados. |
| Secretaria | 18 | Solo su ámbito y estados válidos. |
| Auxiliar de Secretaría | 8 | Sin aprobación, devolución, rechazo o anulación. |
| Extra | 2 | Solo documentos concedidos explícitamente. |

## 6. Seeders

- `PermissionSeeder`: actualiza o crea permisos por slug y guard.
- `RoleSeeder`: actualiza o crea roles por slug y guard.
- `RolePermissionSeeder`: sincroniza la matriz exacta de cada rol.
- `AuthorizationSeeder`: coordina los anteriores y limpia la caché antes y después.
- `DatabaseSeeder`: ejecuta el seeder de autorización sin crear usuarios.

El seeder se ejecutó dos veces en PostgreSQL. Los conteos permanecieron en 6 roles, 42 permisos y 141 asignaciones, confirmando idempotencia.

## 7. Verificación backend

El modelo `User` incorpora `HasRoles`. Se registraron los middleware oficiales:

```php
Route::middleware('permission:roles.manage')->group(function () {
    // Rutas administrativas futuras.
});
```

También se puede usar Gate sin comparar roles ni identificadores:

```php
abort_unless($user->can('documents.approve'), 403);
```

Las pruebas crean una ruta temporal protegida: Administrador obtiene `200` y Extra obtiene `403`. Esa ruta no existe en producción.

## 8. Pruebas realizadas

### Suite completa

```bash
php artisan test
```

Resultado: 30 pruebas y 125 aserciones aprobadas.

### Pruebas RBAC en memoria

```bash
php artisan test tests/Feature/AuthorizationTest.php
```

Resultado: 11 pruebas y 41 aserciones aprobadas.

### Integración PostgreSQL

```bash
php vendor/bin/phpunit --configuration phpunit.postgresql.xml
```

Resultado: 14 pruebas y 45 aserciones aprobadas. Incluye esquema, idempotencia, asignación por slug, Gate, restricciones PostgreSQL y confirmación de cascadas limitadas a pivotes RBAC.

`composer validate --strict`, Laravel Pint, la compilación frontend y `composer audit` finalizaron correctamente. Composer no reportó vulnerabilidades conocidas.

## 9. Archivos creados

- `app/Enums/RoleName.php`
- `app/Enums/PermissionName.php`
- `app/Support/RbacCatalog.php`
- `app/Models/Role.php`
- `app/Models/Permission.php`
- `config/permission.php`
- `database/migrations/2026_10_08_001200_create_permission_tables.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `database/seeders/AuthorizationSeeder.php`
- `tests/Feature/AuthorizationTest.php`
- `tests/PostgreSQL/PostgreSqlAuthorizationTest.php`
- `docs/FASE_4_ROLES_PERMISOS.md`

## 10. Archivos modificados

- `composer.json` y `composer.lock`
- `app/Models/User.php`
- `bootstrap/app.php`
- `database/seeders/DatabaseSeeder.php`
- `tests/PostgreSQL/PostgreSqlSchemaTest.php`
- `docs/seguridad/MATRIZ_PERMISOS.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 11. Límites de la fase

- No se crearon usuarios institucionales ni contraseñas conocidas.
- No se implementaron CRUD de usuarios, carreras o Kardex.
- No se implementaron documentos, flujo, reportes ni auditoría funcional.
- No se implementaron Policies documentales completas.
- No se implementó el módulo de delegación temporal.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 5 no ha comenzado.
