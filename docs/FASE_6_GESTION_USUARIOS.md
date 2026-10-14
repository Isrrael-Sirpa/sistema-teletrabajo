# Fase 6 — Gestión de usuarios

> **Estado:** implementada y validada el 9 de octubre de 2026.

## 1. Alcance completado

Se implementó la administración institucional de usuarios integrando la autenticación, el RBAC y las unidades organizacionales existentes. El módulo permite listar, buscar, filtrar, consultar, crear, editar, activar, desactivar y restablecer contraseñas sin eliminar cuentas ni exponer contraseñas existentes.

No se implementaron documentos, auditoría funcional completa, delegaciones temporales ni dashboards definitivos.

## 2. Modelo aplicado

La Fase 6 no necesitó una migración. La tabla `users` ya disponía de:

- `organizational_unit_id` nullable con FK restrictiva;
- `status` limitado a `active` e `inactive`;
- `public_id` UUID para URLs;
- índice por unidad y estado;
- índice PostgreSQL único `users_email_ci_unique` sobre `LOWER(email)`.

Se conservaron todas las migraciones anteriores sin cambios. Las reglas de rol y ámbito se aplican mediante Form Requests, `UserPolicy` y `UserManagementService`; la base de datos continúa siendo la última barrera para correo, estado y referencias válidas.

## 3. Rol, unidad y permiso

El módulo mantiene separados los tres conceptos:

- **rol:** función institucional;
- **unidad:** lugar donde trabaja;
- **permiso:** acción que puede ejecutar.

Reglas implementadas:

| Rol | Unidad | Gestión permitida |
|---|---|---|
| Administrador | Nula, ámbito global | Puede crear los seis roles y gestionar globalmente. |
| Co-administrador | Nula, ámbito global | Gestiona globalmente salvo cuentas Administrador; no puede crear Administradores. |
| Jefe de Carrera | Carrera activa obligatoria | Gestiona Secretaria, Auxiliar y Extra únicamente en su propia carrera. |
| Secretaria | Carrera activa obligatoria | Sin gestión de usuarios. |
| Auxiliar de Secretaría | Carrera activa obligatoria | Sin gestión de usuarios. |
| Extra | Carrera activa obligatoria | Sin gestión de usuarios. |

Kardex y Facultad no se admiten para roles de carrera. No se crearon roles nuevos para Kardex.

## 4. Protección de cuentas sensibles

No se introdujo un `user_id` especial ni una marca rígida de “Administrador principal”. Se adoptó una solución simple basada en invariantes:

1. un Co-administrador puede ver, pero no crear, editar, desactivar ni restablecer la contraseña de un Administrador;
2. un usuario no puede desactivar su propia cuenta;
3. un usuario no puede cambiar su propio rol o estado desde el formulario administrativo;
4. siempre debe quedar al menos un Administrador activo;
5. no se permite más de un Jefe de Carrera principal activo por carrera.

La regla del Jefe activo se ejecuta dentro de una transacción que bloquea la fila de la carrera antes de comprobar y asignar el rol. Esto serializa creaciones, cambios de rol y activaciones concurrentes para la misma carrera. Pueden conservarse Jefes inactivos como historial y la delegación temporal permanece reservada para la Fase 19.

### Concurrencia del último Administrador

Todas las operaciones que pueden modificar la cantidad de Administradores activos —creación, bootstrap, activación, desactivación y cambio hacia o desde el rol— bloquean mediante `SELECT ... FOR UPDATE` la misma fila estable `roles.name = administrator` dentro de su transacción.

La comprobación de que existe otro Administrador activo ocurre después de obtener ese mutex. Si dos solicitudes intentan desactivar o degradar simultáneamente a los dos últimos Administradores, PostgreSQL permite continuar a una sola. La segunda espera la finalización de la primera y, bajo `READ COMMITTED`, su siguiente consulta observa el estado confirmado, detecta que ya no existe otro activo y rechaza la operación. No se basa en un conteo realizado fuera de la transacción.

Una prueba con dos conexiones PostgreSQL independientes confirmó que la segunda transacción no puede adquirir simultáneamente el mutex; PostgreSQL devuelve `55P03` al agotarse el tiempo de espera configurado para la prueba.

## 5. Autorización por ámbito

`UserPolicy` implementa:

- `viewAny`;
- `view`;
- `create`;
- `update`;
- `activate`;
- `deactivate`;
- `resetPassword`.

Cada decisión combina el permiso `users.*`, el rol del actor, la unidad del actor, el rol objetivo y la unidad objetivo. Las rutas también usan middleware `permission:*`.

Administrador y Co-administrador pueden listar todo. El Jefe recibe en backend una consulta limitada por `organizational_unit_id`; una URL modificada hacia un usuario de otra carrera recibe `403`. El Jefe solo puede modificar usuarios secundarios de su carrera.

## 6. Creación y edición

El backend:

- normaliza nombres y correos;
- guarda el correo en minúsculas;
- rechaza correos duplicados sin distinguir mayúsculas;
- exige una contraseña inicial de al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos;
- usa el cast `hashed` oficial de Laravel;
- exige una carrera activa para roles de unidad;
- fuerza unidad nula para roles globales;
- rechaza roles, unidades y combinaciones no autorizadas;
- asigna exactamente un rol institucional mediante `syncRoles`;
- no modifica la contraseña durante una edición normal.

El restablecimiento de contraseña es una acción separada, autorizada por Policy. Nunca se muestra el hash ni una contraseña existente.

## 7. Bootstrap del primer Administrador

El comando interactivo:

```bash
php artisan app:create-initial-admin
```

crea exclusivamente la primera cuenta Administrador. El modelo actual utiliza un solo campo de nombre, por lo que solicita **nombre completo** en lugar de separar apellidos.

