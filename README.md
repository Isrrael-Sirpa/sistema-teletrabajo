# Sistema de Gestión Documental y Teletrabajo Administrativo

Plataforma web institucional para la Facultad de Tecnología de la Universidad Mayor de San Andrés. Centralizará el envío, recepción, revisión, aprobación, devolución, rechazo, seguimiento y consulta de documentos entre carreras y Kardex.

## Estado

- Fase 0: análisis arquitectónico aprobado y cerrado.
- Fase 1: base Laravel implementada, probada y documentada.
- Fase 2: esquema relacional institucional implementado y validado en MySQL 8.4 (Laragon) con todas las tablas del dominio nombradas en español.
- Fase 3: autenticación básica implementada, probada y documentada.
- Fase 4: roles y permisos implementados con RBAC y validados en PostgreSQL.
- Fase 5: Facultad, Kardex y CRUD dinámico de carreras implementados y validados.
- Fase 6: gestión global y por carrera de usuarios implementada y validada.
- Fase 7: catálogo de tipos documentales implementado, sembrado y validado.
- Fase 8: gestión documental básica en estado borrador implementada y validada.
- Fase 9: destinatarios de unidad y persona para borradores implementados y validados.
- Fase 10: archivos adjuntos privados, integridad y descarga autorizada implementados y validados.
- Fase 11: envío oficial, correlativos y flujo principal por destinatario implementados y validados.
- Fase 12: historial documental append-only, paginado y transaccional implementado y validado.
- Fase 13: versionado y corrección de documentos devueltos implementados y validados.
- Fase 14: notificaciones internas transaccionales y centro de lectura implementados y validados.
- Fase 15: fechas límite y recordatorios internos idempotentes implementados y validados.
- Fase 16: dashboard operativo por rol implementado, probado y validado en PostgreSQL.
- Fase 17: gestión segura de accesos explícitos implementada y validada en PostgreSQL.
- Fase 18: auditoría transversal segura, inmutable y consultable implementada y validada en PostgreSQL.
- Fase 19: delegaciones temporales de Jefatura implementadas y validadas en PostgreSQL, incluida concurrencia real.
- Fase 20: búsqueda avanzada documental implementada y validada en PostgreSQL.
- Fase 21: reportes documentales y exportación CSV controlada implementados y validados en PostgreSQL.
- Fase 22: anulación documental formal, irreversible y trazable implementada y validada en PostgreSQL.
- Fase 23: seguridad, configuración de producción, despliegue y recuperación implementados y validados.
- Fase 24: aceptación automatizada, regresión final, cierre técnico y preparación de entrega completados.
- Fase 25: actividades de la unidad implementadas con asignación, avances, resultados, revisión y vínculo opcional a correspondencia.

La página pública principal es el login institucional. Después de autenticar, `/panel` presenta el trabajo operativo correspondiente al rol y al ámbito autorizado. El RBAC, las Policies por ámbito y el flujo documental permanecen como fuente de autorización.

## Tecnologías

- Laravel 13
- PHP 8.3+ (PHP 8.3 / 8.4)
- MySQL 8.4 (Laragon / InnoDB) con tablas en español
- Blade
- Livewire 4
- Tailwind CSS 4
- Vite
- PHPUnit
- spatie/laravel-permission 8

## Requisitos de desarrollo

- PHP 8.3 o superior con `PDO`, `pdo_mysql` y extensiones estándar
- Composer 2
- Node.js compatible con Vite y npm
- MySQL 8.0+ / 8.4 (disponible en Laragon)

El entorno local de desarrollo opera sobre Laragon con MySQL 8.4.3 (`127.0.0.1:3306`, base de datos `sistema_teletrabajo`).

## Base de Datos y Tablas en Español

Todas las tablas de dominio institucional han sido configuradas y migradas en **español**:

