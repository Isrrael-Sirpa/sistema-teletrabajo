# Fase 11: estados y flujo documental

Actualización de autoridad del 19/10/2026: las cinco acciones formales quedan limitadas a Jefe y Secretaria activos del destino. El alcance global no concede esas acciones y el Auxiliar no inicia revisión. Véase [Autoridad formal y visor privado](AUTORIDAD_Y_VISOR_PRIVADO.md) para el cambio y su validación posterior.

## Resultado

Se implementó el envío oficial y el flujo principal por destinatario: `sent`, `received`, `in_review`, `approved`, `returned` y `rejected`. El documento conserva un estado general agregado calculado a partir de sus destinatarios.

La implementación reutiliza `documents`, `document_recipients` y `document_counters`. La estructura aprobada en la Fase 2 resultó suficiente y no fue necesario crear ni modificar migraciones.

## Envío oficial

Un usuario autorizado puede enviar un documento si continúa en `draft`, su `lock_version` coincide, el tipo y la unidad de origen siguen activos, el asunto es válido y existe al menos un destinatario `prepared`. Además, todos los destinos se vuelven a validar dentro de la transacción: unidad activa, pertenencia personal vigente, rol permitido y compatibilidad con `requires_response`. No se exige archivo adjunto; esa regla queda abierta a configuración futura por tipo documental.

La transacción bloquea el documento y sus destinatarios, obtiene el correlativo, genera el código, registra remitente y fecha, cambia el documento a `sent`, formaliza los destinatarios y aumenta sus versiones de bloqueo. Un fallo revierte también el correlativo, por lo que no deja un envío parcial.

## Código institucional y concurrencia

El código se construye con las siglas reales almacenadas en las unidades:

```text
{FACULTAD}-{UNIDAD}-{AÑO}-{CORRELATIVO DE 6 DÍGITOS}
```

Ejemplo: `FTEC-SIS-2026-000001`. Para un origen que sea la propia Facultad no se duplica la sigla. La cantidad de dígitos es configurable con `DOCUMENT_CODE_DIGITS`.

`document_counters` conserva un contador por unidad de origen y año. La fila se crea de forma idempotente y luego se bloquea mediante `SELECT ... FOR UPDATE`; la restricción única `(origin_unit_id, year)` y `documents.code` actúan como barreras adicionales. Dos procesos PostgreSQL simultáneos fueron probados y generaron correlativos distintos.

## Matriz de transiciones

| Acción | Estado inicial | Estado final | Condiciones |
|---|---|---|---|
| Enviar | `draft` | `sent` | Documento válido, destinatarios preparados y permiso de envío. |
| Recibir | `sent` | `received` | Jefe o Secretaria activos del destino y permiso correspondiente. |
| Iniciar revisión | `received` | `in_review` | Destino formal, permiso de revisión y `requires_response=true`. |
| Aprobar | `in_review` | `approved` | Participación en el destino y permiso de aprobación. |
| Devolver | `received` o `in_review` | `returned` | Participación, permiso y motivo obligatorio. |
| Rechazar | `received` o `in_review` | `rejected` | Participación, permiso y motivo obligatorio. |

No se admiten saltos como `sent -> approved`, `draft -> rejected` o `approved -> received`. Las copias informativas terminan en `received`: no pueden entrar en revisión ni aprobar, devolver o rechazar.

## Autorización por destino

- Administrador y Co-administrador pueden supervisar globalmente, pero no reciben ni ejecutan las otras cuatro acciones formales por su alcance global. La anulación autorizada es independiente.
- En un destino de unidad, Jefe y Secretaria pueden recibir y decidir dentro de su unidad.
- Auxiliar no puede recibir, iniciar revisión, aprobar, devolver ni rechazar; conserva su colaboración no decisoria autorizada.
- En un destino personal formal solo se admite Jefe de Carrera o Secretaria; la persona indicada actúa únicamente si además posee el permiso RBAC necesario.
- Auxiliar puede ser destinatario personal únicamente como copia informativa. Esa copia no recibe artificialmente capacidad de recepción o decisión y no bloquea el cierre formal.
- Extra no puede ser destinatario documental normal ni obtiene visibilidad automática por pertenecer a la unidad. Su acceso corresponde a `document_access_grants` en la fase prevista.
- Conocer el UUID de un documento o destinatario no concede acceso.

La misma regla central se aplica al crear o reclasificar un destinatario y nuevamente al enviar. Esto impide formalizar registros preparados antes del ajuste, usuarios cuyo estado, rol o unidad cambió y solicitudes manipuladas.