El comando:

- comprueba que el catálogo RBAC exista;
- se niega antes de solicitar datos si ya existe cualquier Administrador, activo o inactivo;
- solicita nombre completo y correo;
- usa entradas ocultas para contraseña y confirmación;
- aplica la misma política de 12 caracteres, mayúsculas, minúsculas, números y símbolos del CRUD;
- normaliza nombre y correo;
- bloquea la fila del rol Administrador y vuelve a comprobar la inexistencia dentro de la transacción;
- crea una cuenta activa, global y sin unidad;
- asigna el rol por slug, sin IDs ni correos hardcodeados;
- utiliza el cast `hashed` oficial;
- nunca imprime ni registra la contraseña.

El comando no acepta nombre, correo ni contraseña como argumentos y no ofrece una opción `--force`. Si se usa la opción global `--no-interaction`, faltarán los datos obligatorios y la validación rechazará la creación. Las siguientes cuentas Administrador se crean únicamente mediante el CRUD y su autorización normal.

## 8. Estado y conservación

Activar y desactivar requieren acciones explícitas y confirmación visible. Desactivar cambia `status` a `inactive`; no elimina la fila, el rol, la unidad ni sus relaciones históricas. El middleware de autenticación existente continúa impidiendo el ingreso de usuarios inactivos.

No existe una ruta `destroy` para usuarios.

## 9. Interfaz

La interfaz Blade/Tailwind incluye:

- listado paginado de 15 usuarios;
- nombre, correo, rol, unidad, estado y acciones;
- búsqueda por nombre o correo;
- filtros por rol, carrera y estado;
- formulario adaptable al rol seleccionado;
- selector limitado a carreras activas;
- detalle del usuario y último ingreso;
- mensajes y errores en español;
- acciones con tamaño legible y confirmaciones claras;
- navegación visible únicamente para quienes poseen `users.view`.

JavaScript mejora la presentación del selector de carrera, pero toda la validación se repite en backend.

## 10. Rutas

| Método | Ruta | Nombre | Permiso |
|---|---|---|---|
| `GET` | `/administracion/usuarios` | `users.index` | `users.view` |
| `GET` | `/administracion/usuarios/crear` | `users.create` | `users.create` |
| `POST` | `/administracion/usuarios` | `users.store` | `users.create` |
| `GET` | `/administracion/usuarios/{user}` | `users.show` | `users.view` |
| `GET` | `/administracion/usuarios/{user}/editar` | `users.edit` | `users.update` |
| `PUT` | `/administracion/usuarios/{user}` | `users.update` | `users.update` |
| `PATCH` | `/administracion/usuarios/{user}/activar` | `users.activate` | `users.activate` |
| `PATCH` | `/administracion/usuarios/{user}/desactivar` | `users.deactivate` | `users.deactivate` |
| `GET` | `/administracion/usuarios/{user}/restablecer-contrasena` | `users.password.edit` | `users.reset_password` |
| `PUT` | `/administracion/usuarios/{user}/restablecer-contrasena` | `users.password.update` | `users.reset_password` |

Las rutas usan `public_id`; no exponen el ID interno.

## 11. Preparación para auditoría

Todas las escrituras del módulo pasan por `UserManagementService` y transacciones. Este punto único permitirá incorporar posteriormente eventos de auditoría para creación, edición, cambio de rol, cambio de unidad, activación, desactivación y restablecimiento de contraseña sin dispersar lógica en controladores.

La auditoría funcional no se adelantó.

## 12. Pruebas y verificaciones

### Gestión de usuarios

Resultado de gestión web: **17 pruebas y 116 aserciones aprobadas**.

### Bootstrap inicial

Resultado: **3 pruebas y 23 aserciones aprobadas**.

### Suite completa

Resultado: **62 pruebas y 316 aserciones aprobadas**.

### Integración PostgreSQL

Resultado: **25 pruebas y 69 aserciones aprobadas**.

PostgreSQL verificó el índice único de correo sin distinguir mayúsculas, estado permitido, FK de unidad, asignación transaccional, hashing, ámbito global, la regla de un Jefe activo por carrera y la exclusión mutua del bloqueo del Administrador con dos conexiones independientes.

También se verificaron las 10 rutas, Pint, la compilación Vite y el estado de migraciones.

## 13. Archivos creados

- `app/Enums/UserStatus.php`
- `app/Console/Commands/CreateInitialAdministrator.php`
- `app/Policies/UserPolicy.php`
- `app/Services/AdministratorAccountGuard.php`
- `app/Services/InitialAdministratorService.php`
- `app/Services/UserManagementService.php`
- `app/Support/InstitutionalPassword.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Requests/StoreUserRequest.php`
- `app/Http/Requests/UpdateUserRequest.php`
- `app/Http/Requests/ResetUserPasswordRequest.php`
- `resources/views/users/index.blade.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`
- `resources/views/users/show.blade.php`
- `resources/views/users/password.blade.php`
- `resources/views/users/_form.blade.php`
- `tests/Feature/UserManagementTest.php`
- `tests/Feature/InitialAdministratorCommandTest.php`
- `tests/PostgreSQL/PostgreSqlUserManagementTest.php`
- `docs/FASE_6_GESTION_USUARIOS.md`

## 14. Archivos modificados

- `app/Enums/RoleName.php`
- `app/Models/User.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `resources/js/app.js`
- `routes/web.php`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/seguridad/MATRIZ_PERMISOS.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 15. Límites de la fase

- No se crearon usuarios institucionales mediante seeders.
- No se implementó eliminación física.
- No se implementaron documentos, tipos documentales, reportes ni auditoría funcional.
- No se implementaron delegaciones temporales ni personal específico de Kardex.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 7 no fue iniciada.
