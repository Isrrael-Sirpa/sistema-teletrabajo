# Contexto para continuar el proyecto

Actualizado el 11 de noviembre de 2026. Este archivo resume el sistema existente y los cambios recientes para que otra conversación pueda retomar el trabajo sin depender del historial del chat.

## Instrucción para la siguiente conversación

Lee este documento y revisa el árbol de trabajo actual antes de editar. Los cambios recientes están en el working tree y todavía no se han confirmado en un commit. Conserva las reglas de autorización y los registros existentes al hacer ajustes. El usuario quiere probar las pantallas y luego enviar correcciones graduales.

## Proyecto

Sistema web de gestión documental y teletrabajo administrativo para la Facultad de Tecnología de la UMSA. Usa Laravel 13, PHP 8.3+, PostgreSQL 18, Blade, Tailwind CSS 4, JavaScript, Vite y `spatie/laravel-permission`.

La aplicación ya incluye autenticación, roles y permisos por unidad, gestión de usuarios y carreras, Kardex, borradores, destinatarios múltiples, archivos privados, correlativos, seguimiento documental, versiones y correcciones, notificaciones, vencimientos, auditoría, delegaciones, búsqueda, reportes y exportaciones. La cuenta inicial de Administrador se crea por separado mediante el comando institucional. El seeder inicial ya carga una Facultad, nueve carreras y Kardex.

### Perfiles existentes

El sistema tiene Administrador y Co-administrador globales; Jefe de Carrera y Encargado de Kardex como responsables de unidad; Secretaría y Auxiliar de Secretaría como personal que puede recibir tareas internas; y Extra, que requiere acceso explícito a documentos. El panel y las opciones deben respetar el rol y la unidad.

## Objetivo de producto y criterios de interfaz del usuario

El usuario quiere que todo el sistema sea profesional, claro e intuitivo para personas con poca experiencia con tecnologías. Pide reducir las opciones que no corresponden al trabajo de cada persona, usar palabras familiares, mostrar una acción principal clara por pendiente y facilitar el aprendizaje sin capacitaciones largas. Quiere probar las pantallas con sus cuentas reales y entregar correcciones gradualmente, de modo que la implementación debe conservar ese ciclo de revisión.

El envío oficial de documentos se realiza entre carreras y unidades. Un remitente puede seleccionar varias carreras o unidades para el mismo documento. La gestión posterior pertenece a cada destino y el acceso y la decisión se limitan al destino correspondiente. La entrega individual de tareas dentro de la unidad es un paso interno, no el envío oficial entre personas.

En una revisión futura de cualquier pantalla, revisar la experiencia según sus usuarios reales: responsable de carrera, Encargado de Kardex, Secretaría, Auxiliar, Administrador y remitente. El inicio de Jefatura ya se simplificó, pero la prueba visual y las correcciones del usuario quedan pendientes.

## Decisión pendiente sobre la carga inicial

El usuario decidió dejar para más adelante cualquier modificación de la carga inicial. Se mantiene el catálogo actual de Facultad, nueve carreras y Kardex. Este trabajo no crea usuarios reales ni asigna por defecto a una persona como Encargado de Kardex. El Administrador debe asignar el rol Encargado de Kardex a una persona desde **Usuarios**.

## Cambios recientes de flujo documental

El envío oficial puede tener varios destinatarios de unidad. Cada unidad recibe su propio trámite, conserva el estado correspondiente a ese destino y gestiona las revisiones de manera independiente.

Se añadió el rol **Encargado de Kardex** y la unidad Kardex puede tener su responsable y equipo. La decisión oficial del destino corresponde a su Jefe de Carrera o Encargado de Kardex. Secretaría y Auxiliar pueden recibir revisiones internas y devolver una recomendación de aprobar o rechazar con un mensaje. Esa recomendación no es la decisión oficial.

Las tareas quedan asociadas al destinatario y a la versión del documento que recibió esa unidad. La pertenencia a una tarea mantiene acceso a los documentos y adjuntos autorizados dentro de ese ámbito. Las correcciones y los destinos múltiples mantienen su historial por unidad.

Componentes principales:

