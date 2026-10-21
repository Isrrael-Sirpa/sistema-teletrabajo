# Rediseño visual y experiencia de usuario

La revisión integral posterior del 19/10/2026 —consistencia de módulos, formularios, navegación, feedback y tablas móviles— está documentada por separado en [Producto institucional: experiencia y validación](PRODUCTO_INSTITUCIONAL_UX.md). Sus resultados y límites son los vigentes para esa iteración; los apartados siguientes conservan el registro histórico de intervenciones anteriores.

La mejora posterior de autoridad formal y visualización de adjuntos está documentada en [Autoridad formal y visor privado](AUTORIDAD_Y_VISOR_PRIVADO.md).

Esta intervención es exclusivamente de presentación, posterior a la Fase 24. Conserva Blade, Tailwind 4 y Vite. No introduce dependencias, rutas, permisos, migraciones ni datos institucionales. La autorización de cada acción continúa en las políticas y controladores existentes.

## Estructura y decisiones

- El layout autenticado reúne navegación lateral, encabezado, menú de perfil y contenido principal. Los enlaces se muestran con las capacidades actuales del usuario; ocultarlos no sustituye la autorización del servidor.
- `resources/css/app.css` centraliza la identidad de Facultad de Tecnología (`--ft-*`) y los acentos de carrera (`--career-*`). El atributo `data-career` utiliza la sigla `acronym` existente. Fuera de una carrera se aplica el tema institucional. No se agregan colores a PostgreSQL.
- Las nueve siglas contempladas son AERO, CC, EI, ET, EM, MA, MI, QI y GTG. Los colores claros de algunas paletas se reservan para acentos; `--career-action` utiliza una variante oscura para botones con texto blanco. El sidebar siempre mantiene el azul institucional.
- La navegación lateral es fija en escritorio y un cajón en tamaños menores. Su control gestiona `aria-expanded`, visibilidad, `inert`, foco y cierre con Escape. Los formularios mantienen sus nombres, acciones y validación existentes.
- Los componentes Blade nuevos son iconos SVG locales, enlace de navegación, badge de estado y dos indicadores visuales de pasos. Reutilizan el layout y las vistas existentes, sin biblioteca de iconos adicional.

## Mejoras por pantalla

- Login institucional sin recursos externos; etiquetas visibles, campos cómodos y adaptación móvil.
- Panel con “Necesitan su atención” antes del resumen; accesos rápidos sujetos al rol. El perfil Extra prioriza documentos autorizados y no presenta gestión o reportes.
- Listado documental con código, asunto, tipo, origen, destinatarios, estado, prioridad y fechas; la acción por fila es “Ver”. El borrador representa sus etapas existentes sin crear un wizard de servidor. Tras el envío, el detalle prioriza seguimiento y acciones propias del destinatario.
- Notificaciones con distinción visual entre nuevas y leídas; usuarios, carreras, reportes, auditoría y delegaciones usan jerarquía, estados y vacíos más legibles.
- Las siglas de carrera permanecen editables. La personalización visual no reescribe códigos de documentos históricos.

## Verificación y límites

La comprobación de tema automatizada renderiza las nueve carreras y los perfiles UAT de Jefe y Secretaria sobre PostgreSQL sin cambiar sus cuentas. Las pruebas funcionales preservan las decisiones de autorización. La inspección manual cubre 1440, 1280, 768 y 390 px, además del cajón móvil y la navegación principal del Administrador. En la primera revisión no había documentos reales; la inspección posterior del documento enviado y de las sesiones UAT se detalla más abajo.

No se ejecutó una carga institucional manual o permanente ni se crearon usuarios demo durante esta intervención. Las pruebas pueden preparar datos dentro de sus entornos aislados o transacciones reversibles.

| Comprobación | Resultado |
| --- | --- |
| Suite general | 299 pruebas, 1.985 verificaciones; aprobada |
| Integración PostgreSQL | 71 pruebas, 400 verificaciones; aprobada |
| Autenticación y autorización específicas | 20 pruebas, 90 verificaciones; aprobadas |
| Pint | Sin observaciones |
| Compilación Blade | Correcta |
| Compilación Vite y sintaxis JavaScript | Correctas |
| Estado de migraciones | Todas las migraciones existentes aplicadas; ninguna nueva |
| Rutas | 73 registradas; enlaces principales verificados |

La prueba de diseño en el navegador recorrió las diez rutas principales del Administrador en los cuatro anchos solicitados, sin desbordamiento horizontal del documento. Las tablas anchas mantienen desplazamiento en su propio contenedor. La prueba PostgreSQL verificó las nueve siglas existentes y el renderizado de los 18 usuarios UAT, además de la cuenta Administrador original.

## Archivos de esta intervención

