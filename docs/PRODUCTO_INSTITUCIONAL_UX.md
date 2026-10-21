# Iteración de producto institucional: experiencia y presentación

Fecha: 19 de octubre de 2026. Intervención posterior a las fases funcionales aprobadas.

## Alcance y reglas conservadas

Se revisaron las vistas existentes bajo los criterios **funciona, se entiende y se ve terminada**. Esta iteración modifica presentación e interacción progresiva, no el modelo documental. No agrega dependencias, tablas, migraciones, endpoints, roles ni permisos. No modifica servicios documentales, códigos históricos ni la configuración de PostgreSQL. Tampoco crea usuarios o documentos institucionales para probar.

El repositorio ya tenía cambios de autoridad documental, visor privado y actualización asíncrona de destinatarios al comenzar esta intervención. Se preservaron; no deben confundirse con cambios de backend de esta iteración.

## Base común

- Identificación tipográfica «FT / UMSA», nombre completo de Facultad y Universidad, navegación azul institucional y acentos de las carreras existentes. El icono SVG de la pestaña es una marca tipográfica del sistema, **no un escudo o logotipo oficial inventado**. No se añadieron recursos externos ni fuentes remotas.
- Encabezados con propósito de pantalla, acción principal y explicación breve. Rastro de navegación «Inicio / módulo / pantalla» y enlaces de regreso al contexto.
- Mensaje de éxito único en el layout autenticado. Resúmenes de errores con foco por teclado y errores vinculados a los campos mediante `aria-describedby` y `aria-invalid`.
- Secciones de formulario, ayudas, estados vacíos, botones y jerarquía tipográfica centralizados en `resources/css/app.css`. Sin cambios en nombres, valores o métodos de los formularios.
- Tablas detalladas convertidas a filas tipo tarjeta por debajo de 768 px, conservando encabezados, captions y roles de tabla. Las etiquetas móviles se asocian a cada celda mediante `data-label`.
- Menú móvil con fondo inerte, foco contenido, cierre con Escape y devolución de foco. Perfil con cierre al pulsar fuera, salir con Tab o usar Escape. Enlace visible al recibir foco para saltar al contenido principal.
- Progreso de formularios POST nativos y prevención de reenvíos repetidos dentro de la misma pantalla. No sustituye los bloqueos transaccionales ni la validación del servidor. Se conservan los botones en la serialización del formulario; al volver con el historial se restaura su estado.
- El manejador común respeta formularios asíncronos que cancelan el envío nativo, confirmaciones canceladas, búsquedas GET y descargas en otra pestaña. Los flujos existentes siguen disponibles sin JavaScript.
- Login con mostrar/ocultar contraseña por acción explícita, sin repoblarla ni registrar su contenido.

## Revisión por módulo

| Módulo | A. Funciona: contrato preservado | B. Se entiende: mejora | C. Presentación y consistencia |
| --- | --- | --- | --- |
| Acceso | Autenticación, CSRF, rate limit y sesión existentes | Correo institucional, ayuda de acceso, error claro | Identidad de Facultad, diseño adaptable, contraseña oculta por defecto |
| Inicio | Resúmenes y enlaces sujetos al ámbito actual | Trabajo prioritario y siguiente paso; vacío de Extra distingue autorización de pendientes | Jerarquía compartida y tarjetas |
| Búsqueda documental | Filtros y búsqueda autorizada del servidor | Código/asunto y estado visibles; filtros adicionales desplegables, abiertos cuando están en uso | Resultados con rango; tabla/tarjetas sin ancho mínimo fijo |
| Crear / editar borrador | Mismos campos, validaciones y versión de bloqueo | Guardar no equivale a enviar; identificación y atención en secciones; siguiente paso explícito | Ayuda junto al campo y errores accesibles |
| Detalle y destinatarios | Gates, transiciones, copias y principales sin cambios | Resumen de actuación, guía del borrador, retorno a documentos; se mantiene la actualización inmediata de destinatarios | Tabla de preparación adaptable; se conserva visor y tarjetas de seguimiento |
| Historial | Eventos append-only y paginación existentes | Paginar mantiene el historial abierto y enlaza a su sección | Lectura cronológica y secciones secundarias plegables |
| Usuarios | UserPolicy, rol, estado y carrera existentes | Datos personales / acceso institucional; contraseña separada de edición; consecuencias de activar/desactivar | Errores por campo, retornos y acciones contextualizadas |
| Carreras y unidades | Facultad/Kardex protegidos; siglas editables | Se explica efecto solo en documentos futuros; se exponen filtros ya admitidos | Conteos, tabla/tarjetas y formulario coherentes |
| Tipos documentales | Catálogo y código interno estable | Nombre reconocible, descripción opcional y estado por acción separada | Vacíos accionables, ayudas y errores |
| Delegaciones | Responsables, vigencia y funciones existentes | Tres secciones; hora de Bolivia; finalización anticipada con motivo en bloque separado | Formularios y estados coherentes |
| Notificaciones | Acciones de lectura existentes | Leer aviso no equivale a recibir o aprobar un documento | Nuevas/leídas y vacío con siguiente paso |
| Reportes | Consultas, filtros y exportación existentes | Alcance y uso de CSV explicados | Resúmenes descriptivos, tablas/tarjetas y resultados |
| Auditoría | Consulta autorizada, registros inmutables | Filtros, horario y detalles legibles | Columnas móviles etiquetadas y expansión de detalle |
| Errores HTTP | Códigos y respuestas existentes | Explicación y siguiente paso; nunca pedir contraseña | Identidad institucional autónoma, incluso sin bundle Vite |