- `app/Services/DocumentReviewService.php` gestiona la asignación, entrega y consulta de tareas.
- `app/Models/DocumentReviewTask.php` representa una revisión individual asignada a una persona.
- `app/Http/Controllers/DocumentReviewController.php` sirve las bandejas de tareas y las acciones de revisión.
- `app/Enums/ReviewRecommendation.php` contiene las recomendaciones internas.
- `database/migrations/2026_11_09_000100_create_document_review_tasks_table.php` crea las tareas, incorpora el rol de Kardex al RBAC y actualiza los permisos de decisión de Secretaría.
- `routes/web.php` registra las bandejas `/mis-tareas` y `/revisiones-de-mi-unidad` y las acciones para asignar y entregar revisiones.
- `resources/views/documents/reviews/` contiene la sección de revisión y el tablero del equipo.

El cambio conserva las decisiones oficiales y la historia del destinatario. La entrega de una tarea es un registro interno. Al tomar la decisión oficial, las tareas pendientes del destino se cierran y las revisiones ya entregadas se conservan.

## Cambios recientes en el inicio de responsables

El panel de **Jefe de Carrera** y **Encargado de Kardex** ahora reúne dos secciones, limitadas a la unidad de quien inició sesión.

**Documentos pendientes** presenta hasta cinco documentos priorizados según vencimiento y estado del destinatario. Cada fila muestra el asunto, la unidad remitente y el siguiente paso, como recibir, asignar revisión o consultar revisiones. La cantidad de adjuntos se calcula para la versión recibida por ese destino y solo cuando el permiso permite descargarlos.

**Tareas del equipo** muestra los totales pendientes y entregados, un resumen de hasta cuatro personas y las revisiones recientes. Incluye un acceso al tablero completo. La navegación de Secretaría y Auxiliar sigue ofreciendo **Mis tareas**.

Componentes principales:

- `app/Services/DashboardService.php` prepara los pendientes por destinatario y el resumen del equipo.
- `app/Services/DocumentWorkQueryService.php` aplica el ámbito autorizado a las consultas del panel.
- `resources/views/dashboard/unit.blade.php` muestra el inicio de responsables.
- `resources/views/dashboard.blade.php` distribuye el panel según el rol.
- `resources/views/components/layouts/authenticated.blade.php` muestra los accesos de navegación.
- `tests/Feature/DashboardTest.php` cubre permisos, estados, prioridades, conteos y límites del panel.

## Base de desarrollo

La migración `2026_11_09_000100_create_document_review_tasks_table.php` ya se aplicó a la base local de desarrollo `sistema_teletrabajo`. Se comprobó la tabla de tareas, el rol y sus permisos. La verificación reportó que todas las migraciones estaban aplicadas.

Todavía falta asignar el rol Encargado de Kardex a la persona designada. No se han creado cuentas para completar ese paso.

## Verificación más reciente reportada

- 352 pruebas PHP pasaron con SQLite.
- 16 pruebas PostgreSQL focalizadas pasaron con una instancia aislada, incluidos los escenarios del panel y las revisiones.
- 15 pruebas JavaScript pasaron.
- La compilación de producción de Vite y Pint pasaron.
- El servidor local respondió con HTTP 200 en el inicio, la ruta de salud y los recursos compilados.
- URL local informada al usuario: `http://127.0.0.1:8000/`.
- La revisión visual del usuario con sus propias cuentas queda pendiente.

Antecedente de PostgreSQL: una ejecución anterior de la suite completa tuvo un único fallo de plan de consulta en `PostgreSqlDocumentSearchTest`. En una base nueva, el optimizador eligió otro índice de los vencimientos. La búsqueda devolvió los resultados correctos y en la base de desarrollo se observó el índice esperado. Los 16 resultados PostgreSQL mencionados arriba corresponden a pruebas focalizadas, no a una nueva ejecución de toda la suite de integración.

## Estado del árbol de trabajo

La rama es `main`, basada en el commit `666b7d4` (`21/10/2026 mejoras-falta dejarlo para produccion`). Los cambios recientes siguen sin commit. El inventario siguiente corresponde al estado del working tree al crear este documento; `git status --short` da el estado vigente.

### Archivos modificados

