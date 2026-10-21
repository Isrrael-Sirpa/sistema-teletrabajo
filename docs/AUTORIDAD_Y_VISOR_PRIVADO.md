# Autoridad formal y visor privado de archivos

Actualización aprobada el 19 de octubre de 2026, posterior al rediseño visual.

## A. Autoridad del flujo documental

Antes del ajuste, `DocumentPolicy::canActForRecipient()` aceptaba el alcance global como autoridad formal y admitía al Auxiliar para iniciar revisión. Ahora la regla central exige un usuario activo con rol `career_head` o `secretary`. Se mantienen el permiso correspondiente, el estado, la pertenencia al documento y el destinatario. Los destinatarios personales exigen además que actúe la persona indicada y que siga perteneciendo a la carrera destinataria.

| Perfil | Autoridad formal en una carrera destinataria |
| --- | --- |
| Jefe | Recibir, iniciar revisión, aprobar, devolver y rechazar según Policy y estado. |
| Secretaria | Las mismas cinco acciones, también cuando el destinatario es la unidad. |
| Auxiliar | Ninguna de esas cinco acciones; conserva consulta, descarga y colaboración autorizadas. |
| Administrador / Co-administrador | El alcance global no concede esas cinco acciones. Conservan supervisión, administración y anulación autorizada. |
| Extra | Ninguna acción formal; el grant solo concede el acceso establecido. |

No se modificaron RBAC, roles, tablas, migraciones, rutas, servicio de workflow, estados, agregación ni bloqueos. La delegación temporal ya exige una Secretaria activa de la misma carrera y sigue evaluándose por capacidad y vigencia. No permite convertir a un Auxiliar o Extra en actor formal. Sus pruebas se mantienen y se agregó la comprobación de cambio de rol durante una delegación.

El middleware existente cerraba la sesión de cualquier usuario inactivo y lo redirigía al login. Para cumplir el rechazo HTTP solicitado, las solicitudes a `documents.recipients.transition` ahora reciben 403 después de invalidar la sesión; las demás páginas conservan su redirección anterior. La Policy también niega a usuarios inactivos.

La prueba PostgreSQL de anulación simultánea con aprobación mantiene dos procesos independientes y un único ganador: el Administrador anula y la Secretaria destinataria aprueba. Conserva los controles de versiones, integridad e historial de la prueba original.

## B. Visor de archivos

Se reutiliza `GET /documentos/{document}/archivos/{documentFile}/descargar`, su controlador y `DocumentPolicy::downloadFile()`. Sin parámetro se entrega `attachment`; `presentation=inline` solicita visualización. Solo se aceptan esos dos valores. No se crearon endpoints, conversores ni dependencias.

El controlador comprueba que el archivo pertenezca al documento, autoriza la obtención de contenido, valida la presentación y verifica la existencia del archivo. El MIME procede de los metadatos validados durante la carga, nunca del nombre que recibe el navegador.

| MIME | Presentación |
| --- | --- |
| `application/pdf` | PDF nativo del navegador. |
| `image/jpeg`, `image/png` | Imagen ajustada al ancho disponible. |
| Office y ZIP | Mensaje de formato no visualizable y descarga autorizada. |

La lista de formatos de carga permanece igual; no se agregó WEBP, HTML ni SVG. Una solicitud inline no compatible responde 415 con una página administrativa, sin entregar el archivo. Si falta el archivo físico responde 404 con un mensaje administrativo, incluso en desarrollo, sin traza ni ruta interna.

El modal usa `<dialog>` nativo: cierre explícito y Escape, fondo inerte mientras está abierto, foco inicial en Cerrar y devolución del foco al enlace que lo abrió. La lista indica nombre, versión, formato y tamaño; permite elegir archivos o usar Anterior/Siguiente. En móvil la lista aparece encima del visor; en escritorio se sitúa al lado. La navegación no modifica estados ni exige haber abierto un archivo antes de decidir.

