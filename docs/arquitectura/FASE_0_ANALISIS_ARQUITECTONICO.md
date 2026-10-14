# Fase 0 — Análisis arquitectónico

> **Estado:** aprobada por la parte interesada el 8 de octubre de 2026. Las decisiones de cierre de esta fase son definitivas para las fases siguientes.

## 1. Resultado ejecutivo

Se adopta un **monolito modular Laravel**. Es la opción más estable para un equipo pequeño, mantiene autenticación, autorización, transacciones, colas, notificaciones y vistas en una sola aplicación y evita la complejidad operativa de microservicios.

La arquitectura se organiza alrededor de módulos de dominio, aunque se mantienen las convenciones de Laravel. Los controladores coordinan solicitudes; los Form Requests validan; las Policies autorizan; los servicios de aplicación ejecutan casos de uso transaccionales; los modelos representan relaciones; y los eventos/notificaciones desacoplan efectos secundarios.

La prioridad transversal es:

1. autorización por rol, unidad, destinatario y confidencialidad;
2. trazabilidad inmutable;
3. almacenamiento privado;
4. experiencia de usuario sencilla;
5. evolución incremental sin adelantar módulos futuros.

## 2. Compatibilidad tecnológica

| Componente | Decisión | Evaluación |
|---|---|---|
| Laravel | 13.x | Adecuado. El proyecto se creó con Laravel 13.35. |
| PHP | 8.5 en producción | Adecuado. Laravel 13 requiere PHP 8.3 o superior. El cierre técnico validó PHP 8.3.28; PHP 8.5 permanece pendiente de ejecutar con runtime real. |
| PostgreSQL | 18.x | Adecuado para integridad, concurrencia, índices y datos JSON de auditoría. Es la base oficial del proyecto. |
| Blade | Plantillas principales | Adecuado para accesibilidad, renderizado servidor y mantenimiento simple. |
| Livewire | 4.x | Adecuado para bandejas, filtros, formularios por pasos y notificaciones sin SPA. |
| Tailwind CSS | 4.x | Adecuado para un sistema visual consistente y responsive. |
| Alpine.js | Incluido por Livewire | Se usa solo para interacciones locales pequeñas; no se carga una segunda copia. |
| Redis | Opcional al inicio | La aplicación comienza con cache y colas en base de datos; producción podrá cambiar a Redis por configuración. |

### Estrategia de versiones

- El archivo `composer.lock` fija versiones exactas reproducibles.
- Se aceptan actualizaciones de parche y minor compatibles después de ejecutar pruebas.
- No se obliga a PHP 8.5 en `composer.json` mientras el entorno de desarrollo compartido continúe en PHP 8.3; el objetivo de producción sí es PHP 8.5.
- No se utilizará SQLite en producción. PHPUnit puede usar SQLite en memoria como motor aislado para pruebas rápidas; las pruebas de integración críticas también deberán ejecutarse contra PostgreSQL 18 en CI.

## 3. Capas y responsabilidades

### Presentación

- Blade para páginas y componentes visuales.
- Livewire para formularios, bandejas, filtros y acciones con retroalimentación inmediata.
- Componentes comunes para botones, alertas, tablas, badges, modales, paginación y estados.
- La interfaz oculta acciones no permitidas mediante `@can`, pero la seguridad siempre se repite en backend.

### Aplicación

- Un servicio por caso de uso sensible: enviar documento, cambiar estado, cargar versión, descargar archivo, delegar responsabilidad.
- Transacciones para operaciones que escriben varias tablas.
- Comandos/jobs para recordatorios, reportes pesados y notificaciones masivas.
- Eventos de dominio después de confirmar la transacción, evitando notificaciones de operaciones fallidas.

### Dominio

- Enums PHP respaldados por cadenas legibles para estados, prioridad, confidencialidad y tipos de destinatario.
- Policies como autoridad de acceso.
- Reglas de transición centralizadas; no se repiten en controladores o componentes.
- Estados por destinatario cuando un documento se envía a varias unidades/personas.

### Infraestructura

- PostgreSQL 18 como base principal.
- Flysystem con un disco privado dedicado a documentos.
- Colas y cache mediante base de datos al inicio; Redis intercambiable mediante variables de entorno.
- Logs técnicos separados de auditoría funcional.

## 4. Decisiones arquitectónicas definitivas

### 4.1 Unidad organizacional única

Se modelará una entidad `unidades` con tipos `facultad`, `carrera` y `kardex`. Esto permite que documentos, usuarios, reportes y permisos apunten a una sola clase de destino sin columnas polimórficas frágiles. `Carrera` será una entidad de dominio filtrada por tipo, cumpliendo el requisito de carreras dinámicas. Una restricción de base de datos garantizará un único Kardex activo institucional.

### 4.2 Ámbito del usuario