- **Enumeraciones, permisos y modelos:** `app/Enums/AuditAction.php`, `app/Enums/OrganizationalUnitType.php`, `app/Enums/RoleName.php`, `app/Models/Document.php`, `app/Models/DocumentRecipient.php`, `app/Models/User.php`, `app/Support/CareerHeadDelegationCatalog.php`, `app/Support/RbacCatalog.php`.
- **Controladores, solicitudes y políticas:** `app/Http/Controllers/DocumentController.php`, `app/Http/Controllers/DocumentFileController.php`, `app/Http/Controllers/UserController.php`, `app/Http/Requests/SearchDocumentsRequest.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, `app/Policies/DocumentPolicy.php`, `app/Policies/UserPolicy.php`.
- **Servicios:** `app/Services/DashboardService.php`, `app/Services/DocumentDeadlineRecipientResolver.php`, `app/Services/DocumentDeadlineService.php`, `app/Services/DocumentDraftService.php`, `app/Services/DocumentNotificationRecipientResolver.php`, `app/Services/DocumentRecipientEligibility.php`, `app/Services/DocumentReportService.php`, `app/Services/DocumentSearchService.php`, `app/Services/DocumentWorkQueryService.php`, `app/Services/DocumentWorkflowService.php`, `app/Services/UserManagementService.php`.
- **Interfaz y rutas:** `resources/js/multi-career-recipients.js`, `resources/views/components/layouts/authenticated.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/delegations/create.blade.php`, `resources/views/documents/partials/recipient-tracking-card.blade.php`, `resources/views/documents/show.blade.php`, `resources/views/users/_form.blade.php`, `resources/views/users/index.blade.php`, `routes/web.php`.
- **Pruebas modificadas:** `tests/Feature/AuthorizationTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/DocumentAnnulmentTest.php`, `tests/Feature/DocumentDeadlineTest.php`, `tests/Feature/DocumentFilePreviewTest.php`, `tests/Feature/DocumentHistoryTest.php`, `tests/Feature/DocumentNotificationTest.php`, `tests/Feature/DocumentRecipientManagementTest.php`, `tests/Feature/DocumentReportTest.php`, `tests/Feature/DocumentSearchTest.php`, `tests/Feature/DocumentVersioningTest.php`, `tests/Feature/DocumentWorkflowTest.php`, `tests/Feature/InstitutionalExperienceTest.php`, `tests/Feature/ProjectAcceptanceTest.php`, `tests/Feature/TemporaryDelegationTest.php`, `tests/Feature/UserManagementTest.php`, `tests/JavaScript/multi-career-recipients.test.mjs`, `tests/PostgreSQL/PostgreSqlDocumentNotificationTest.php`, `tests/PostgreSQL/PostgreSqlDocumentRecipientTest.php`, `tests/PostgreSQL/PostgreSqlDocumentWorkflowTest.php`.

### Archivos nuevos

- `app/Enums/ReviewRecommendation.php`
- `app/Http/Controllers/DocumentReviewController.php`
- `app/Models/DocumentReviewTask.php`
- `app/Services/DocumentReviewService.php`
- `database/migrations/2026_11_09_000100_create_document_review_tasks_table.php`
- `resources/views/dashboard/unit.blade.php`
- `resources/views/documents/reviews/_section.blade.php`
- `resources/views/documents/reviews/index.blade.php`
- `tests/Feature/DocumentInternalReviewTest.php`

## Maqueta HTML para revisión visual

El 12 de noviembre de 2026 se creó `public/maqueta-panel.html`, una maqueta independiente y adaptable del inicio de Jefatura. Presenta el resumen de pendientes, la lista con filtros, el siguiente paso por documento y la carga/actividad del equipo. Incluye interacciones de demostración para filtros, menú móvil y detalle del documento. Usa información ficticia y no se conecta a Laravel ni a la base de datos; no modifica el flujo de producción. Si el servidor local está levantado, se puede abrir en `/maqueta-panel.html`; también puede abrirse como archivo HTML.

## Continuación acordada

1. El usuario puede probar `/panel` como Jefe de Carrera o Encargado de Kardex y señalar qué etiquetas, prioridades, pasos o datos le resultan confusos.
2. Ajustar el panel con esos comentarios y comprobar de nuevo que los documentos y las tareas sigan limitados a la unidad, los conteos coincidan con sus bandejas y los enlaces lleven a la acción correcta.
3. Extender las mejoras de claridad a los demás perfiles cuando la revisión del usuario indique qué pantalla requiere atención. Mantener la interfaz simple y enfocada en las tareas de ese perfil.
4. El usuario dejó explícitamente la carga inicial de carreras y unidades para más adelante. La instalación sigue cargando una Facultad, nueve carreras y Kardex. Las cuentas y la persona con rol Encargado de Kardex se configuran por separado desde el Administrador.