| Tabla en Español | Responsabilidad |
|---|---|
| `unidades_organizacionales` | Jerarquía institucional (Facultad, Kardex y 9 Carreras) |
| `usuarios` | Cuentas institucionales y funcionarios |
| `roles` / `permisos` / `rol_tiene_permisos` | Control de acceso basado en roles (Spatie RBAC) |
| `tipos_documentos` | Catálogo de tipos de documento oficiales |
| `contadores_documentos` | Correlativos atómicos por unidad y año |
| `documentos` | Trámites y expedientes documentales |
| `destinatarios_documentos` | Destinatarios por unidad o persona |
| `tareas_revision_documentos` | Revisiones internas de Secretaría y Auxiliares |
| `versiones_documentos` / `archivos_documentos` | Versionado inmutable y adjuntos privados |
| `observaciones_documentos` | Anotaciones y notas internas |
| `concesiones_acceso_documentos` | Concesiones temporales de lectura y descarga para usuarios Extra |
| `eventos_documentos` | Trazabilidad documental inmutable (append-only) |
| `delegaciones_temporales` | Delegaciones transitorias de Jefatura |
| `registros_auditoria` | Auditoría de acciones del sistema |
| `notificaciones` | Notificaciones internas de base de datos |
| `sesiones` | Sesiones HTTP en base de datos |

## Acceso

- Login público: `/`
- Panel operativo protegido: `/panel`
- Sesiones almacenadas en MySQL mediante `SESSION_DRIVER=database` (tabla `sesiones`)
- Solo los usuarios con estado `active` pueden autenticarse

La recuperación de contraseña conserva la estructura oficial de Laravel, pero el envío de correo institucional todavía no está habilitado. Consulte [la documentación de la Fase 3](docs/FASE_3_AUTENTICACION.md).

### Primera cuenta Administrador

Después de aplicar las migraciones y seeders institucionales, cree la primera cuenta de forma interactiva:

```bash
php artisan app:create-initial-admin
```

El comando solicita nombre completo, correo y confirmación de contraseña. La contraseña se introduce de forma oculta, se valida con la misma política del módulo y no se imprime ni se registra. Solo funciona mientras no exista ninguna cuenta con rol Administrador; las cuentas administrativas posteriores deben crearse desde el CRUD autorizado.

## Roles y permisos

El proyecto utiliza `spatie/laravel-permission` con seis roles institucionales y permisos atómicos verificables mediante Gate o middleware. Los nombres internos se resuelven por slug, nunca por identificadores numéricos. El ámbito por unidad se aplicará en Policies posteriores.

Consulte [la documentación de la Fase 4](docs/FASE_4_ROLES_PERMISOS.md) y [la matriz exacta](docs/seguridad/MATRIZ_PERMISOS.md).

## Estructura organizacional

La Facultad de Tecnología, su Kardex y las nueve carreras confirmadas se crean mediante `InstitutionalStructureSeeder`, incluido en `DatabaseSeeder`. Sus nombres y siglas iniciales se encuentran en `config/institutional_careers.php`; ejecutar el seeder de nuevo no duplica carreras ni sobrescribe una sigla o un estado modificados posteriormente por el Administrador. Las carreras se administran desde `/administracion/unidades`, donde la sigla puede editarse; el cambio solo afecta códigos de documentos enviados después. Los códigos históricos almacenados en `documents.code` no se recalculan.

Consulte [la documentación de la Fase 5](docs/FASE_5_CARRERAS_KARDEX.md).

## Gestión de usuarios

La administración se encuentra en `/administracion/usuarios`. Administrador y Co-administrador trabajan con ámbito global; el Jefe de Carrera solo puede gestionar Secretaria, Auxiliar y Extra de su propia carrera. Las cuentas se activan o desactivan sin eliminación física y el restablecimiento de contraseña es una acción separada.

Para pruebas locales/UAT, después de cargar la estructura institucional y crear el Administrador, se pueden crear nueve Jefes y nueve Secretarias ficticios mediante:

```bash
php artisan app:create-demo-uat-users --generate-password
```

La primera ejecución genera una contraseña aleatoria que se guarda únicamente como `DEMO_USER_INITIAL_PASSWORD` en el `.env` local ignorado por Git. El comando no la imprime; puede consultarse directamente en ese archivo privado si se necesita iniciar sesión con las cuentas de demostración. También puede establecerse esa variable localmente antes de ejecutar el comando sin la opción. Se aplica la política institucional de contraseñas. El comando rechaza producción, utiliza correos `@example.test`, es idempotente y no forma parte de `DatabaseSeeder`. No incluya el `.env` en el repositorio ni use estas cuentas en producción.

Consulte [la documentación de la Fase 6](docs/FASE_6_GESTION_USUARIOS.md).

## Tipos documentales

