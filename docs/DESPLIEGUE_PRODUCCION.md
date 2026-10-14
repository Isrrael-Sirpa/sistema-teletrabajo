# Despliegue de producción

## Arquitectura inicial

La primera puesta en producción utiliza una arquitectura simple: Linux, Nginx, PHP-FPM, PostgreSQL 18, almacenamiento privado local respaldado y Laravel Scheduler. Redis, S3, Docker, Kubernetes y workers de cola no son requisitos actuales.

El servidor debe publicar únicamente `/var/www/sistema-teletrabajo/public`. `storage/app/documents`, `.env`, `.git`, pruebas, documentación, dumps y logs nunca forman parte del document root.

## 1. Paquetes del sistema y PHP

Instale Nginx, PostgreSQL client, Node.js compatible con `package-lock.json`, Composer 2 y PHP-FPM. El proyecto declara PHP `^8.3`. La revisión del lock no muestra una prohibición conocida para PHP 8.5; antes de cambiar el servidor definitivo debe repetirse `composer prohibits php 8.5 --locked` y ejecutar toda la suite con el binario 8.5 real. Hasta completar esa prueba, PHP 8.3 es la versión verificada localmente.

Extensiones requeridas o utilizadas:

- `pdo_pgsql` y `pgsql` para PostgreSQL;
- `mbstring`, `openssl`, `fileinfo`, `zip` y `gd` para validación y manejo de archivos;
- `ctype`, `curl`, `dom`, `filter`, `hash`, `iconv`, `json`, `libxml`, `pcre`, `session`, `tokenizer`, `xml`, `xmlreader` y `xmlwriter` para Laravel, Composer o sus dependencias;
- `intl` está disponible y es recomendable para funciones de idioma, aunque el código actual no depende exclusivamente de ella;
- `opcache` para rendimiento de producción.

Verifique con:

```bash
php -v
php -m
composer check-platform-reqs --no-dev
```

Use como base [production.ini.example](../deploy/php/production.ini.example) y reinicie PHP-FPM después de modificar `php.ini`.

## 2. PostgreSQL dedicado

No use `postgres` ni otro superusuario. Ejemplo ejecutado una sola vez por un administrador de PostgreSQL, sustituyendo la contraseña fuera del historial:

```sql
CREATE ROLE sistema_teletrabajo_app
    LOGIN
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION
    PASSWORD 'GENERAR_Y_ENTREGAR_POR_CANAL_SEGURO';

CREATE DATABASE sistema_teletrabajo
    OWNER sistema_teletrabajo_app
    ENCODING 'UTF8';

REVOKE ALL ON DATABASE sistema_teletrabajo FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE sistema_teletrabajo TO sistema_teletrabajo_app;
ALTER ROLE sistema_teletrabajo_app IN DATABASE sistema_teletrabajo
    SET timezone TO 'America/La_Paz';
```

Conectado a la base, revoque `CREATE` público sobre el esquema y concédalo al dueño de la aplicación para migraciones:

```sql
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO sistema_teletrabajo_app;
```

Use `DB_SSLMODE=require` en una red confiable con TLS. Cuando exista una CA verificable, prefiera `verify-full` y configure `DB_SSLROOTCERT`. `DB_SSLCERT` y `DB_SSLKEY` solo son necesarios si PostgreSQL exige certificado de cliente.

## 3. Código y dependencias

Despliegue un commit revisado junto con `composer.lock` y `package-lock.json`:

```bash
cd /var/www/sistema-teletrabajo
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

No ejecute `composer update` ni `npm audit fix --force` como procedimiento de producción. Alternativamente, compile Vite en CI y despliegue `public/build` como artefacto del mismo commit. Nunca ejecute el servidor de desarrollo de Vite en producción.

## 4. Configuración y APP_KEY

Copie `.env.example` a `.env` fuera de cualquier commit, restrinja su modo y complete al menos:

- `APP_URL` HTTPS real;
- `APP_KEY` propia;
- host, base, usuario y contraseña PostgreSQL;
- modo SSL y CA cuando corresponda;
- IP o CIDR explícito de cada reverse proxy controlado;
- remitente institucional cuando se habilite correo.

Genere la clave solo en una instalación nueva:

```bash
php artisan key:generate
chmod 0640 .env
```

No regenere `APP_KEY` en una instalación con datos, cookies o valores cifrados existentes. Para rotación planificada, conserve temporalmente la clave anterior mediante `APP_PREVIOUS_KEYS` y realice una migración controlada; nunca publique ninguna clave.

Valores obligatorios de producción:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://documentos.example.edu.bo
APP_TIMEZONE=America/La_Paz
DB_CONNECTION=pgsql
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
LOG_STACK=daily
LOG_LEVEL=warning
QUEUE_CONNECTION=sync
```

`TRUSTED_PROXIES` queda vacío cuando Nginx conecta directamente a PHP-FPM. Si hay balanceador o proxy adicional, indique únicamente sus IP/CIDR separados por coma. Nunca use `*`, porque la IP resuelta alimenta la auditoría y la detección HTTPS.

La interfaz Blade + Livewire es de mismo origen. No habilite CORS global ni use `*` salvo que un requisito futuro defina orígenes concretos y una revisión de seguridad específica. Mantenga activo el middleware CSRF de Laravel.

## 5. Límites de carga