Los documentos enviados aparecen a sus destinatarios mediante el scope de `Document`. Los destinatarios todavía `prepared` no amplían visibilidad antes del envío. La confidencialidad no modifica estas reglas ni agrega participantes.

## Recepción y decisiones

Abrir el detalle nunca cambia el estado. La recepción exige la acción explícita **Recibir documento** y guarda `received_by` y `received_at`.

Al iniciar revisión se guarda `current_handler_id`; `updated_at` conserva la hora de la transición porque el esquema no incluye un campo separado para inicio de revisión.

Aprobar, devolver o rechazar guarda `decided_by`, `decided_at` y, cuando corresponde, `decision_reason`. Los motivos de devolución y rechazo se recortan, no admiten solo espacios y tienen un máximo configurable mediante `DOCUMENT_DECISION_REASON_MAX_LENGTH`.

## Estado general agregado

El algoritmo usa solamente destinos con `requires_response=true` para las decisiones formales:

1. `rejected` si algún destino formal fue rechazado.
2. `returned` si algún destino formal fue devuelto y no existe rechazo.
3. `approved` si existe al menos un destino formal y todos están aprobados.
4. `in_review` si algún destino formal está en revisión.
5. `received` si algún destino formal está recibido.
6. Para un documento compuesto solo por copias, `received` si alguna copia fue recibida.
7. `sent` en los demás casos posteriores al envío.

Las decisiones individuales nunca se borran. Así, `approved + rejected` conserva ambos resultados y produce estado general `rejected`. Una copia pendiente no bloquea la aprobación de todos los destinos formales.

## Bloqueo optimista

Cada acción envía `document_lock_version` y `recipient_lock_version`. El servicio bloquea ambas filas dentro de la transacción, compara las versiones y solo después autoriza y modifica. Una decisión ganadora incrementa ambas versiones; una segunda decisión concurrente se rechaza y no sobrescribe la primera.

La prueba PostgreSQL ejecuta aprobación y devolución simultáneas en procesos independientes y confirma un único ganador.

## Inmutabilidad posterior al envío

Al abandonar `draft` dejan de estar autorizadas la edición, modificación de destinatarios, carga o retiro de archivos y descarte físico. La descarga privada continúa disponible según `DocumentPolicy`.

## Interfaz y rutas

El detalle muestra código oficial, fecha de envío, estado y trazabilidad básica por destinatario. En borrador presenta un resumen de origen, tipo, asunto, destinatarios y archivos antes de confirmar el envío.

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `/documentos/{document}/enviar` | Envío oficial. |
| `PATCH` | `/documentos/{document}/destinatarios/{recipient}/estado/{transition}` | Recepción, revisión o decisión. |

Los botones se renderizan mediante Policies. La devolución y el rechazo incluyen un campo obligatorio de motivo; la aprobación y el envío solicitan confirmación.

## Pruebas

Las pruebas funcionales cubren envío con y sin archivos, rechazo sin destinatarios, códigos, correlativos, autorización, recepción explícita, recepción personal de Jefe y Secretaria, revisión, decisiones, Auxiliar como copia y apoyo de unidad, exclusión de Extra, doble validación de elegibilidad, copias, motivos, agregación, múltiples destinos, bloqueo optimista e inmutabilidad.

La integración PostgreSQL cubre constraints de copias y decisiones, correlativos simultáneos y decisiones simultáneas mediante procesos independientes.

Validación final después del ajuste de destinatarios personales:

- destinatarios y flujo específicos: **34 pruebas, 228 aserciones**;
- integración PostgreSQL específica: **9 pruebas, 39 aserciones**;
- suite completa: **132 pruebas, 782 aserciones**;
- integración PostgreSQL completa: **47 pruebas, 153 aserciones**;
- Pint, compilación Blade y build de Vite: aprobados.

## Archivos principales

- `app/Services/DocumentWorkflowService.php`
- `app/Enums/DocumentTransition.php`
- `app/Models/DocumentCounter.php`
- `app/Http/Controllers/DocumentWorkflowController.php`
- `app/Http/Requests/SendDocumentRequest.php`
- `app/Http/Requests/TransitionDocumentRecipientRequest.php`
- `app/Policies/DocumentPolicy.php`
- `tests/Feature/DocumentWorkflowTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentWorkflowTest.php`

## Fuera de alcance

No se implementaron versión 2, corrección posterior a devolución, historial documental completo, notificaciones, auditoría completa, reportes ni firma digital. La Fase 12 no fue iniciada.