El catálogo se administra desde `/administracion/tipos-documentales`. Los tipos pueden crearse, editarse, activarse y desactivarse sin eliminación física. El seeder institucional carga los 12 tipos iniciales sin sobrescribir personalizaciones y solo los tipos activos pueden seleccionarse al crear documentos.

Consulte [la documentación de la Fase 7](docs/FASE_7_TIPOS_DOCUMENTOS.md).

## Borradores documentales

La bandeja inicial está disponible en `/documentos`. Los usuarios autorizados pueden crear, consultar, retomar y editar borradores sin consumir código oficial. La unidad de origen se deriva de la carrera para Jefe y Secretaria; los usuarios globales seleccionan una unidad institucional activa.

La edición utiliza `lock_version` para impedir sobrescrituras silenciosas entre pestañas. Solo el creador puede descartar lógicamente su propio borrador nunca enviado y sin información dependiente; la fila y su trazabilidad permanecen preservadas y dejan de aparecer en rutas o listados normales. El envío oficial y las decisiones están disponibles cuando el borrador posee al menos un destinatario preparado.

Consulte [la documentación de la Fase 8](docs/FASE_8_GESTION_DOCUMENTAL_BASICA.md).

## Destinatarios preparados

Dentro del detalle de un borrador pueden agregarse carreras activas, Kardex y personas activas de una unidad institucional. Cada destino se clasifica como “Requiere respuesta” o “Copia informativa”. La lista puede modificarse mientras el documento continúe en borrador y comparte el control de concurrencia `lock_version` con la edición general.

Los destinatarios permanecen en estado interno `prepared`: agregarlos no envía el documento, no genera código, no consume correlativo y no crea notificaciones.

Retirar un destinatario preparado utiliza eliminación lógica. Si se agrega nuevamente el mismo destino, el servicio restaura la fila existente y conserva su identificador histórico; no existe una acción pública de restauración.

Consulte [la documentación de la Fase 9](docs/FASE_9_DESTINATARIOS.md).

## Archivos adjuntos privados

En el detalle de un borrador se pueden adjuntar varios PDF, documentos Office, imágenes o ZIP. Cada archivo admite hasta 20 MB y el lote inicial permite 10 archivos, ambos límites configurables. Los archivos se guardan con nombres UUID en el disco privado `documents`; se registra nombre original, MIME detectado por el servidor, tamaño, SHA-256, autor y versión inicial.

No existe acceso desde `public/storage`. Toda descarga pasa por autenticación, permiso y Policy sobre el documento. Los archivos pueden retirarse únicamente mientras el documento sigue en borrador y la operación comparte `lock_version` con la edición y los destinatarios.

Consulte [la documentación de la Fase 10](docs/FASE_10_ARCHIVOS_ADJUNTOS.md).

## Flujo documental oficial

El envío asigna de forma atómica un código como `FTEC-SIS-2026-000001`, registra remitente y fecha, y formaliza todos los destinatarios preparados. Los correlativos se administran por unidad de origen y año con bloqueo PostgreSQL; los borradores no consumen números.

La recepción es explícita. Después, cada destino formal puede entrar en revisión y terminar aprobado, devuelto o rechazado según RBAC, Policy y participación real. Las copias informativas pueden recibirse, pero no deciden ni bloquean la aprobación general. `lock_version` protege documento y destinatario frente a decisiones simultáneas.

Consulte [la documentación de la Fase 11](docs/FASE_11_ESTADOS_FLUJO_DOCUMENTAL.md).

## Historial documental

El detalle del documento presenta una línea de tiempo inmutable con los cambios relevantes del borrador y del ciclo oficial. Cada evento conserva actor, fecha y hora exacta, destinatario relacionado, transición, motivo y metadatos administrativos seguros. La vista utiliza el huso horario `America/La_Paz`, muestra primero lo más reciente y pagina 15 eventos por vez.

Los eventos se escriben dentro de la misma transacción que la acción principal. No existen rutas para modificarlos o eliminarlos, y el historial hereda íntegramente la autorización del documento. Esta responsabilidad permanece separada de `audit_logs`.

Consulte [la documentación de la Fase 12](docs/FASE_12_HISTORIAL_DOCUMENTAL.md).

## Versionado y corrección

