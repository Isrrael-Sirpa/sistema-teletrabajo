# Rediseño visual y experiencia de usuario

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
- Listado documental con código, asunto, tipo, origen, destinatarios, estado, prioridad y fechas; la acción por fila es “Ver”. El detalle agrupa información y acciones, y representa el flujo usando únicamente el estado actual. El formulario de borrador indica las etapas existentes sin crear un wizard de servidor.
- Notificaciones con distinción visual entre nuevas y leídas; usuarios, carreras, reportes, auditoría y delegaciones usan jerarquía, estados y vacíos más legibles.
- Las siglas de carrera permanecen editables. La personalización visual no reescribe códigos de documentos históricos.

## Verificación y límites

La comprobación de tema automatizada renderiza las nueve carreras y los perfiles UAT de Jefe y Secretaria sobre PostgreSQL sin cambiar sus cuentas. Las pruebas funcionales preservan las decisiones de autorización. La inspección manual cubre 1440, 1280, 768 y 390 px, además del cajón móvil y la navegación principal del Administrador. El detalle de un documento real no pudo abrirse en el entorno local porque no hay documentos existentes; se cubre mediante pruebas con datos temporales. La revisión visual interactiva de las sesiones UAT de Jefe y Secretaria requiere iniciar sesión sin compartir sus contraseñas.

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