- Estilos e interacción: `resources/css/app.css`, `resources/js/app.js`.
- Vistas principales: `resources/views/auth/login.blade.php`, `resources/views/components/layouts/authenticated.blade.php`, `resources/views/dashboard.blade.php`.
- Componentes nuevos: `resources/views/components/ui/icon.blade.php`, `nav-link.blade.php`, `status-badge.blade.php`, `document-stepper.blade.php`, `draft-steps.blade.php` (todos en `resources/views/components/ui/`).
- Documentos: `resources/views/documents/index.blade.php`, `show.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`.
- Módulos asociados: `resources/views/notifications/index.blade.php`, `notifications/partials/item.blade.php`, `users/index.blade.php`, `users/show.blade.php`, `users/_form.blade.php`, `users/password.blade.php`, `organizational-units/index.blade.php`, `organizational-units/show.blade.php`, `organizational-units/edit.blade.php`, `organizational-units/_form.blade.php`, `document-types/index.blade.php`, `document-types/show.blade.php`, `document-types/_form.blade.php`, `reports/documents.blade.php`, `audit/index.blade.php` y `delegations/index.blade.php` (bajo `resources/views/`).
- Pruebas nuevas: `tests/Feature/FrontendThemeTest.php`, `tests/PostgreSQL/PostgreSqlFrontendThemeTest.php`.
- Documentación: este archivo y un enlace desde `README.md`.

Las modificaciones ya presentes al iniciar esta intervención en el seeder institucional, el comando UAT, la configuración de carreras y sus pruebas pertenecen al trabajo anterior; no se ampliaron ni ejecutaron como parte del rediseño.

## Ajuste prioritario: seguimiento posterior al envío

El detalle de un documento enviado ya no presenta el stepper ni la tabla de configuración del borrador. En su lugar muestra una tarjeta con fecha de envío, número de destinos, estado en lenguaje sencillo y una indicación explícita de si el remitente necesita actuar. Con varios destinos aparece un resumen de aprobaciones, revisiones y recepciones pendientes; cada destino tiene una tarjeta compacta con su estado y última actualización.

Para el destinatario se conserva únicamente la acción permitida por la Policy en su tarjeta (recibir, iniciar revisión o aprobar). Devolver y rechazar permanecen disponibles cuando corresponde, dentro de “Otras decisiones”. Una devolución resalta la necesidad de corrección y enlaza a las observaciones. Los datos extensos, archivos, accesos e historial permanecen disponibles en secciones desplegables, sin ocupar la vista inicial. Los enlaces abren la sección correspondiente antes de desplazarse a ella.

El ajuste modifica solo `resources/views/documents/show.blade.php`, el nuevo parcial `resources/views/documents/partials/recipient-tracking-card.blade.php`, la interacción de anclas en `resources/js/app.js`, las pruebas de vista en `tests/Feature/DocumentWorkflowTest.php` y esta nota. No modifica estados, servicios, Policies, rutas ni esquema.

Verificación inicial del ajuste: suite general 303 pruebas y 2.032 verificaciones aprobadas; integración PostgreSQL 71 pruebas y 400 verificaciones aprobadas; Pint, compilación Blade, Vite y sintaxis JavaScript correctos. La revisión posterior con un documento real y la ampliación multicarrera se documentan a continuación.

## Revisión real y selección multicarrera

Se inspeccionó en PostgreSQL y en el navegador el documento enviado `FTEC-MA-2026-000001` (`/documentos/01a146b4-9ac1-7356-bd49-063635aca7b2`). Su origen y remitente son Mecánica Automotriz y su Jefe demo; su estado general es `sent`. Tiene dos destinatarios en Aeronáutica, ambos todavía `sent`: Jefe demo como destinatario personal principal, con respuesta requerida, y la carrera como copia informativa, sin respuesta requerida. La vista del remitente, la del Jefe destinatario y la de la Secretaria destinataria se comprobaron en sesiones proporcionadas por el usuario, sin recibir contraseñas ni cambiar el documento. El navegador cargó el servidor Vite actual (`@vite/client`, `resources/css/app.css` y `resources/js/app.js`), no un build anterior.

Antes del ajuste adicional, el remitente ya veía la tarjeta de seguimiento y no el stepper antiguo, pero ambas filas aparecían como dos destinatarios indistintos. El Jefe de Aeronáutica veía dos acciones idénticas de recepción. La Secretaria solo tenía acción sobre la copia. Después del ajuste, la vista separa explícitamente destinatarios principales y copias informativas; el remitente recibe un resumen de seguimiento sin acciones de recepción o decisión. El destinatario principal ve una acción destacada para su próximo paso. La copia tiene su propia tarjeta y ofrece, cuando corresponde, solo registrar su recepción, sin aprobar ni revisar. El stepper se mantiene únicamente durante el borrador.

El backend ya permitía varias filas `document_recipients` independientes para un documento: el endpoint existente agrega una unidad o persona por llamada, el servicio conserva transacción, autorización, control optimista mediante `lock_version`, restauración controlada y prevención de duplicados. No se modificaron Request, Controller, Service, Policy, rutas, esquema ni el algoritmo de estado agregado. La nueva interfaz del borrador permite buscar por nombre o sigla y marcar una o varias carreras institucionales con casillas; el contador indica cuántas se seleccionaron. Las nueve carreras disponibles pueden elegirse para el mismo documento, sin seleccionar por separado Jefe, Secretaria o Auxiliar. Al guardar, la interfaz llama secuencialmente al endpoint existente para cada carrera y actualiza `lock_version` entre llamadas. La selección completa no es una única transacción: si alguna llamada falla, las carreras ya añadidas permanecen en el borrador. La corrección posterior descrita abajo actualiza inmediatamente el listado, incluso ante fallos parciales, sin recargar la página. El servidor sigue rechazando destinatarios equivalentes activos; la interfaz también deshabilita los ya agregados.