La vista destaca Visualizar archivos antes de la acción formal disponible. En borrador, el resumen Listo para enviar incluye cada archivo con Visualizar archivo y Descargar archivo antes del botón Enviar documento. Sin JavaScript, los enlaces individuales siguen utilizando la ruta privada y la presentación autorizada del servidor.

El cliente obtiene un archivo a la vez mediante la ruta autorizada con `cache: no-store`; comprueba estado HTTP y MIME antes de crear una URL `blob:` temporal en memoria para el PDF o imagen. Cambiar de archivo o cerrar cancela la solicitud pendiente, retira el contenido y revoca esa URL. No se crean URLs públicas, archivos persistidos en el navegador ni peticiones a visores externos. La representación PDF depende del soporte nativo del navegador; se conserva la descarga como alternativa.

## C. Seguridad

- Almacenamiento privado existente, UUID públicos y comprobación de pertenencia al documento.
- La misma autoridad para preview y descarga: un grant `view` permite la ficha, pero no entrega bytes. Un grant `download` efectivo permite ambas presentaciones. La vigencia y revocación se comprueban en cada solicitud.
- La confidencialidad y el scope de visibilidad existentes siguen aplicándose. La pertenencia a otra carrera no concede acceso.
- Toda entrega de bytes mantiene el evento de auditoría existente `files.downloaded`, sin crear un estado visto ni cambiar la semántica del historial documental. No se guardan paths ni contenido del archivo en auditoría.
- `X-Content-Type-Options: nosniff` permanece en todas las respuestas. Los nombres se escapan mediante Blade y el mecanismo oficial de Content-Disposition de Laravel/Symfony.
- Solo una respuesta de archivo inline autorizada y exitosa recibe `X-Frame-Options: SAMEORIGIN` y `frame-ancestors 'self'`; se preservan las demás directivas CSP configuradas. El indicador se establece en el controlador después de autorizar, no desde el parámetro del cliente. Las páginas, descargas attachment y errores conservan `DENY` y la CSP general.
- Las respuestas de contenido y de archivo no disponible usan `Cache-Control: private, no-store, max-age=0`, `Pragma: no-cache` y `Expires: 0`.

## D. Verificación y archivos

Las pruebas de autorización cubren las cinco acciones para Jefe y Secretaria; denegación de Auxiliar, Extra, Administrador, Co-administrador, otra carrera y cuentas inactivas; destinatario y estado incorrectos; y ausencia de cambios o eventos tras un rechazo. La anulación autorizada y las delegaciones conservan sus pruebas.

Las pruebas del visor cubren destinos de unidad, tres MIME inline, siete formatos de fallback, grants insuficientes, vencidos y revocados, documento restringido, descarga attachment, presentación manipulada, cruce entre documentos, cabeceras, auditoría, archivo faltante y nombres escapados. La integración PostgreSQL agrega una entrega inline autorizada y auditada que no altera el documento.

| Comprobación | Resultado |
| --- | --- |
| Suite general | 323 pruebas, 2.408 verificaciones; aprobada. |
| Integración PostgreSQL | 73 pruebas, 415 verificaciones; aprobada. |
| Flujo, visor, archivos, grants, delegaciones, cabeceras y autorización focalizados | 107 pruebas, 895 verificaciones; aprobadas tras el ajuste final de presentación. |
| Visor aislado | 15 pruebas, 222 verificaciones; aprobadas. |
| JavaScript del visor | 4 pruebas con `node --test tests/JavaScript/document-file-viewer.test.mjs`; aprobadas. |
| Pint, compilación Blade, sintaxis JS, build Vite y diff whitespace | Correctos. |
| Rutas documentales | Las 23 rutas existentes permanecen; ninguna nueva. |
| Estado de migraciones | Todas las existentes aplicadas; ninguna creada o ejecutada. |

