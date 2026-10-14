# Checklist de entrega

Este checklist separa lo comprobado en el repositorio de aquello que debe completar el operador en la infraestructura definitiva. Ninguna marca de preparación sustituye la aprobación institucional ni una restauración real de backup.

| Área | Comprobación | Estado del proyecto | Acción antes de abrir producción |
|---|---|---|---|
| Configuración | `.env.example` productivo, sin secretos, `APP_DEBUG=false` y PostgreSQL | Preparado y probado | Crear `.env` real fuera del repositorio y revisar cada valor |
| APP_KEY | Procedimiento de generación y rotación documentado | Preparado | Generar una clave única solo en instalación nueva; custodiarla y respaldarla |
| PostgreSQL | 18.3 accesible, esquema e integración aprobados | Validado localmente | Crear rol no superusuario `sistema_teletrabajo_app`, TLS y credenciales exclusivas |
| Migraciones | 19 migraciones incrementales en estado `Ran` | Aprobado | Backup DB + storage, mantenimiento y `migrate --force`; nunca `fresh`/`wipe` |
| Seeders | Autorización, estructura y tipos idempotentes; sin usuarios | Aprobado | Ejecutar `db:seed --force` una vez y revisar salida |
| Administrador inicial | Comando interactivo seguro y de un solo uso | Aprobado | Ejecutar `php artisan app:create-initial-admin` por canal controlado |
| Storage | Documentos privados fuera de `/public`, sin symlink | Aprobado | Montar volumen persistente, permisos del usuario PHP-FPM y espacio monitorizado |
| Permisos | Código solo lectura; escritura en `storage` y `bootstrap/cache`; sin `777` | Documentado | Aplicar propietario/grupo y verificar con el usuario de servicio |
| Backups | Procedimiento DB + archivos, cifrado, checksum, retención y copia externa | Documentado | Programar backup y completar restauración aislada antes de apertura |
| Scheduler | `documents:process-deadlines` diario 08:00 `America/La_Paz`, sin solapamiento | Aprobado | Instalar cron cada minuto y alertar si deja de ejecutarse |
| Colas | No existen trabajos `ShouldQueue`; driver `sync` | Revisado | No instalar worker; reevaluar si se introduce correo/colas |
| HTTPS | Redirección, cookies seguras y HSTS condicionado documentados | Preparado | Instalar certificado, probar renovación y luego decidir HSTS |
| Nginx | VHost de ejemplo publica exclusivamente `/public` | Preparado | Sustituir dominio/rutas/socket, validar `nginx -t` y bloquear artefactos sensibles |
| PHP-FPM | Pool no root y socket restringido de ejemplo | Preparado | Crear usuario/pool, aplicar límites y reiniciar servicio |
| PHP | 8.3.28 y extensiones requeridas verificadas | Aprobado | PHP 8.5 solo tras repetir suites con runtime real |
| Sesiones/cookies | DB, cifrado, `Secure`, `HttpOnly`, `SameSite=Lax` en plantilla | Aprobado | Confirmar cookies en navegador bajo HTTPS real |
| Headers/errores | Headers, CSP inicial, health check y páginas 403/404/419/429/500/503 | Aprobado | Verificar desde Internet/proxy y revisar que no duplique HSTS |
| Auditoría | Eventos transversales, sanitización e inmutabilidad | Aprobado | Definir retención, monitoreo y acceso administrativo |
| Dependencias | Lockfiles, plataforma, Composer/NPM auditados | Aprobado | Usar `composer install --no-dev` y `npm ci`; no actualizar durante despliegue |
| Build | Vite productivo genera manifiesto y assets | Aprobado | Compilar en CI/servidor y desplegar assets del mismo commit |
| Cachés | `config:cache`, `route:cache` y `view:cache` compatibles | Aprobado | Ejecutar después de completar `.env`; limpiar antes de cambiar variables |
| Pruebas | Regresión rápida, aceptación y PostgreSQL aprobadas | Aprobado | No ejecutar pruebas contra datos productivos; usar preproducción aislada |
| UAT | Matriz preparada para los seis roles | Pendiente institucional | Ejecutar `PRUEBAS_ACEPTACION.md` con personal y cuentas temporales |
| Correo | Desarrollo usa `MAIL_MAILER=log` | Diferido | Configurar proveedor institucional cuando se apruebe la mejora futura |
| Monitoreo | `/up` genérico sin datos sensibles | Preparado | Vigilar HTTPS, DB, disco, backups, Scheduler, logs y expiración TLS |
| Documentación | README, arquitectura, flujo, seguridad, despliegue, backup y cierre | Actualizada | Entregar copia del commit aprobado a operación y responsables |

## Orden recomendado de liberación

1. Aprobar commit/artefacto y ventana de mantenimiento.
2. Confirmar backup conjunto y restauración aislada reciente.
3. Preparar PostgreSQL dedicado, filesystem, `.env`, PHP-FPM y Nginx.
4. Instalar dependencias bloqueadas y compilar assets.
5. Activar mantenimiento, ejecutar migraciones/seeders y generar cachés.
6. Crear el Administrador inicial si es una instalación nueva.
7. Abrir HTTPS, ejecutar comprobaciones controladas y revisar logs.
8. Completar UAT institucional y registrar conformidad.
9. Activar monitoreo y cerrar formalmente la ventana.

## Criterios de no liberación

No abrir producción si falta cualquiera de los siguientes: backup restaurable, HTTPS válido, rol PostgreSQL no superusuario, almacenamiento persistente privado, Scheduler, `APP_DEBUG=false`, migraciones completas, Administrador inicial controlado o UAT institucional.