Un documento formalmente devuelto puede recibir una versión nueva sin modificar archivos, hashes, decisiones ni eventos anteriores. El usuario autorizado del origen primero prepara el conjunto documental corregido y, después de revisarlo, envía la corrección mediante una acción separada.

Solo los destinatarios formales que devolvieron el documento regresan a `sent`. Los destinatarios aprobados y las copias informativas conservan su estado. El código oficial y el correlativo no cambian, y todas las versiones continúan disponibles mediante descarga privada autorizada.

Consulte [la documentación de la Fase 13](docs/FASE_13_VERSIONADO_CORRECCION.md).

## Notificaciones internas

El centro de notificaciones está disponible en `/notificaciones` para toda cuenta autenticada y activa. La navegación muestra `Notificaciones (N)`, permite separar avisos leídos y no leídos, abrir el documento sujeto a su `DocumentPolicy`, marcar un aviso o todos como leídos y paginar ambas listas.

El envío, la recepción, el inicio de revisión, las decisiones y las correcciones generan avisos en la tabla oficial `notifications` únicamente después de confirmar la transacción principal. Los destinos por unidad notifican a Jefe y Secretaria activos; los destinos personales notifican a la persona indicada y la resolución elimina duplicados. Extra y usuarios inactivos no reciben avisos documentales ordinarios.

Los documentos restringidos usan texto genérico y no guardan asunto, motivo ni código en el aviso. Por ahora el único canal es `database`; no se envía correo real.

Consulte [la documentación de la Fase 14](docs/FASE_14_NOTIFICACIONES_INTERNAS.md).

## Fechas límite y recordatorios

La condición de vencimiento se calcula sin alterar `general_status`. Un trámite puede continuar `sent`, `received`, `in_review` o `returned` y, al mismo tiempo, estar vigente, próximo a vencer o vencido. Los borradores y los estados terminales `approved`, `rejected` y `annulled` no generan alertas activas; un documento sin `due_at` nunca vence.

El umbral inicial es de tres días calendario. El listado documental ofrece filtros para próximos a vencer y vencidos, mientras listado y detalle muestran la fecha y badges textuales. Una corrección conserva la fecha límite original.

El comando manual es:

```bash
php artisan documents:process-deadlines
```

El Scheduler lo ejecuta diariamente a las 08:00 en `America/La_Paz`, con `withoutOverlapping()`. Cada recordatorio también posee una clave UUID determinista por documento, fecha límite, hito y usuario, y se crea bajo bloqueo transaccional; por ello dos ejecuciones simultáneas no duplican avisos.

En producción, el ejecutor de Laravel debe invocarse cada minuto. Ejemplo de cron Linux:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

En Windows, configure el Programador de tareas para ejecutar `php artisan schedule:run` cada minuto desde el directorio del proyecto. Un servicio `systemd` también puede mantener `php artisan schedule:work` cuando se adopte ese modelo de despliegue.

Consulte [la documentación de la Fase 15](docs/FASE_15_FECHAS_LIMITE_RECORDATORIOS.md).

## Dashboard operativo

Después de iniciar sesión, `/panel` muestra trabajo real y accesos rápidos según el rol. Administrador y Co-administrador obtienen el resumen institucional; Jefe y Secretaria ven recepción, revisión, decisiones, correcciones y vencimientos de su ámbito; Auxiliar recibe únicamente tareas de colaboración; Extra solo ve documentos con una concesión explícita vigente.

Cada tarjeta abre el listado con un filtro equivalente. La lista “Necesitan su atención” se limita a cinco elementos y prioriza vencidos, urgentes, próximos a vencer, importantes y normales. Los asuntos restringidos se ocultan en el resumen global. El panel no usa caché, no carga colecciones completas y no sustituye la autorización de `DocumentPolicy`.

Consulte [la documentación de la Fase 16](docs/FASE_16_DASHBOARD_OPERATIVO.md).

## Accesos explícitos

Los usuarios autorizados pueden conceder a un Extra acceso `Ver` o `Ver y descargar` sobre un documento específico desde su detalle. Jefe y Secretaria quedan limitados a su ámbito; Administrador y Co-administrador conservan alcance global. La fecha de vencimiento es opcional y se evalúa automáticamente.

Los cambios de nivel y revocaciones crean ciclos históricos trazables: no se eliminan ni sobrescriben concesiones anteriores. Un acceso explícito nunca convierte a Extra en destinatario ni habilita recepción, revisión o decisiones. Toda descarga continúa pasando por almacenamiento privado y `DocumentPolicy`.

