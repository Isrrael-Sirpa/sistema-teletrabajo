# Pruebas de aceptación

## Propósito y alcance

Esta matriz registra la aceptación técnica automatizada de la Fase 24 y queda preparada para una sesión posterior con personal institucional. La ejecución usa SQLite en memoria para la regresión rápida y una suite independiente sobre PostgreSQL 18.3 para integridad, tipos, índices y concurrencia real.

Los usuarios, carreras y documentos de aceptación son datos ficticios y efímeros creados por factories dentro de cada prueba. No existen contraseñas publicadas ni usuarios de aceptación en `DatabaseSeeder`. Los seeders productivos contienen únicamente autorización, estructura institucional y tipos documentales idempotentes.

Estados usados:

- **Aprobado automático:** comportamiento verificado por la suite.
- **Pendiente UAT:** requiere confirmación de usabilidad por personal real en preproducción, sin cambiar la conclusión técnica.
- **Pendiente de infraestructura:** solo puede comprobarse en el servidor productivo definitivo.

## Matriz

| Caso | Rol | Precondición | Acción | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|---|
| Catálogo de roles | Todos | RBAC sembrado | Consultar los seis roles y sus permisos | Existen Administrador, Co-administrador, Jefe, Secretaria, Auxiliar y Extra con separación efectiva | `AuthorizationTest` valida catálogo, asignación y negativas | Aprobado automático; pendiente UAT |
| Datos controlados | Administrador | Base de prueba vacía | Ejecutar seeders productivos y crear fixtures mediante factories | Facultad, Kardex, carreras ficticias, tipos y usuarios efímeros; ningún usuario productivo sembrado | `ProjectAcceptanceTest` confirma que `DatabaseSeeder` deja `users` vacío | Aprobado automático |
| Flujo principal completo | Secretaria / destino | Dos carreras ficticias y tipo Informe | Crear borrador, agregar destino, cargar PDF, enviar, recibir, revisar, devolver, preparar versión 2, reenviar, recibir, revisar y aprobar | Código y fecha original intactos; estado aprobado; dos versiones; archivos, eventos, notificaciones y auditoría conservados | Escenario integral completado con todas las comprobaciones | Aprobado automático; pendiente UAT |
| Multidestino A/B/C | Secretarias destino | Tres destinos formales | A aprueba, B devuelve, C aprueba; corregir solo B y aprobar | A y C permanecen aprobados; solo B se reabre; estado final aprobado; eventos anteriores intactos | Escenario integral completado; 3 aprobaciones, 1 devolución y versiones 1/2 | Aprobado automático |
| Copia informativa | Secretaria / Auxiliar | Destinatario con `requires_response=false` | Recibir o consultar e intentar revisar/decidir | No puede aprobar, devolver o rechazar y no bloquea el agregado | `DocumentWorkflowTest` valida copia de unidad y personal | Aprobado automático; pendiente UAT |
| Rechazo | Secretaria / Jefe | Destinatario formal recibido | Intentar rechazar sin motivo y luego con motivo | Motivo obligatorio, estados `rejected`, historial/notificación correctos y sin corrección ordinaria | `DocumentWorkflowTest`, `DocumentHistoryTest`, `DocumentNotificationTest` y `DocumentVersioningTest` aprobados | Aprobado automático |
| Anulación | Administrador / Co-administrador | Documento oficial en cualquier estado permitido | Anular con motivo e intentar acciones posteriores | Código, versiones, archivos y destinatarios intactos; historial/auditoría/notificaciones; flujo bloqueado | `DocumentAnnulmentTest` y concurrencia PostgreSQL aprobados | Aprobado automático; pendiente UAT |
| Restricted invisible | Usuario sin relación | Documento `restricted` ajeno | Abrir, buscar, consultar dashboard y reportes | No revela existencia, asunto, conteos ni contenido | Pruebas de draft, búsqueda, dashboard, reportes y anulación aprobadas | Aprobado automático |
| Restricted y notificaciones | Participante autorizado / no autorizado | Documento `restricted` enviado | Consultar centro de notificaciones y recordatorios | Mensaje genérico sin asunto/contenido y sin otorgar acceso | `DocumentNotificationTest` y `DocumentDeadlineTest` aprobados | Aprobado automático |
| Grant `view` | Extra | Concesión vigente de consulta | Abrir detalle e intentar descarga | Puede ver el detalle, no descargar | `DocumentAccessGrantTest` aprobado | Aprobado automático; pendiente UAT |
| Grant `download` | Extra | Concesión vigente de descarga | Abrir y descargar | Puede consultar y descargar el archivo privado | `DocumentAccessGrantTest` aprobado | Aprobado automático; pendiente UAT |
| Grant ausente, vencido o revocado | Extra | Sin concesión efectiva | Buscar, abrir y descargar | Pierde toda visibilidad y acceso | Pruebas de grants, búsqueda y dashboard aprobadas | Aprobado automático |
| Límites del rol Extra | Extra | Cuenta activa | Intentar recibir, decidir, administrar o abrir reportes | Todas las operaciones quedan denegadas | Pruebas de autorización, workflow, grants y reportes aprobadas | Aprobado automático |
| Delegación futura/activa/expirada/revocada | Jefe / Secretaria | Intervalos y capacidades controlados | Consultar antes, durante y después de vigencia; revocar | Solo aplica en intervalo semiabierto, cesa inmediatamente y se audita | `TemporaryDelegationTest` aprobado | Aprobado automático; pendiente UAT |
| Ámbito de delegación | Secretaria delegada | Delegación de una carrera | Actuar en misma/otra carrera y revisar rol/Spatie | Solo capacidades seleccionadas y misma carrera; no cambia rol ni permisos permanentes | Suite funcional y PostgreSQL de delegaciones aprobada | Aprobado automático |
| Gestión de usuarios | Administrador / Jefe | Usuarios ficticios por rol | Crear, editar, activar, desactivar y restablecer contraseña | Alcance y validaciones correctos; contraseña separada y hasheada | `UserManagementTest` y auditoría aprobados | Aprobado automático; pendiente UAT |
| Último Administrador | Administrador | Uno o dos administradores activos | Desactivar concurrentemente | Nunca quedan cero administradores activos | Regla funcional y mutex PostgreSQL aprobados | Aprobado automático |
| Jefe titular único | Administrador | Carrera activa | Asignar dos jefes titulares concurrentemente | Solo uno queda activo por carrera | Pruebas funcionales y PostgreSQL aprobadas | Aprobado automático |
| Restricción de Co-administrador | Co-administrador | Existe Administrador | Editar o desactivar Administrador | Operación denegada | `UserManagementTest` aprobado | Aprobado automático |
| Fechas límite | Todos con visibilidad | Documentos sin fecha, vigentes, próximos, vencidos y terminales | Consultar filtros y ejecutar recordatorios dos veces | Clasificación correcta; `returned` pendiente; aprobados/rechazados/anulados sin recordatorio; sin duplicados | `DocumentDeadlineTest` y prueba concurrente PostgreSQL aprobados | Aprobado automático; pendiente UAT |
| Búsqueda combinada | Roles con documentos visibles | Datos con códigos, asuntos, estados y fechas variados | Filtrar por código, texto, estado, prioridad, tipo, origen, destino, fechas, vencimiento y corrección | Semántica AND, paginación estable y solo resultados autorizados | `DocumentSearchTest` y PostgreSQL aprobados | Aprobado automático; pendiente UAT |
| Reportes por ámbito | Todos | Documentos globales y de carrera | Consultar reportes | Admin/Co-admin global; Jefe/Secretaria/Auxiliar limitados; Extra recibe 403 | `DocumentReportTest` aprobado | Aprobado automático; pendiente UAT |
| CSV seguro | Roles autorizados | Reporte filtrado | Exportar CSV con valores que parecen fórmulas y superar límite | Misma autorización/filtros; neutraliza fórmulas; impone límite; audita exportación | `DocumentReportTest` aprobado | Aprobado automático |
| Dashboard por rol | Todos | Trabajo, grants, delegaciones y vencimientos variados | Abrir panel y seguir enlaces | Conteos, enlaces, prioridades, vacíos y ámbitos coinciden con consultas autorizadas | `DashboardTest` y PostgreSQL aprobados | Aprobado automático; pendiente UAT |
| Formatos adjuntos | Secretaria | Borrador propio | Cargar PDF, DOCX, XLSX, PPTX, JPG, PNG y ZIP reales | Se acepta estructura/MIME coherente y se almacena privadamente | 7 datasets de `ProjectAcceptanceTest` aprobados | Aprobado automático |
| Seguridad de archivos | Secretaria / autorizado / ajeno | Borrador u oficial con archivos | Carga múltiple, exceso, MIME falso, descarga e historial | Límite aplicado, falsos rechazados sin huérfanos, descarga autorizada, versiones históricas inmutables | `DocumentFileManagementTest` y `DocumentVersioningTest` aprobados | Aprobado automático |
| Concurrencia documental | Actores autorizados | Solicitudes paralelas reales en PostgreSQL | Enviar, decidir, corregir, conceder, recordar, delegar o anular simultáneamente | Un único resultado válido sin pérdida de integridad | Suites PostgreSQL cubren correlativos, decisiones, versiones, grants, recordatorios, delegaciones y anulación | Aprobado automático |
| Auditoría transversal | Administrador / actores | Acciones funcionales ejecutadas | Consultar login/logout, usuarios, carreras, tipos, envío, descarga, grants, delegaciones, anulación y exportación | Registros inmutables, con actor/resultado/recurso y transacción correcta | `AuditLogTest` y pruebas específicas de cada módulo aprobadas | Aprobado automático; pendiente UAT |
| Sanitización de auditoría | Administrador | Datos con campos sensibles | Registrar y consultar auditoría | Sin passwords, hashes, tokens, secretos, rutas privadas, binarios o contenido restricted | Sanitizador central y pruebas negativas aprobados | Aprobado automático |
| Errores seguros | Visitante / usuario | `APP_DEBUG=false` | Provocar 403, 404, 419, 429, 500 y 503 | Página institucional, headers seguros y ninguna traza o secreto | `ProductionSecurityTest` aprobado | Aprobado automático |
| Producción simulada | Operador | Variables `APP_ENV=production`, `APP_DEBUG=false` | Cachear configuración/rutas/vistas, compilar Vite, consultar `/up`, headers, Scheduler y cookies | Todos los comandos concluyen y la configuración permanece segura | Validado en Fases 23/24; cachés limpiadas tras la prueba local | Aprobado automático |
| PostgreSQL oficial | Operador | PostgreSQL 18.3 y base `sistema_teletrabajo` | Ejecutar integración y `migrate:status` | Tipos, FKs, índices, restricciones y concurrencia correctos; 19 migraciones `Ran` | Suite PostgreSQL completa aprobada | Aprobado automático |
| PHP 8.5 | Operador | Runtime 8.5 no instalado | Revisar restricciones sin instalar | Dependencias compatibles; no afirmar ejecución en 8.5 | `composer prohibits php 8.5 --locked` sin bloqueos; runtime pendiente | Pendiente de infraestructura |
| UAT institucional | Personal real | Preproducción aislada con cuentas temporales entregadas por canal seguro | Ejecutar esta matriz con teclado, móvil/escritorio y casos institucionales | Conformidad funcional y de facilidad de uso firmada por responsables | No corresponde automatizar ni usar credenciales reales en desarrollo | Pendiente UAT |

## Evidencia automatizada principal

- `tests/Feature/ProjectAcceptanceTest.php`: flujo completo, multidestino, archivos reales y aislamiento de seeders.
- `tests/Feature/*Test.php`: aceptación funcional, autorización, confidencialidad, auditoría, producción y regresión.
- `tests/PostgreSQL/*Test.php`: integridad y concurrencia contra PostgreSQL real.
- `phpunit.xml`: pruebas rápidas aisladas en SQLite en memoria; nunca es la base productiva.
- `phpunit.postgresql.xml`: integración exclusiva con PostgreSQL configurado localmente.

## Procedimiento para UAT con personal

1. Restaurar un backup anonimizado o migrar una base PostgreSQL exclusiva de preproducción.
2. Crear cuentas temporales por rol mediante el CRUD, nunca mediante seeders productivos.
3. Entregar credenciales temporales por un canal seguro y forzar su eliminación al finalizar.
4. Ejecutar cada fila marcada **Pendiente UAT**, registrando nombre del validador, fecha, observación y evidencia sin datos sensibles.
5. Corregir únicamente bugs. Registrar ideas nuevas en `MEJORAS_FUTURAS.md`.
6. Eliminar o desactivar todas las cuentas y datos de aceptación al cerrar la sesión.