Administrador y Co-administrador tienen ámbito global. Jefe, Secretaria, Auxiliar y Extra se asocian administrativamente a una unidad. El rol no concede por sí solo acceso a datos: la Policy combina permiso, ámbito y participación formal. Extra es la excepción estricta al alcance por carrera o destinatario: solo consulta documentos mediante una concesión `document_access_grants` vigente.

### 4.3 Múltiples destinatarios

Cada destino tiene una fila propia. Para un envío a una unidad, el destinatario es la unidad; para un envío personal, se registra usuario y su unidad. Cada destinatario conserva recepción, responsable y estado propios.

El documento mantiene un estado general calculado para las bandejas. La fuente de verdad operativa son los estados de sus destinatarios. Esto evita declarar “Aprobado” un documento mientras otro destino aún lo revisa.

### 4.4 Regla de cierre aprobada

Todos los destinatarios marcados como `requiere_respuesta` deben aprobar para que el documento termine aprobado. Una devolución deja el estado general en devuelto y habilita una versión nueva. Cada rechazo se conserva individualmente y el estado general se recalcula sin borrar decisiones previas. Los destinatarios informativos (`requiere_respuesta = false`) no bloquean la aprobación general.

### 4.5 Códigos sin colisiones

Una tabla de correlativos mantiene el último número por unidad de origen y año. El borrador todavía no consume correlativo. La generación ocurre dentro de la transacción de envío, bloqueando la fila del correlativo (`SELECT ... FOR UPDATE`). Existe además una restricción única en `documentos.codigo` como última defensa.

### 4.6 Versiones y archivos

Una versión representa una entrega documental; una versión contiene uno o varios archivos. Subir una corrección crea una versión nueva y nunca reemplaza filas o archivos anteriores. Cada archivo guarda UUID/ruta interna, nombre original, tamaño, MIME detectado y hash SHA-256.

### 4.7 Confidencialidad

Los documentos normales se autorizan por ámbito y participación. Los confidenciales requieren además participación directa o una concesión en `documento_accesos`. Administrador y Co-administrador conservan acceso global de supervisión; cada consulta o descarga confidencial realizada por ellos se registra en auditoría.

### 4.8 Trazabilidad doble

- `documento_eventos`: línea de tiempo inmutable y comprensible del expediente.
- `auditorias`: seguridad y administración transversal, con IP, agente, valores anteriores/nuevos filtrados.

No se reutiliza una tabla para ambas finalidades.

### 4.9 Fechas

PostgreSQL utilizará `timestamp with time zone`. La aplicación se configura con `America/La_Paz`; PostgreSQL normaliza internamente y la interfaz presenta hora boliviana. Los eventos relevantes guardan precisión de segundos o superior. No se almacenan fechas como texto.

### 4.10 Eliminación

Un creador puede descartar físicamente su propio borrador mientras `sent_at` sea nulo. El descarte no es una anulación y no afecta correlativos ni genera notificaciones. Después del envío no existe eliminación normal: únicamente Administrador o Co-administrador pueden anular con motivo obligatorio. Catálogos y usuarios pueden desactivarse; el historial conserva sus referencias. Las claves foráneas documentales usarán `restrict/no action` o `nullOnDelete` solo cuando no se pierda atribución histórica; no se aplicará borrado en cascada indiscriminado.

### 4.11 Delegación temporal

La delegación no crea un rol permanente ni copia todo el rol de Jefe. Cada delegación declara permisos temporales normalizados, unidad, inicio, fin, autorizador, motivo y estado. Las Policies comprueban el intervalo en cada acción; por ello los permisos dejan de aplicar al vencer aunque el proceso programado todavía no haya actualizado el estado visual a `expired`.

### 4.12 Aprobación por Secretaria

Secretaria conserva los permisos de aprobar, devolver y rechazar dentro de su ámbito. Estos permisos se asignan mediante RBAC y Policies, no mediante comparaciones rígidas de rol. No se implementan aún reglas por tipo documental; el diseño permite que una fase posterior exija aprobación exclusiva de Jefatura para tipos determinados.

## 5. Dependencias

### Instaladas en Fase 1

| Paquete | Motivo |
|---|---|
| `laravel/framework` | Framework base, autenticación, validación, policies, colas y notificaciones. |
| `livewire/livewire` | Interacciones de interfaz sin introducir una SPA. |
| Tailwind CSS/Vite | Estilos accesibles, responsive y compilación de recursos. |
| PHPUnit | Pruebas automatizadas incluidas en Laravel. |

### Diferidas hasta que exista necesidad

| Paquete posible | Fase | Criterio |
|---|---:|---|
| `spatie/laravel-permission` | 4 | Instalar solo después de validar compatibilidad y matriz. Evita implementar RBAC artesanal. |
| Generador PDF mantenido | 17 o 20 | Elegir tras una prueba con la hoja institucional; no es necesario en la base. |
| Exportación Excel | 17 | Incorporar al implementar reportes, no antes. |
| Driver S3 | Despliegue | Solo cuando exista almacenamiento compatible configurado. |

