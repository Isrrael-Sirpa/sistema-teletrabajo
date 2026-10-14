# Respaldo y restauración

## Alcance obligatorio

Un respaldo válido del sistema es un conjunto consistente compuesto por:

1. PostgreSQL, incluida la tabla `audit_logs`;
2. `storage/app/documents`, con los archivos privados cuyos hashes y metadatos están en la base;
3. una copia cifrada y de acceso restringido de la configuración operativa, certificados y procedimientos, nunca dentro del repositorio.

Respaldar únicamente PostgreSQL no permite reconstruir el expediente documental. Respaldar únicamente archivos tampoco permite relacionarlos con versiones, permisos e historial.

## Política inicial

- PostgreSQL: respaldo diario en formato custom de `pg_dump`.
- Archivos privados: copia incremental diaria de `storage/app/documents`.
- Retención orientativa: 14 copias diarias, 8 semanales y 12 mensuales.
- Al menos una copia cifrada debe permanecer fuera del servidor principal y fuera de la misma cuenta o volumen.
- Calcular y conservar checksums del dump y del manifiesto de archivos.
- Proteger claves de cifrado en un gestor de secretos o custodia institucional separada.
- Supervisar tamaño, duración y resultado; un comando ejecutado sin validar su salida no equivale a un respaldo correcto.

La ventana debe coordinar el dump y la instantánea de archivos. Para máxima consistencia, coloque brevemente la aplicación en mantenimiento o use snapshots coordinados del volumen y la base. No borre copias antiguas hasta confirmar que la nueva es legible.

## Ejemplo de respaldo

Ejecutar con una cuenta de backup con permisos de lectura suficientes, no con el superusuario si no es imprescindible:

```bash
install -d -m 0700 /srv/backups/sistema-teletrabajo/2026-10-12
pg_dump --format=custom --no-owner --no-acl \
  --host=postgresql.internal --port=5432 \
  --username=sistema_teletrabajo_backup \
  --file=/srv/backups/sistema-teletrabajo/2026-10-12/database.dump \
  sistema_teletrabajo

tar --create --gzip \
  --file=/srv/backups/sistema-teletrabajo/2026-10-12/documents.tar.gz \
  --directory=/var/www/sistema-teletrabajo/storage/app documents

sha256sum /srv/backups/sistema-teletrabajo/2026-10-12/database.dump \
  /srv/backups/sistema-teletrabajo/2026-10-12/documents.tar.gz \
  > /srv/backups/sistema-teletrabajo/2026-10-12/SHA256SUMS
```

No coloque la contraseña en el comando, el historial del shell o scripts versionados. Use `.pgpass` con modo `0600`, un secreto inyectado por el sistema de backup o autenticación administrada. Cifre el conjunto antes de copiarlo fuera del servidor.

## Prueba de restauración

Debe realizarse inicialmente y luego al menos trimestralmente, además de antes de cambios importantes. Siempre se restaura primero en un entorno aislado y en una base vacía; nunca sobre producción ni sobre la base de desarrollo sin autorización.

1. Seleccionar un conjunto completo DB + archivos de la misma ventana.
2. Descargarlo a un host aislado y verificar `sha256sum -c SHA256SUMS`.
3. Crear una base vacía con un usuario no superusuario.
4. Restaurar con `pg_restore --clean` únicamente sobre esa base desechable, o sin `--clean` si se creó vacía.
5. Extraer `documents.tar.gz` en el `storage/app` aislado.
6. Instalar el mismo commit y `composer.lock` usados por el respaldo.
7. Crear un `.env` de restauración con una `APP_KEY` compatible con los datos cifrados respaldados. No publicar esa clave.
8. Ejecutar `php artisan migrate:status`, nunca `migrate:fresh` ni `db:wipe`.
9. Verificar login con una cuenta de prueba controlada, conteos principales, historial y descarga de una muestra no sensible.
10. Comparar archivos con los hashes SHA-256 registrados en `document_files` mediante una herramienta interna controlada o una consulta/muestra documentada.
11. Confirmar Scheduler, auditoría y reportes sin enviar notificaciones externas.
12. Registrar fecha, responsable, conjunto probado, duración, incidencias y resultado.
13. Eliminar de forma segura el entorno temporal cuando la evidencia haya sido conservada.

Ejemplo sobre una base aislada recién creada:

```bash
pg_restore --no-owner --no-acl \
  --host=127.0.0.1 --port=5432 \
  --username=sistema_teletrabajo_restore \
  --dbname=sistema_teletrabajo_restore \
  /srv/restore/database.dump

tar --extract --gzip --file=/srv/restore/documents.tar.gz \
  --directory=/var/www/sistema-teletrabajo-restore/storage/app
```

## Restauración de producción

Una restauración real exige autorización y una ventana comunicada:

1. detener escrituras con `php artisan down --retry=60`;
2. tomar un respaldo final del estado dañado si todavía es posible;
3. confirmar el punto de recuperación y los checksums;
4. restaurar base y archivos como una unidad;
5. reinstalar la configuración secreta y permisos de filesystem;
6. ejecutar verificaciones en modo mantenimiento;
7. habilitar con `php artisan up`;
8. realizar el checklist posterior y conservar evidencias.

El bypass secreto de mantenimiento, si se usa, debe transmitirse por un canal privado y eliminarse después. Nunca se documenta su valor.

## Antes de cada actualización

Todo despliegue que incluya migraciones requiere, antes de `php artisan migrate --force`:

- backup verificable de PostgreSQL;
- backup verificable de `storage/app/documents`;
- copia segura de configuración y `APP_KEY`;
- plan de reversión de código y revisión de reversibilidad de la migración;
- espacio disponible y responsable de la ventana identificado.

La retención de `audit_logs` se definirá formalmente con la política institucional. Hasta entonces se incluye íntegramente en los backups y no se purga mediante tareas automáticas.