Los tests JavaScript usan un DOM simulado y verifican PDF/imagen/fallback, navegación por tres adjuntos, respuestas tardías y cancelación, revocación de URLs, rechazo de HTML de login o errores y recorrido circular del foco. No sustituyen una prueba del lector PDF nativo de cada navegador.

Se revisó el documento real `FTEC-MA-2026-000001` en `/documentos/01a146b4-9ac1-7356-bd49-063635aca7b2`, en una sesión del Jefe de Aeronáutica proporcionada por el usuario. Al comenzar esta inspección ya estaba `received`, con versión de bloqueo 5 y seis eventos. El destinatario principal histórico es personal (Jefe), mientras Aeronáutica figura además como copia. La prueba de autoridad para Secretaria sobre un destino formal de unidad se realiza con fixtures automatizados, sin reclasificar ese documento real.

Su único adjunto es DOCX; se comprobó el fallback, nombre/versión/tamaño, ausencia de desbordamiento horizontal, foco inicial, Tab/Shift+Tab dentro del modal, Escape y retorno al enlace de apertura. Se verificaron el diseño móvil a 390 × 844 y el diseño con lista lateral a 1280 × 900. No se ejecutaron transiciones ni se descargaron archivos ni se cargaron adjuntos nuevos. Se restableció el viewport al terminar. La consulta final mantiene `received`, `lock_version=5` y seis eventos. La UAT visual de PDF/imagen queda para un adjunto adecuado que aporte el usuario; los tests ya verifican entrega, MIME y autorización.

La primera corrida PostgreSQL detectó dos supuestos antiguos sobre UAT: una Secretaria de Mecánica Automotriz está inactiva y las pruebas suponían que todas las cuentas seguían activas. Se preservó ese estado real. La prueba positiva del comando prepara su fixture activo exclusivamente dentro de `DatabaseTransactions`, sin commit; una nueva prueba comprueba que el comando no reactiva una cuenta inactiva. La prueba de tema crea un usuario temporal cuando falta un perfil activo, sin reactivar cuentas existentes. La consulta posterior confirma que la Secretaria real sigue inactiva y que el Administrador conserva su rol. No se modificó el comando UAT.

Archivos creados:

- `resources/js/document-file-viewer.js`.
- `resources/views/documents/partials/file-actions.blade.php`.
- `resources/views/documents/partials/file-viewer.blade.php`.
- `resources/views/documents/files/unavailable.blade.php`.
- `tests/Feature/DocumentFilePreviewTest.php`.
- `tests/JavaScript/document-file-viewer.test.mjs` (sin dependencias adicionales).
- Este documento.

Archivos modificados:

- `app/Policies/DocumentPolicy.php`.
- `app/Http/Middleware/EnsureUserIsActive.php`.
- `app/Http/Controllers/DocumentFileController.php`.
- `app/Http/Middleware/AddSecurityHeaders.php`.
- `app/Models/DocumentFile.php`.
- `resources/views/documents/show.blade.php`.
- `resources/js/app.js` y `resources/css/app.css`.
- `tests/Feature/DocumentWorkflowTest.php` y `tests/Feature/TemporaryDelegationTest.php`.
- `tests/PostgreSQL/PostgreSqlDocumentWorkflowTest.php` y `tests/PostgreSQL/PostgreSqlDocumentFileTest.php`.
- `tests/PostgreSQL/PostgreSqlDemoUatUsersTest.php` y `tests/PostgreSQL/PostgreSqlFrontendThemeTest.php` (fixtures transaccionales).
- `README.md`, `docs/FASE_10_ARCHIVOS_ADJUNTOS.md`, `docs/FASE_11_ESTADOS_FLUJO_DOCUMENTAL.md` y `docs/REDISENO_VISUAL.md`.

Se preservan las modificaciones anteriores del rediseño/multicarrera presentes al iniciar esta intervención. No se cargaron documentos ni adjuntos institucionales para las pruebas automáticas.
