# Fase 23: Endurecimiento de seguridad y producción

## Resultado

La Fase 23 prepara una primera puesta en producción reproducible sobre Linux, Nginx, PHP-FPM y PostgreSQL 18, sin agregar módulos funcionales ni modificar RBAC, Policies, estados, destinatarios, versiones, auditoría, delegaciones o reportes.

No se inició la Fase 24. No se creó ninguna migración ni se agregó una dependencia.

## Configuración segura

`.env.example` quedó como plantilla de producción sin secretos:

- `APP_ENV=production`, `APP_DEBUG=false` y URL HTTPS de ejemplo inválida;
- `APP_KEY` y contraseñas vacías;
- usuario PostgreSQL dedicado, SSL y timezone explícitos;
- sesiones en base de datos, cifradas, cookie `Secure`, `HttpOnly` y `SameSite=Lax`;
- logs diarios con nivel `warning` y retención inicial de 14 días;
- cola `sync`, porque no existen jobs o notifications con `ShouldQueue`;
- cache de aplicación en base de datos, sin requerir Redis;
- proxies confiables y HSTS desactivados hasta configurarlos expresamente.

El conector PostgreSQL acepta `DB_SSLROOTCERT`, `DB_SSLCERT`, `DB_SSLKEY`, `DB_TIMEZONE` y un `application_name`. El procedimiento crea un rol de aplicación no superusuario y prohíbe usar `postgres` como cuenta habitual.

El script `post-create-project-cmd` de Composer ya no crea SQLite ni ejecuta migraciones automáticas. PostgreSQL continúa siendo la base oficial.

## Sesiones, proxies y HTTPS

`SESSION_DRIVER=database` se conserva. La plantilla productiva usa:

```dotenv
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
```

No se usa `SameSite=None`. `TRUSTED_PROXIES` acepta únicamente IP o CIDR explícitos separados por coma; una lista vacía representa Nginx directo y nunca se recomienda `*`. Esto protege la IP de auditoría y la detección del esquema HTTPS.

HSTS solo se emite cuando la petición es HTTPS y `SECURITY_HSTS_ENABLED=true`. Debe activarse después de comprobar certificado, renovación y subdominios. La redirección HTTP a HTTPS pertenece a Nginx.

## Headers y CSP

El middleware global agrega:

- `X-Content-Type-Options: nosniff`;
- `X-Frame-Options: DENY`;
- `Referrer-Policy: strict-origin-when-cross-origin`;
- `Permissions-Policy: camera=(), geolocation=(), microphone=()`;
- CSP inicial: `frame-ancestors 'none'; base-uri 'self'; object-src 'none'`.

La CSP protege framing, bases y objetos sin imponer todavía `default-src` o `script-src`, por lo que no rompe Vite, Livewire ni los handlers actuales. No usa `unsafe-eval`. Una CSP más amplia deberá introducir nonces/hashes, retirar scripts inline y ejecutarse primero en `Content-Security-Policy-Report-Only`.

La aplicación Blade + Livewire opera bajo el mismo origen. No se abrió CORS ni se agregó ningún origen comodín; las rutas web conservan el middleware CSRF oficial y no se incorporaron exclusiones.

## Errores y health check

Se añadieron páginas institucionales, accesibles y sin detalles técnicos para 403, 404, 419, 429, 500 y 503. Con `APP_DEBUG=false`, la prueba de 500 confirma que no aparecen clase de excepción, SQL, ruta interna ni valor sensible.

`/up` conserva el health check mínimo de Laravel. Solo confirma que la aplicación responde y recibe los mismos headers; no publica versión, configuración ni estado detallado de PostgreSQL. El monitoreo debe complementar este endpoint con verificaciones privadas de DB, storage, espacio, backups y Scheduler.

## Storage, temporales y descargas

El disco `documents` permanece en `storage/app/documents`, con `visibility=private`, `serve=false` y errores explícitos. No existe un symlink público hacia esa carpeta; la configuración de Nginx niega `/storage` por completo.

Las cargas usan nombres internos y compensan filesystem si la transacción falla. Los retiros de borrador mueven temporalmente el archivo y eliminan ese temporal después del commit; ante error lo restauran. Los CSV usan `php://temp` y no dejan exportaciones permanentes.

Descargas privadas conservan Policy y auditoría y reciben un límite de 120 solicitudes por minuto por actor/IP. Exportaciones CSV reciben 10 por minuto. El login conserva su límite existente de cinco intentos fallidos por clave normalizada.