No se instala un paquete de auditoría todavía: primero se definirá qué campos y eventos institucionales son auditables.

## 6. Estructura Laravel propuesta

```text
app/
├── Actions/                  # casos de uso pequeños, si aportan claridad
├── Enums/                    # estados, prioridad, confidencialidad
├── Events/                   # hechos de dominio confirmados
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Jobs/
├── Livewire/                 # pantallas/componentes interactivos
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Documents/
│   ├── Security/
│   └── Reports/
└── Support/                  # utilidades sin reglas de negocio

resources/views/
├── components/
├── layouts/
├── livewire/
└── pages/

docs/
├── arquitectura/
└── seguridad/
```

Se usarán clases y nombres técnicos en inglés (`Document`, `DocumentPolicy`, `StoreDocumentRequest`) y textos de interfaz en español. Esto mantiene consistencia con Laravel y evita mezclar convenciones.

## 7. Riesgos e inconsistencias detectadas

| Tema | Problema | Resolución propuesta |
|---|---|---|
| Estado único vs. varios destinos | Cada destino puede responder en momentos distintos. | Estado por destinatario y estado general agregado. |
| Aprobación por Secretaria | Algunos tipos podrían exigir Jefatura en el futuro. | Secretaria conserva el permiso actual; una regla por tipo se difiere y podrá añadirse sin cambiar el RBAC base. |
| “Recibido” para una carrera | No se define si abrir el documento o pulsar “Recibir” constituye recepción. | Usar aceptación explícita con usuario y hora; abrir no cambia estado. |
| Delegación en módulo 6 y fase 19 | El documento da dos órdenes distintos. | Preparar extensión de Policies desde el inicio e implementar delegaciones en Fase 19, que es el orden final explícito. |
| Confidencialidad y acceso global | Admin/Co-admin tienen responsabilidad institucional incluso sobre contenido restringido. | Acceso global aprobado y auditoría obligatoria de consulta/descarga confidencial. |
| Cierre con múltiples aprobadores | Las decisiones ocurren en momentos distintos. | Todos los destinos que requieren respuesta deben aprobar; decisiones individuales nunca se eliminan. |
| Rol Extra autorizado | Necesita acceso mínimo para impresión. | Concesión explícita por Admin, Co-admin, Jefe de unidad o Secretaria autorizada, con vigencia y revocación auditables. |
| Retención | No existen plazos de archivo o depuración. | No borrar; definir política institucional antes de producción. |
| Anulación | Debe distinguirse de descartar trabajo no oficial. | El creador descarta su borrador; después del envío solo Admin/Co-admin anulan con motivo. |
| ZIP | Puede contener archivos peligrosos. | Permitir descarga, no previsualizar ni extraer en servidor; añadir antivirus en fase futura. |
| Datos personales | No se define clasificación ni política de privacidad. | Aplicar mínimo privilegio, auditoría de descargas y redactar política antes del despliegue. |

## 8. Mejoras incorporadas sin sobrecarga

1. Incorporar estado `borrador` como estado interno previo a `enviado`; sin él no se puede guardar un documento antes del envío.
2. Separar “observación interna” de “motivo oficial de devolución/rechazo”.
3. Añadir `requiere_respuesta` a cada destinatario para distinguir copias informativas.
4. Usar bloqueo optimista (`lock_version`) y bloqueo transaccional al cambiar estado, evitando decisiones simultáneas sobre datos obsoletos.
5. Mostrar acciones pendientes antes que estadísticas en dashboards operativos.
6. Añadir prueba de restauración de backups como requisito de salida a producción.
7. Registrar la zona horaria de presentación y conservar timestamps con zona en base de datos.
8. Usar UUID público para URLs documentales y claves numéricas internas para relaciones eficientes.

## 9. Criterios de Fase 0

- Arquitectura aprobada y trazable: completado.
- Compatibilidad de tecnologías: confirmada.
- Dependencias mínimas: definidas.
- Modelo entidad-relación: documentado por separado.
- Matriz de permisos: documentada por separado.
- Flujo documental: documentado por separado.
- Decisiones institucionales de cierre: incorporadas.
- Plan incremental: documentado por separado.

## 10. Arquitectura implementada al cierre técnico

Al 13 de octubre de 2026, las decisiones anteriores están implementadas sobre Laravel 13, Blade + Livewire, PostgreSQL 18 y almacenamiento documental privado. La aplicación separa presentación, Form Requests, Policies/middleware, servicios transaccionales, modelos y persistencia; usa historial documental y auditoría como trazas append-only independientes.

La topología productiva aprobada es Linux + Nginx + PHP-FPM + PostgreSQL, con Scheduler y backups conjuntos de base y archivos. Redis, object storage, correo institucional, firma digital, OCR, antivirus y alta disponibilidad permanecen como mejoras futuras deliberadas. La evidencia final está en `PRUEBAS_ACEPTACION.md`, `CHECKLIST_ENTREGA.md` y `FASE_24_CIERRE_TECNICO.md`.

