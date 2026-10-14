# Fase 17 — Gestión de accesos explícitos a documentos

## Resultado

La estructura `document_access_grants` creada en la Fase 2 se reutiliza sin migraciones. El detalle documental permite conceder, cambiar de nivel y revocar accesos explícitos para usuarios Extra. Una concesión habilita consulta o consulta y descarga, pero nunca convierte al beneficiario en destinatario ni le entrega acciones del flujo documental.

## Estructura reutilizada

Cada ciclo conserva:

- documento y usuario beneficiario;
- concedente y fecha de creación;
- permiso `view` o `download`;
- inicio y fin opcional de vigencia como `TIMESTAMPTZ`;
- motivo estructurado;
- revocador, fecha y motivo de revocación;
- FKs `RESTRICT`, índices de documento/usuario y vigencia;
- índice único parcial para una concesión no revocada equivalente.

La estructura resultó suficiente. No se agregó tabla, columna ni índice.

## Quién puede conceder

La autorización combina el permiso RBAC `documents.grant_access`, `DocumentPolicy`, visibilidad y ámbito:

- Administrador y Co-administrador: documentos institucionales globales y Extras activos de cualquier unidad.
- Jefe de Carrera: documentos originados en su carrera o destinados a ella; solo Extras activos de su carrera.
- Secretaria: documentos creados por ella dentro de su carrera; solo Extras activos de esa carrera.
- Auxiliar y Extra: no pueden administrar concesiones.

El backend vuelve a resolver y validar beneficiario, rol, estado y unidad dentro de la transacción. El selector no constituye autorización.

## Capacidades

- `view`: permite abrir el detalle, pero no descargar archivos.
- `download`: permite abrir el detalle y descargar mediante la ruta privada autorizada.

`DocumentPolicy::downloadFile` sigue siendo la autoridad. Si el acceso del usuario procede únicamente de una concesión, exige específicamente `download`. Las rutas físicas nunca se exponen.

La concesión no permite editar, enviar, administrar destinatarios o archivos, recibir, revisar, aprobar, devolver, rechazar ni preparar o reenviar correcciones. Extra continúa fuera de `document_recipients`.

## Vigencia

Toda concesión comienza en el momento en que se crea y puede tener `ends_at` opcional. La zona horaria de aplicación es `America/La_Paz` y PostgreSQL conserva las fechas como `TIMESTAMPTZ`.

`DocumentAccessGrant::effective()` define una concesión vigente como:

- no revocada;
- iniciada;
- sin vencimiento o con vencimiento futuro.

La expiración se evalúa en cada consulta, sin Scheduler ni proceso batch. Un registro expirado permanece en la base. Si se vuelve a conceder acceso a la misma persona, el ciclo expirado se cierra estructuradamente y se crea uno nuevo.

## Cambios de nivel y revocación

Cambiar `view → download` o `download → view` no sobrescribe el nivel anterior. La operación:

1. bloquea el documento y la concesión;
2. revoca el ciclo anterior, registrando actor, fecha y motivo de cambio;
3. crea un ciclo nuevo con el nivel solicitado y conserva el vencimiento anterior.

“Revocar acceso” exige un motivo. El registro no se elimina físicamente y deja de ser reconocido inmediatamente por `visibleTo()`, la Policy, el listado y el dashboard.

## Concurrencia y duplicados

Las altas y cambios usan transacción. El bloqueo `FOR UPDATE` de la fila del documento serializa concesiones concurrentes para la misma entidad, incluso cuando una intenta `view` y otra `download`. Después del bloqueo se comprueba cualquier ciclo no revocado del mismo documento y usuario.

El índice parcial `active_access_grants_unique` actúa como segunda barrera para permisos equivalentes. Una eventual violación de unicidad se convierte en un mensaje de validación y no produce error 500.

La prueba PostgreSQL inicia dos procesos simultáneos: uno crea el ciclo y el otro termina como rechazo controlado; permanece una sola fila activa.

## Confidencialidad y documentos descartados

Un documento `restricted` puede compartirse expresamente, pero solo por un otorgante que pueda administrar sus accesos. El formulario se muestra después de autorizar el documento y no filtra asuntos a terceros.

Los documentos con `deleted_at` quedan excluidos por el scope global de `SoftDeletes`; ni el route model binding ni `visibleTo()` pueden reactivarlos mediante una concesión existente.

## Interfaz

La sección **Accesos explícitos** del detalle muestra únicamente a administradores autorizados:

- persona, rol y unidad;
- nivel de acceso;
- concedente y fecha;
- vencimiento;
- cambio explícito de nivel;
- revocación motivada.

El formulario selecciona Extras activos con etiquetas legibles y vencimiento opcional. Jefe y Secretaria reciben solo candidatos de su propia carrera. Los IDs técnicos no se presentan como texto al usuario.

## Coherencia con Fase 16

`Document::visibleTo()` continúa siendo la definición compartida de visibilidad para dashboard, listado, detalle y Policy. El dashboard de Extra muestra solo concesiones vigentes sobre documentos no descartados. Una notificación no concede acceso.

## Pruebas y cierre

Se validaron los 27 escenarios solicitados, agrupados en pruebas funcionales y PostgreSQL:

- concesión por Admin, Co-administrador, Jefe y Secretaria;
- aislamiento de carrera y documentos administrables;
- denegación a Auxiliar, Extra y beneficiarios inactivos;
- diferencias entre `view` y `download` usando descarga privada real;
- ausencia, expiración y revocación;
- persistencia histórica de revocación;
- documentos restringidos y aislamiento entre Extras;
- elevación y reducción de nivel con ciclos históricos;
- duplicados y concurrencia real;
- documentos descartados;
- manipulación cruzada de una concesión de otro documento;
- coherencia entre `visibleTo()`, Policy, detalle y dashboard;
- FKs, checks, unicidad parcial y `TIMESTAMPTZ` en PostgreSQL.

Resultados:

- pruebas específicas funcionales: **15 pruebas, 68 aserciones**;
- pruebas específicas PostgreSQL: **2 pruebas, 11 aserciones**;
- suite principal: **195 pruebas, 1172 aserciones**;
- integración PostgreSQL: **59 pruebas, 216 aserciones**.

No se agregaron eventos documentales, notificaciones, auditoría transversal, delegaciones, correo, reportes ni enlaces públicos. La Fase 18 no fue iniciada.