Consulte [la documentación de la Fase 17](docs/FASE_17_ACCESOS_EXPLICITOS.md).

## Auditoría transversal

Administrador y Co-administrador pueden consultar `/administracion/auditoria`. El módulo registra autenticación relevante, cambios administrativos, envío oficial, descargas privadas y gestión de accesos explícitos. No duplica las transiciones ordinarias que ya pertenecen al historial documental.

Los registros son append-only, conservan actor, recurso, fecha, IP, agente y cambios permitidos. Una sanitización central excluye contraseñas, hashes, tokens, sesiones, cookies, secretos, archivos y contenido documental sensible. Para documentos restringidos se conservan únicamente identificadores y contexto operativo mínimo.

El listado permite filtrar por fechas, actor, acción, recurso y resultado, y pagina 25 filas por defecto mediante `AUDIT_LOGS_PER_PAGE`. Solo existen rutas de lectura.

Consulte [la documentación de la Fase 18](docs/FASE_18_AUDITORIA_TRANSVERSAL.md).

## Delegaciones temporales de Jefatura

El módulo `/administracion/delegaciones` permite a Administrador, Co-administrador y al Jefe titular de una carrera autorizar temporalmente a una Secretaria activa de esa misma carrera. Cada delegación tiene inicio inclusivo, fin exclusivo, motivo y un subconjunto explícito de capacidades de Jefatura. La Secretaria conserva su rol y sus permisos permanentes; la autorización temporal se consulta en cada petición y nunca se copia a las tablas de asignación de Spatie.

La vigencia se deriva de las fechas y de `revoked_at`, sin Scheduler ni cambio de sesión. Una revocación exige motivo, conserva la fila y sus capacidades históricas y surte efecto en la siguiente autorización. La creación bloquea transaccionalmente la fila de la carrera antes de comprobar solapamientos, por lo que dos altas concurrentes no pueden dejar dos Jefaturas temporales para el mismo instante.

Consulte [la documentación de la Fase 19](docs/FASE_19_DELEGACIONES_TEMPORALES.md).

## Búsqueda avanzada documental

La bandeja `/documentos` incorpora búsqueda por código, asunto o descripción y filtros combinables por tipo, unidades, destinatario personal, estados, prioridad, clasificación, creador, remitente, fechas, vencimiento, corrección pendiente y versión mínima. Los filtros se conservan al paginar, el orden está limitado a campos permitidos y la cantidad por página se configura con `DOCUMENT_SEARCH_PER_PAGE`.

Toda consulta comienza en el mismo ámbito `Document::visibleTo()` utilizado por las rutas documentales. Por ello, los resultados, sus totales y las opciones disponibles respetan rol, carrera, participación formal, concesiones explícitas y delegaciones temporales vigentes. Los documentos descartados y los destinatarios retirados permanecen fuera del flujo normal.

Consulte [la documentación de la Fase 20](docs/FASE_20_BUSQUEDA_AVANZADA.md).

## Reportes y exportaciones

El módulo `/reportes/documentos` presenta resúmenes por estado, unidad de origen, prioridad y tipo, además de reportes de enviados, recibidos, aprobados, devueltos, rechazados, vencidos y próximos a vencer. El detalle se pagina y permite combinar los filtros autorizados de la búsqueda avanzada con un período local inclusivo.

Administrador y Co-administrador poseen alcance global; Jefe, Secretaria y Auxiliar conservan exactamente su ámbito documental, incluidas las delegaciones temporales efectivas. Extra no puede acceder al módulo y su visibilidad documental ordinaria continúa limitada exclusivamente a `document_access_grants` vigentes, nunca a su carrera ni a destinatarios.

La exportación CSV conserva los filtros activos, utiliza una lista explícita de campos, UTF-8 y mitigación de fórmulas. No deja archivos permanentes, se audita sin copiar asuntos ni contenido y rechaza conjuntos mayores que `REPORT_EXPORT_MAX_ROWS`. XLSX y PDF quedan pendientes de una evaluación y autorización expresa de dependencia.

Consulte [la documentación de la Fase 21](docs/FASE_21_REPORTES_EXPORTACIONES.md).

## Anulación documental