La copia informativa es una sección secundaria y opcional. Permite agregar una unidad o persona mediante el flujo existente, sin exponer `requires_response` al usuario. El resumen antes de enviar separa principales, copias y archivos. La distinción principal/copia no altera la visibilidad institucional ni crea tres destinatarios personales para una carrera.

Se examinó además un borrador real existente en la sesión del Jefe de Mecánica Automotriz. Se seleccionaron temporalmente tres carreras y se probó la búsqueda en el navegador; no se envió el formulario ni se persistió ningún cambio. El documento enviado tampoco fue alterado.

### Verificación de este ajuste

| Comprobación | Resultado |
| --- | --- |
| Suite general | 305 pruebas, 2.059 verificaciones; aprobada |
| Integración PostgreSQL | 71 pruebas, 400 verificaciones; aprobada |
| Pruebas focalizadas de flujo y destinatarios | 41 pruebas, 322 verificaciones; aprobadas tras el ajuste visual final |
| Autorización específica | 11 pruebas, 41 verificaciones; aprobadas |
| Pint y compilación Blade | Sin observaciones |
| Sintaxis JavaScript y compilación Vite | Correctas |
| Rutas documentales | 23 existentes; sin cambios |

Cuatro pruebas PostgreSQL se ajustaron para no asumir una base vacía ahora que existe el documento real: comparan la variación de sus propios datos de prueba o verifican el índice sin exigir un planificador concreto. No alteran lógica de aplicación ni datos reales.

Archivos de este ajuste: `resources/views/documents/show.blade.php`, `resources/views/documents/partials/recipient-tracking-card.blade.php`, `resources/js/app.js`, `resources/js/multi-career-recipients.js`, `tests/Feature/DocumentWorkflowTest.php`, `tests/Feature/DocumentRecipientManagementTest.php`, `tests/PostgreSQL/PostgreSqlDashboardTest.php`, `tests/PostgreSQL/PostgreSqlDocumentFileTest.php`, `tests/PostgreSQL/PostgreSqlDocumentRecipientTest.php`, `tests/PostgreSQL/PostgreSqlDocumentReportTest.php` y esta documentación.

## Corrección de actualización inmediata de destinatarios — 19/10/2026

Causa: el JavaScript consumía el HTML del redirect solamente para leer `lock_version` y terminaba con una navegación a la misma URL más `#recipients-title`. Al ser un cambio de fragmento, el navegador no sustituía el DOM por el HTML recibido. El servidor había guardado el destinatario, pero el listado visible continuaba obsoleto. La integración de `app.js` ya importaba correctamente el módulo.

Se reutiliza la respuesta HTML existente, sin cambios de Controller, Request ni endpoints. Blade identifica fragmentos de listado, contador, opciones de carreras/copias y resumen/controles de envío. Tras cada respuesta válida del mismo borrador, JavaScript los sincroniza y actualiza todos los campos de versión documental de la página. Las filas nuevas usan delegación de eventos para que Quitar y Actualizar sigan funcionando después de ser insertadas. Las copias mantienen su grupo separado.

Las carreras añadidas quedan marcadas y deshabilitadas con su explicación; las retiradas vuelven a estar disponibles. El contador de selección excluye las ya incorporadas y vuelve a cero después del éxito completo. Durante el proceso aparece Agregando..., se bloquean los controles relacionados y se evitan solicitudes duplicadas. Al terminar se muestra un mensaje breve y se acerca el feedback al área visible.

En un fallo parcial se conservan y muestran inmediatamente los éxitos previos, se identifica la carrera rechazada y las pendientes siguen seleccionables. Un GET autenticado en segundo plano reconcilia el estado si la operación falla; no se recarga ni navega toda la página. Si la conexión tampoco permite esa consulta, se conserva lo confirmado y se ofrece Reintentar actualización, bloqueando nuevas mutaciones hasta recuperar una versión fiable.

Verificación mínima: cuatro pruebas aisladas de JavaScript cubren una/dos/tres carreras, fallo de la tercera después de dos éxitos, retiro de filas nuevas, copias, contadores, versiones y doble clic; 18 pruebas de destinatarios (129 verificaciones) pasan, incluida la respuesta HTML de alta/retiro. Sintaxis JS, Pint del test modificado, compilación Blade, Vite y revisión de whitespace: correctos. No se ejecutó otra ronda de QA general ni se modificaron datos institucionales.

Archivos de esta corrección: `resources/js/multi-career-recipients.js`, `resources/views/documents/show.blade.php`, `tests/JavaScript/multi-career-recipients.test.mjs`, `tests/Feature/DocumentRecipientManagementTest.php` y este documento.