## Archivos de esta iteración

Base e interacción:

- `public/favicon.svg` (nuevo).
- `resources/css/app.css`, `resources/js/app.js`.
- `resources/js/institutional-ui.js` (nuevo).
- `resources/views/components/layouts/app.blade.php` y `authenticated.blade.php`.

Pantallas:

- `resources/views/auth/login.blade.php`, `dashboard.blade.php`.
- `resources/views/documents/{index,create,edit,_form,show}.blade.php`.
- `resources/views/users/{index,create,edit,_form,show,password}.blade.php`.
- `resources/views/organizational-units/{index,create,edit,_form,show}.blade.php`.
- `resources/views/document-types/{index,create,edit,_form,show}.blade.php`.
- `resources/views/delegations/{index,create}.blade.php`.
- `resources/views/notifications/index.blade.php` y `partials/item.blade.php`.
- `resources/views/reports/documents.blade.php`, `audit/index.blade.php`.
- `resources/views/errors/{layout,403,404,419,429,500,503}.blade.php`.

Pruebas y documentación:

- `tests/Feature/InstitutionalExperienceTest.php` y `tests/JavaScript/institutional-ui.test.mjs` (nuevos).
- `tests/Feature/DashboardTest.php`: expectativa del vacío específico de Extra.
- `tests/Feature/DocumentHistoryTest.php`: regresión de sección abierta al paginar.
- Este informe, `docs/REDISENO_VISUAL.md` y enlace desde `README.md`.

## Validación

Las pruebas de experiencia recorren los seis roles, entradas de módulos, mensajes únicos, contratos de tabla, búsqueda progresiva, campos de contraseña y el recorrido real POST inválido → GET del formulario con errores. Las pruebas JavaScript verifican progreso, duplicados, cancelación, búsquedas, historial del navegador, contraseña y menú móvil. Se conservan las pruebas del visor privado y de destinatarios.

La suite general conserva el aislamiento SQLite `:memory:` de `phpunit.xml`; no es la conexión principal de la aplicación ni modifica `.env`. La integración usa el PostgreSQL real con los mecanismos reversibles de las pruebas existentes. No se ejecutan comandos de limpieza sobre la base institucional.

| Comprobación | Resultado |
| --- | --- |
| Suite funcional completa | 330 pruebas y 2.689 aserciones aprobadas |
| Experiencia institucional, repetición final con el caso adicional de filtros | 6 pruebas y 302 aserciones aprobadas |
| Integración PostgreSQL completa | 73 pruebas y 415 aserciones aprobadas |
| JavaScript: interfaz, visor y destinatarios | 15 pruebas aprobadas |
| Pint `--test --dirty` | Correcto |
| Caché Blade y sintaxis PHP de todas las vistas compiladas | Correctas |
| Vite `npm run build` | Correcto; bundle final `app-7ztFbbLo.css` y `app-YK8b3MY1.js` |
| `php artisan migrate:status` | 19 migraciones existentes en estado `Ran`; ninguna nueva |
| Listado de rutas | Correcto; no se editaron rutas |
| `git diff --check` | Sin observaciones |

Las pruebas específicas no deben sumarse a la suite general como si fueran casos completamente independientes: la repetición final incluye casos ya ejecutados y el caso adicional de filtros incorporado después de iniciar la suite completa.

## Alcance de la comprobación visual y aceptación pendiente

Se inspeccionaron inicio y detalle documental con una sesión real del Jefe demo de Aeronáutica, incluido escritorio a 1280 px. El listado móvil se inspeccionó a 614 px (`scrollWidth = clientWidth = 614`) y con viewport de 390 px (`scrollWidth = clientWidth = 375`, descontando la barra de desplazamiento). Se comprobaron filtros secundarios plegados, tarjetas documentales, foco visible y el menú: diálogo modal, foco en Cerrar, Escape, fondo liberado y devolución de foco a Abrir menú. No se ejecutaron acciones documentales ni cambios de cuentas desde el navegador.

La herramienta inicial del navegador presentó timeouts; se recuperó la lectura por la API DOM disponible y se pudo completar la comprobación de 390 px. El usuario preparó una segunda pestaña, pero la sesión visible en las dos pestañas inspeccionadas seguía siendo Jefe demo de Aeronáutica. La revisión visual completa de Administrador/Secretaria **no se considera realizada en esta iteración**. Las vistas de esos roles se cubren por renderizado automatizado, no por una aprobación visual humana.

El intento final de restablecer automáticamente el viewport agotó el tiempo disponible de la herramienta; su retorno al ancho habitual no pudo confirmarse. Esto no ejecutó acciones de negocio ni cambios de credenciales.

La matriz anterior describe mejoras implementadas, no una certificación de accesibilidad ni una aceptación institucional definitiva. Antes de considerar el producto listo para presentación debe completarse la UAT con personal administrativo: localizar un documento, preparar un borrador, reconocer principal/copia, consultar archivo/historial y comprender errores y siguiente acción. El despliegue seguro, restauración de backup y aceptación de procesos siguen sujetos a las guías ya existentes.