Laravel permite 20 MB por archivo y 10 archivos por solicitud. Los niveles superiores deben admitir el lote y su overhead:

| Nivel | Valor inicial |
|---|---:|
| Laravel `DOCUMENT_MAX_FILE_SIZE_KB` | 20480 |
| Laravel `DOCUMENT_MAX_FILES_PER_REQUEST` | 10 |
| PHP `upload_max_filesize` | 20M |
| PHP `post_max_size` | 220M |
| PHP `max_file_uploads` | 20 |
| Nginx `client_max_body_size` | 220m |

Un límite menor en Nginx o PHP rechaza la solicitud antes de la validación en español de Laravel.

## 6. Permisos de filesystem

No ejecute la aplicación como root ni use `chmod 777`. Un esquema inicial con usuario de despliegue `deploy` y grupo web `www-data` es:

```bash
chown -R deploy:www-data /var/www/sistema-teletrabajo
find /var/www/sistema-teletrabajo -type d -exec chmod 0750 {} \;
find /var/www/sistema-teletrabajo -type f -exec chmod 0640 {} \;
chmod -R 2770 storage bootstrap/cache
```

El usuario PHP-FPM necesita lectura del código y lectura/escritura únicamente en `storage` y `bootstrap/cache`. La carpeta `storage/app/documents` no debe poseer un symlink bajo `public`; no ejecute `storage:link` para documentos.

## 7. Migraciones, seeders y primer administrador

Después del backup y la revisión de migraciones:

```bash
php artisan down --retry=60
php artisan migrate --force
php artisan db:seed --force
php artisan app:create-initial-admin
```

`DatabaseSeeder` solo ejecuta seeders idempotentes de roles, permisos, estructura institucional y tipos documentales. No crea usuarios de prueba. El comando del primer Administrador se usa una sola vez y se niega a continuar cuando ya existe uno.

Nunca use `migrate:fresh` ni `db:wipe` en producción.

No ejecute PHPUnit, Pest ni pruebas automatizadas contra la base productiva. CI y preproducción deben usar una base PostgreSQL exclusiva, credenciales propias y almacenamiento aislado. Las comprobaciones posteriores al despliegue deben limitarse al checklist controlado de este documento.

## 8. Cachés y servicios

Las rutas son compatibles con caché. Después de establecer `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Configure Nginx a partir de [sistema-teletrabajo.conf.example](../deploy/nginx/sistema-teletrabajo.conf.example) y PHP-FPM a partir de [sistema-teletrabajo.conf.example](../deploy/php-fpm/sistema-teletrabajo.conf.example). Reemplace dominio, rutas y socket, valide `nginx -t` y reinicie ambos servicios.

Obtenga un certificado TLS real y automatice su renovación. La redirección HTTP a HTTPS debe estar activa. Habilite HSTS (`SECURITY_HSTS_ENABLED=true` o la directiva Nginx, no ambas) solo después de comprobar certificado, renovación y cobertura de subdominios.

## 9. Scheduler y colas

Opción recomendada en un solo servidor:

```cron
* * * * * cd /var/www/sistema-teletrabajo && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use el crontab del usuario de la aplicación, no root. El Scheduler ejecuta `documents:process-deadlines` una vez al día a las 08:00 en `America/La_Paz` y evita solapamiento.

Actualmente no hay jobs ni notifications que implementen `ShouldQueue`; `QUEUE_CONNECTION=sync` es suficiente y no se requiere worker ni Redis. Si una fase futura agrega tareas en cola, documente y supervise un worker antes de cambiar el driver.

## 10. Verificación y apertura

```bash
php artisan migrate:status
php artisan schedule:list
php artisan route:list
curl --fail --silent https://documentos.example.edu.bo/up
php artisan up
```

El endpoint `/up` confirma únicamente que Laravel responde; no publica versiones ni credenciales. Monitorear disponibilidad no sustituye una comprobación interna de PostgreSQL, almacenamiento, espacio, backups y Scheduler.

## Checklist posterior

Use datos ficticios no sensibles y confirme:

- HTTPS y redirección HTTP, certificado y cookies `Secure`, `HttpOnly`, `SameSite=Lax`;
- login correcto, rate limiting, logout e invalidación de sesión;
- separación de los seis roles y bloqueo de cuentas inactivas;
- carga privada y descarga autorizada sin URL pública ni ruta física;
- envío, recepción, revisión y decisión documental;
- documento `restricted` sin filtración en notificaciones, dashboard, auditoría o errores;
- notificaciones internas y lectura;
- ejecución manual controlada del Scheduler y registro diario de las 08:00;
- auditoría de login, administración, descarga, exportación y anulación;
- búsqueda, reportes y CSV sin fórmulas activas;
- delegación temporal, vencimiento y revocación;
- backup DB + storage generado, cifrado, copiado fuera del servidor y restaurado en aislamiento;
- páginas 403, 404, 419, 429, 500 y 503 sin trazas;
- logs rotados sin contraseñas, tokens, binarios o contenido documental.

Consulte [BACKUP_RESTORE.md](BACKUP_RESTORE.md) antes de cada despliegue con migraciones.

Antes de abrir el servicio complete también [CHECKLIST_ENTREGA.md](CHECKLIST_ENTREGA.md) y ejecute la validación humana definida en [PRUEBAS_ACEPTACION.md](PRUEBAS_ACEPTACION.md) sobre una preproducción aislada.
