# Fase 16 — Dashboard operativo

## Resultado

`GET /panel` es el destino operativo real después de iniciar sesión. El contenido provisional fue reemplazado por un resumen responsive, accesible y específico para la función institucional del usuario. No se agregaron tablas, migraciones, caché, gráficos ni reportes.

La pregunta que guía el panel es: **¿qué necesita atender este usuario ahora?** Las tarjetas son enlaces y abren `/documentos` con el mismo filtro utilizado para calcular su cantidad, o el listado administrativo correspondiente cuando representan usuarios, carreras o notificaciones.

## Widgets por rol

### Administrador y Co-administrador

- documentos enviados pendientes;
- documentos en revisión;
- documentos devueltos;
- vencidos y próximos a vencer;
- notificaciones sin leer;
- usuarios activos y carreras activas;
- accesos rápidos a documentos, usuarios, unidades, tipos documentales y notificaciones.

Los conteos institucionales de estados y vencimientos se resuelven en una sola consulta de agregación condicional. En la lista resumida, el asunto de todo documento `restricted` se sustituye por “Documento de acceso restringido”.

### Jefe de Carrera

- pendientes de recibir destinados a su persona o unidad;
- documentos en revisión;
- trámites con respuesta pendiente que puede decidir;
- documentos devueltos cuyo origen es su carrera;
- vencidos, próximos a vencer y notificaciones.

### Secretaria

- pendientes de recibir y en revisión en los que participa;
- trámites que puede aprobar, devolver o rechazar;
- borradores creados por ella;
- documentos propios devueltos para corrección;
- vencidos, próximos a vencer y notificaciones.

### Auxiliar de Secretaría

- documentos recibidos o en revisión en los que puede colaborar;
- revisiones donde figura como `current_handler`;
- vencimientos de documentos visibles y notificaciones.

El panel no presenta tarjetas ni accesos de decisión formal.

### Extra

- cantidad de documentos con concesión explícita vigente;
- hasta cinco documentos autorizados recientes;
- notificaciones sin leer;
- enlaces de consulta al documento y al centro de notificaciones.

No presenta métricas institucionales, creación documental ni administración. La gestión de concesiones sigue fuera de esta fase; solo se integró la lectura segura de las filas existentes en `document_access_grants`.

## Significado de los contadores

Un documento pendiente puede estar en `sent`, `received`, `in_review` o `returned`, pero cada rol solo cuenta el subconjunto que le corresponde por origen, destino personal, destino de unidad o asignación. `approved`, `rejected` y `annulled` nunca se consideran pendientes.

- **Pendientes de recibir:** existe un destinatario visible en estado `sent`.
- **En revisión:** existe un destinatario visible en estado `in_review`.
- **Requieren decisión:** destinatario que exige respuesta en `received` o `in_review` y pertenece al ámbito del actor.
- **Asignados a mí:** destinatario en revisión cuyo `current_handler_id` es el usuario.
- **Devueltos para corregir:** documento `returned` del lado remitente. Para Secretaria se limita además a documentos creados por ella.
- **Mis borradores:** documentos `draft` creados por el usuario.
- **Vencidos/próximos:** usan los scopes y el umbral de `DocumentDeadlineService`; los estados terminales y borradores quedan excluidos.

Cuando el último evento `document_version_created` es posterior al último `correction_sent`, un devuelto muestra **CORRECCIÓN PREPARADA · FALTA ENVIAR**.

## Visibilidad y autorización

Todos los conteos, listados y elementos prioritarios parten de `Document::visibleTo($user)`. `DocumentPolicy::view` usa esa misma consulta, evitando divergencias entre el dashboard, `/documentos` y el detalle.

La visibilidad conserva:

- ámbito global de Administrador y Co-administrador;
- origen de carrera para Jefe;
- propiedad del origen para Secretaria;
- participación por destinatario personal o de unidad;
- exclusión de destinatarios retirados mediante `SoftDeletes`;
- concesiones explícitas `view` o `download` no revocadas y dentro de su vigencia;
- exclusión automática de documentos descartados mediante `SoftDeletes`.

Una notificación por sí sola no concede acceso. Las URLs de detalle vuelven a evaluar la Policy.
Una concesión `download` habilita también la visualización necesaria para llegar al archivo; una concesión `view` no eleva por sí sola el permiso de descarga.

## Prioridad operativa

La lista principal contiene como máximo cinco documentos y se ordena completamente en SQL:

1. vencidos;
2. prioridad `urgent`;
3. próximos a vencer;
4. prioridad `important`;
5. prioridad `normal`;
6. a igualdad, el documento más antiguo primero.

Extra recibe una lista diferente: autorizaciones visibles ordenadas por actualización más reciente.

## Consultas y rendimiento

`DashboardService` prepara los widgets y `DocumentWorkQueryService` comparte los filtros entre dashboard y listado. Se usan `COUNT`, agregación condicional, `EXISTS`, ordenamiento y `LIMIT 5` en la base de datos. Las relaciones de tipo documental y unidad se cargan anticipadamente; los dos indicadores de corrección se calculan con subconsultas y no generan consultas por fila.

La prueba de rendimiento crea más documentos que el límite de la lista, confirma que solo se retornan cinco y exige un máximo de 16 consultas para el panel global. No se usa Redis ni caché sensible.

Los índices existentes cubren los accesos principales: estado/prioridad y vencimiento en `documents`, destino/estado y responsable/estado en `document_recipients`, y usuario/vigencia en `document_access_grants`. No se justificó una migración adicional.

## Pruebas

La cobertura específica verifica:

- panel global de Administrador y Co-administrador;
- aislamiento por carrera y participación;
- operación de Secretaria y Auxiliar sin privilegios indebidos;
- concesión efectiva, expirada y ausencia de concesión para Extra;
- enmascaramiento de asuntos restringidos;
- conteos de recepción, revisión, decisión, borradores, devueltos y vencimientos;
- exclusión de estados terminales;
- filtros enlazables válidos;
- notificaciones no leídas;
- estado vacío;
- corrección preparada sin enviar;
- prioridad, límite y ausencia de N+1 evidente;
- agregaciones y fechas con PostgreSQL real.

Resultados de cierre:

- suite principal: **180 pruebas, 1104 aserciones**;
- integración PostgreSQL: **57 pruebas, 205 aserciones**;
- prueba específica PostgreSQL del dashboard: incluida y aprobada;
- sin migraciones nuevas ni operaciones destructivas.

## Limitaciones conscientes

- no existe aún interfaz de administración de concesiones explícitas;
- no hay gráficos, KPIs históricos, exportaciones ni reportes;
- no se agregó búsqueda avanzada;
- no se implementó auditoría general ni correo;
- el dashboard no sustituye a las Policies ni ofrece acciones de flujo directamente.

La Fase 17 no fue iniciada.