## PHP, Nginx y archivos grandes

Se incluyeron ejemplos revisables para:

- Nginx con document root exclusivo `/public`, TLS, sin autoindex, bloqueo de dotfiles/extensiones sensibles y sin ruta a storage;
- PHP de producción sin `display_errors`, con OPcache y límites coherentes;
- pool PHP-FPM no root con socket restringido y límites razonables.

Laravel admite 20 MB por archivo y 10 archivos por solicitud. El ejemplo configura `upload_max_filesize=20M`, `post_max_size=220M`, `max_file_uploads=20` y `client_max_body_size=220m`.

`composer.json` declara PHP `^8.3`. `composer prohibits php 8.5 --locked` no encontró paquetes incompatibles con PHP 8.5, pero el runtime 8.5 debe instalarse y ejecutar la suite completa antes de declararlo validado. La ejecución actual se verificó con PHP 8.3.

## Scheduler, colas y caché

El Scheduler mantiene `documents:process-deadlines` todos los días a las 08:00 `America/La_Paz` y `withoutOverlapping()`. La guía recomienda `schedule:run` cada minuto mediante cron bajo el usuario de aplicación.

No se introdujo Redis. Tampoco se requiere worker mientras no existan trabajos `ShouldQueue`. `config:cache`, `route:cache` y `view:cache` se ejecutaron correctamente; las cachés se limpiaron después de la comprobación para no fijar el `.env` local.

Los logs técnicos rotan diariamente y son distintos de `audit_logs`, que permanece en PostgreSQL y forma parte del backup. Contraseñas, tokens, cookies, claves, binarios y contenido documental no deben escribirse en logs. No existe Debugbar ni Telescope en dependencias productivas.

## Backups, restauración y despliegue

[DESPLIEGUE_PRODUCCION.md](DESPLIEGUE_PRODUCCION.md) contiene el procedimiento desde un servidor limpio, incluida base dedicada, permisos, APP_KEY, Composer/NPM, migraciones, seeders, Administrador inicial, Scheduler, TLS y checklist posterior.

[BACKUP_RESTORE.md](BACKUP_RESTORE.md) define respaldo conjunto de PostgreSQL y `storage/app/documents`, cifrado, copia externa, retención, checksums y restauración trimestral en un entorno aislado. Antes de toda migración se exige backup DB + storage. Nunca se restaura sobre desarrollo o producción sin autorización.

## Dependencias y plataforma

Resultados del 12 de octubre de 2026:

- `composer validate --strict`: aprobado;
- `composer check-platform-reqs --no-dev`: aprobado;
- `composer prohibits php 8.5 --locked`: sin paquetes incompatibles instalados;
- `composer audit --locked --no-dev`: **0 avisos**;
- `npm audit`: **0 vulnerabilidades**;
- extensiones locales verificadas: PDO, `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl`, `curl`, DOM/XML y Sodium.

No se ejecutó `composer update`, `npm audit fix`, `npm audit fix --force` ni una actualización mayor.

## Archivos creados

- `app/Http/Middleware/AddSecurityHeaders.php`
- `config/security.php`
- `config/trustedproxy.php`
- `resources/views/errors/layout.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/errors/429.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`
- `tests/Feature/ProductionSecurityTest.php`
- `deploy/nginx/sistema-teletrabajo.conf.example`
- `deploy/php/production.ini.example`
- `deploy/php-fpm/sistema-teletrabajo.conf.example`
- `docs/FASE_23_PRODUCCION_SEGURIDAD.md`
- `docs/DESPLIEGUE_PRODUCCION.md`
- `docs/BACKUP_RESTORE.md`

## Archivos modificados

- `.env.example`
- `composer.json`
- `bootstrap/app.php`
- `config/database.php`
- `routes/web.php`
- `README.md`
- `docs/PLAN_IMPLEMENTACION.md`

## Verificación final

Resultados finales:

- pruebas específicas de producción: **11 pruebas, 73 aserciones**;
- suite general: **282 pruebas, 1730 aserciones**;
- suite PostgreSQL completa: **69 pruebas, 290 aserciones**.

También quedaron aprobados Pint, Blade, Vite, `config:cache`, `route:cache`, `view:cache`, Scheduler, health check, headers, almacenamiento privado, auditorías de dependencias y `migrate:status`. Las 19 migraciones permanecen en estado `Ran`; no se ejecutó ninguna migración, `migrate:fresh` ni `db:wipe`.