Administrador y Co-administrador pueden dejar sin efecto un documento oficial desde su detalle, indicando un motivo obligatorio. La operación es irreversible y transaccional: registra actor y fecha, incrementa el control de versión, crea historial `document_annulled`, genera auditoría segura y notifica internamente a participantes activos.

Anular no borra ni usa `deleted_at`. Se conservan código, destinatarios y sus estados, archivos, versiones, eventos, grants y notificaciones previas. El expediente queda visible y descargable para quienes mantengan autorización, pero ya no puede recibirse, revisarse, decidirse, corregirse ni reenviarse, y desaparece de pendientes y vencimientos. Los documentos restringidos mantienen mensajes genéricos.

Consulte [la documentación de la Fase 22](docs/FASE_22_ANULACION_DOCUMENTAL.md).

## Instalación local

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
```

Configure en `.env` una base PostgreSQL existente:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_teletrabajo
DB_USERNAME=postgres
DB_PASSWORD=
DB_SSLMODE=prefer
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
SESSION_SECURE_COOKIE=false
```

Después ejecute:

```bash
php artisan migrate --seed
php artisan app:create-initial-admin
composer run dev
```

En PowerShell puede usar `Copy-Item .env.example .env` en lugar de `copy`.

## Configuración importante

- Zona horaria: `America/La_Paz`.
- Idioma de interfaz: español.
- Archivos documentales: disco privado `documents`, ubicado localmente en `storage/app/documents`.
- Límite inicial: 20 MB por archivo, configurable con `DOCUMENT_MAX_FILE_SIZE_KB`.
- Lote inicial: 10 archivos por solicitud, configurable con `DOCUMENT_MAX_FILES_PER_REQUEST`.
- Motivos de decisión: máximo 2000 caracteres, configurable con `DOCUMENT_DECISION_REASON_MAX_LENGTH`.
- Correlativos: 6 dígitos, configurable con `DOCUMENT_CODE_DIGITS`.
- Historial: 15 eventos por página, configurable con `DOCUMENT_HISTORY_PER_PAGE`.
- Búsqueda documental: 15 resultados por página, configurable con `DOCUMENT_SEARCH_PER_PAGE`.
- Reportes: 25 resultados por página, configurable con `REPORTS_PER_PAGE`.
- Exportaciones: máximo 5000 filas, configurable con `REPORT_EXPORT_MAX_ROWS`.
- Motivos de anulación: máximo 2000 caracteres, configurable con `DOCUMENT_ANNULMENT_REASON_MAX_LENGTH`.
- Notificaciones: 15 avisos por sección y página, configurable con `DOCUMENT_NOTIFICATIONS_PER_PAGE`.
- Auditoría: 25 registros por página, configurable con `AUDIT_LOGS_PER_PAGE`.
- Próximo a vencer: 3 días, configurable con `DOCUMENT_DUE_SOON_DAYS`.
- Hora diaria de recordatorios: 08:00, configurable con `DOCUMENT_DEADLINE_REMINDER_HOUR`.
- Colas iniciales: `sync`; actualmente no se necesita worker ni Redis.
- Cache inicial: base de datos.

Los archivos documentales nunca deben exponerse mediante `public/storage`. Toda descarga pasa por autenticación y una Policy.

## Comandos de verificación

```bash
composer test
npm run build
php artisan route:list
php artisan about
composer audit
```

## Documentación técnica

