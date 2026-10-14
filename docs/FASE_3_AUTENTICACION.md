# Fase 3 — Autenticación

> **Estado:** implementada y validada el 8 de octubre de 2026.

## 1. Alcance completado

La fase implementa exclusivamente autenticación y seguridad básica de acceso. Utiliza el modelo `User` y la tabla `users` existentes, sin introducir roles, permisos, policies documentales ni dashboards por perfil.

No fue necesaria ninguna migración: `users` ya disponía de `email`, `password`, `status`, `last_login_at` y `remember_token`; la tabla `sessions` y `password_reset_tokens` también existían desde la Fase 2.

## 2. Flujo implementado

| Método | Ruta | Nombre | Acceso | Función |
|---|---|---|---|---|
| `GET` | `/` | `login` | Visitante | Formulario institucional de inicio de sesión. |
| `POST` | `/iniciar-sesion` | `login.store` | Visitante | Valida y procesa las credenciales. |
| `GET` | `/panel` | `dashboard` | Usuario autenticado y activo | Panel provisional. |
| `POST` | `/cerrar-sesion` | `logout` | Usuario autenticado y activo | Cierre seguro de sesión. |

La raíz pública es el formulario de acceso. Un visitante que intenta abrir `/panel` es redirigido a `/`. Un usuario ya autenticado que intenta volver al formulario es redirigido al panel provisional.

## 3. Controles de seguridad

### Estado del usuario

La autenticación exige `status = active` como parte de las credenciales. Una cuenta `inactive` recibe el mismo mensaje genérico que unas credenciales incorrectas, evitando revelar el estado o la existencia de la cuenta.

El middleware `active.user` vuelve a comprobar el estado en cada acceso privado. Si una cuenta es desactivada durante una sesión existente, se cierra la sesión, se invalida su contenido y se regenera el token CSRF.

### Límite de intentos

El inicio de sesión admite hasta cinco fallos por combinación normalizada de correo e IP. Después se bloquean nuevos intentos durante 60 segundos y se informa el tiempo restante en español. Un inicio exitoso limpia el contador.

### Sesiones y CSRF

- Se conserva `SESSION_DRIVER=database`.
- Después de autenticar se regenera el identificador de sesión para impedir session fixation.
- El cierre de sesión ejecuta `logout`, invalida la sesión completa y regenera el token CSRF.
- Las rutas usan el grupo web oficial de Laravel y ambos formularios `POST` incluyen `@csrf`.
- No se incorporaron mecanismos propios de cookies, sesiones o cifrado.

### Credenciales

Laravel verifica la contraseña mediante el proveedor Eloquent y el hash almacenado. La contraseña nunca se registra ni se devuelve a las vistas. Todos los mensajes visibles de validación están en español.

## 4. Interfaz

La pantalla de acceso es responsive, operable por teclado y utiliza:

- etiquetas explícitas para correo y contraseña;
- autocompletado estándar `username` y `current-password`;
- foco visible y controles con tamaño táctil adecuado;
- mensajes con roles accesibles `status` y `alert`;
- enlace para saltar al contenido principal;
- lenguaje institucional sencillo y sin opciones ajenas a esta fase.

El panel autenticado es deliberadamente provisional. Solo confirma la sesión, muestra el nombre, correo y último ingreso, y ofrece el cierre de sesión. No contiene lógica de roles.

## 5. Recuperación de contraseña

La estructura queda preparada mediante `password_reset_tokens`, el broker configurado en `config/auth.php` y `MAIL_MAILER=log` durante desarrollo. No se implementó todavía el envío de correo ni una interfaz de recuperación, porque el correo institucional real y su operación pertenecen a fases posteriores.

## 6. Archivos creados

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- `resources/views/auth/login.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/components/layouts/app.blade.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/PostgreSQL/PostgreSqlAuthenticationTest.php`
- `docs/FASE_3_AUTENTICACION.md`

## 7. Archivos modificados

- `bootstrap/app.php`: redirecciones de autenticación y alias `active.user`.
- `routes/web.php`: rutas públicas, privadas y de sesión.
- `database/factories/UserFactory.php`: estado reutilizable `inactive` para pruebas.
- `tests/Feature/ApplicationFoundationTest.php`: expectativa de la nueva página principal.
- `docs/PLAN_IMPLEMENTACION.md` y `README.md`: estado del proyecto.

La antigua vista pública `resources/views/welcome.blade.php` fue reemplazada por el login institucional. No se modificaron migraciones de la Fase 2.

## 8. Pruebas

### Suite general

```bash
php artisan test
```

Resultado: 19 pruebas y 84 aserciones aprobadas.

La suite verifica, entre otros puntos:

- formulario principal y token CSRF;
- acceso de usuario activo y actualización de `last_login_at`;
- rechazo de contraseña incorrecta;
- rechazo de usuario inactivo;
- redirección de visitantes desde rutas privadas;
- invalidación de sesión y regeneración de CSRF al salir;
- límite tras cinco intentos fallidos;
- regeneración del identificador de sesión al ingresar;
- cierre forzado si una cuenta autenticada pasa a inactiva.

### Integración PostgreSQL

```bash
php vendor/bin/phpunit --configuration phpunit.postgresql.xml
```

Resultado: 9 pruebas y 31 aserciones aprobadas. Las dos pruebas de autenticación PostgreSQL crean usuarios dentro de transacciones que se revierten, por lo que no dejan datos de prueba.

### Comprobación HTTP local

Con el servidor local temporal se comprobó que:

- `GET /` responde `200`, contiene el formulario en español y el campo CSRF;
- `GET /panel` sin autenticación responde `302` y redirige a `/`.

La base oficial no contiene usuarios permanentes, por lo que no se creó una cuenta institucional artificial para una prueba manual. El envío real de credenciales se validó tanto en la suite funcional como directamente contra PostgreSQL mediante transacciones revertidas.

## 9. Decisiones de dependencia

No se instaló Laravel Breeze ni otra dependencia. Para este alcance, el guard web, el proveedor Eloquent, el middleware, el limitador y las sesiones oficiales de Laravel cubren todo lo necesario con menos superficie de mantenimiento.

## 10. Límites de la fase

- No se crearon roles, permisos, Gates o policies; corresponden a la Fase 4.
- No se implementaron dashboards definitivos por rol.
- No se implementó administración de usuarios, carreras o documentos.
- No se configuraron correo institucional real, firma digital ni 2FA.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 4 no ha comenzado.
