# Fase 25: acceso de estudiantes

## Objetivo

Incorporar el rol Estudiante para que presente documentos desde su carrera, adjunte archivos y consulte el seguimiento de sus propios envíos sin ampliar la visibilidad de las demás carreras ni de los módulos administrativos.

## Cuenta y alcance

- Un administrador crea la cuenta desde **Administración > Usuarios**, asigna el rol **Estudiante** y la carrera correspondiente.
- La cuenta debe estar asociada a una unidad de tipo `career`.
- El estudiante solo consulta documentos que creó o envió desde su propia carrera. La autorización también se aplica a las rutas y descargas, no únicamente al menú.
- No puede administrar usuarios, carreras, tipos documentales, reportes, delegaciones, revisiones ni decisiones de destinatario.

## Presentación documental

El estudiante puede crear un borrador, indicar el asunto y el motivo, adjuntar archivos y enviar. En el formulario solo ve la sección de identificación del documento; no aparece «¿Cómo debe atenderse?». El catálogo disponible se limita a los tipos «Solicitud» y «Otros», y el servidor valida la misma restricción. El origen se fija a su carrera. El sistema mantiene el almacenamiento privado y el flujo documental oficial, incluidos el correlativo, la fecha de envío, el historial y la autorización de descargas.

Los destinos habilitados son su propia carrera o Kardex. No se permiten destinatarios personales ni elegir otra carrera. Esta validación se realiza también en el servicio de destinatarios para rechazar solicitudes alteradas fuera de la interfaz.

La bandeja y el detalle permiten revisar fecha de envío, unidad destinataria, estado y motivo indicado. Los borradores propios se mantienen disponibles para que el estudiante pueda completar su presentación antes del envío.

## Notificaciones

Se conserva el centro actual de notificaciones; no se crea un sistema de mensajería separado. El envío de un documento notifica a las personas responsables de la unidad destinataria según las reglas existentes. Los avisos de recepción, revisión o decisión se dirigen también al creador o remitente activo, por lo que el estudiante puede recibir novedades sobre su solicitud.

## Permisos del rol

El rol recibe únicamente los permisos necesarios para ver sus documentos, crear y editar sus borradores, descartarlos, enviarlos, adjuntar archivos y descargar sus propios archivos. No recibe permisos para recibir, revisar, aprobar, devolver, rechazar, emitir notificaciones, gestionar unidades o consultar reportes.

## Actualización de una instalación

```bash
git pull origin trabajo-m2
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan optimize:clear
```

Los seeders usan `updateOrCreate` y sincronizan los permisos de los roles del catálogo. Después, un administrador puede asignar el rol Estudiante a las cuentas correspondientes desde el módulo de usuarios.

## Publicación en GitHub

Los cambios de esta fase se integran en la rama `trabajo-m2` y se publican en el remoto `origin`. Para obtenerlos en otro entorno, ejecutar los comandos de actualización anteriores.