- [Análisis arquitectónico](docs/arquitectura/FASE_0_ANALISIS_ARQUITECTONICO.md)
- [Modelo de datos](docs/arquitectura/MODELO_DATOS.md)
- [Flujo documental](docs/arquitectura/FLUJO_DOCUMENTAL.md)
- [Matriz de permisos](docs/seguridad/MATRIZ_PERMISOS.md)
- [Plan de implementación](docs/PLAN_IMPLEMENTACION.md)
- [Fase 2: base de datos](docs/FASE_2_BASE_DE_DATOS.md)
- [Fase 6: gestión de usuarios](docs/FASE_6_GESTION_USUARIOS.md)
- [Fase 7: tipos de documentos](docs/FASE_7_TIPOS_DOCUMENTOS.md)
- [Fase 8: gestión documental básica](docs/FASE_8_GESTION_DOCUMENTAL_BASICA.md)
- [Fase 9: destinatarios](docs/FASE_9_DESTINATARIOS.md)
- [Fase 10: archivos adjuntos](docs/FASE_10_ARCHIVOS_ADJUNTOS.md)
- [Fase 11: estados y flujo documental](docs/FASE_11_ESTADOS_FLUJO_DOCUMENTAL.md)
- [Fase 12: historial documental](docs/FASE_12_HISTORIAL_DOCUMENTAL.md)
- [Fase 13: versionado y corrección](docs/FASE_13_VERSIONADO_CORRECCION.md)
- [Fase 14: notificaciones internas](docs/FASE_14_NOTIFICACIONES_INTERNAS.md)
- [Fase 15: fechas límite y recordatorios](docs/FASE_15_FECHAS_LIMITE_RECORDATORIOS.md)
- [Fase 16: dashboard operativo](docs/FASE_16_DASHBOARD_OPERATIVO.md)
- [Fase 17: accesos explícitos](docs/FASE_17_ACCESOS_EXPLICITOS.md)
- [Fase 18: auditoría transversal](docs/FASE_18_AUDITORIA_TRANSVERSAL.md)
- [Fase 19: delegaciones temporales](docs/FASE_19_DELEGACIONES_TEMPORALES.md)
- [Fase 20: búsqueda avanzada](docs/FASE_20_BUSQUEDA_AVANZADA.md)
- [Fase 21: reportes y exportaciones](docs/FASE_21_REPORTES_EXPORTACIONES.md)
- [Fase 22: anulación documental](docs/FASE_22_ANULACION_DOCUMENTAL.md)
- [Fase 23: producción y seguridad](docs/FASE_23_PRODUCCION_SEGURIDAD.md)
- [Fase 24: cierre técnico](docs/FASE_24_CIERRE_TECNICO.md)
- [Fase 25: acceso de estudiantes](docs/FASE_25_ACCESO_ESTUDIANTES.md)
- [Fase 25: actividades de la unidad](docs/FASE_25_ACTIVIDADES_UNIDAD.md)
- [Rediseño visual y experiencia de usuario](docs/REDISENO_VISUAL.md)
- [Iteración de producto institucional: UX y validación](docs/PRODUCTO_INSTITUCIONAL_UX.md)
- [Autoridad formal y visor privado de archivos](docs/AUTORIDAD_Y_VISOR_PRIVADO.md)
- [Matriz de pruebas de aceptación](docs/PRUEBAS_ACEPTACION.md)
- [Checklist de entrega](docs/CHECKLIST_ENTREGA.md)
- [Mejoras futuras](docs/MEJORAS_FUTURAS.md)
- [Despliegue de producción](docs/DESPLIEGUE_PRODUCCION.md)
- [Backup y restauración](docs/BACKUP_RESTORE.md)

## Convenciones

- Código, clases y permisos técnicos en inglés.
- Textos, mensajes de validación y documentación funcional en español.
- Controllers delgados, Form Requests para validación y Policies para autorización.
- Casos de uso críticos dentro de servicios transaccionales.
- Historial documental y auditoría son registros separados y append-only.
- No se eliminan físicamente documentos oficiales desde operaciones ordinarias.

## Producción

La configuración inicial usa Linux, Nginx, PHP-FPM, PostgreSQL 18, HTTPS y Laravel Scheduler. No requiere Redis ni worker mientras no existan tareas en cola. El document root debe ser exclusivamente `/public`; los documentos permanecen en almacenamiento privado.

La plantilla `.env.example` contiene valores seguros de producción sin secretos. Para desarrollo local deben aplicarse explícitamente los valores locales indicados en la sección de instalación.

Antes del primer despliegue consulte [la guía de producción](docs/DESPLIEGUE_PRODUCCION.md) y [el procedimiento de backup/restauración](docs/BACKUP_RESTORE.md). PHP 8.5 no presenta bloqueos en las restricciones instaladas, pero debe ejecutar la suite completa con el binario 8.5 real antes de adoptarlo; PHP 8.3 continúa siendo la versión comprobada.

La aceptación técnica automatizada está cerrada. Antes de la apertura real permanecen obligatorias la configuración del servidor definitivo, una restauración aislada de backup y la UAT con personal institucional descrita en [PRUEBAS_ACEPTACION.md](docs/PRUEBAS_ACEPTACION.md).

