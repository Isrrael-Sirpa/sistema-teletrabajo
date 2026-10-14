# Fase 15: fechas límite y recordatorios

Fecha de cierre técnico: 11 de octubre de 2026.

## Resultado

La aplicación calcula y muestra vencimientos sin modificar el estado documental. Se reutilizó `documents.due_at`; no se agregó una columna, tabla, migración ni valor a `DocumentStatus`.

PostgreSQL ya almacenaba `due_at` como `TIMESTAMPTZ NULL` y disponía del índice parcial `documents_due_open_index`. El modelo conserva el cast `datetime` y `DocumentDraftService` interpreta la fecha institucional al final del día en `America/La_Paz`.

## Condiciones

`DocumentDeadlineService` centraliza cinco resultados:

| Condición | Regla |
|---|---|
| Sin fecha límite | `due_at` es nulo. |
| Vigente | Tiene fecha futura fuera del umbral. |
| Próximo a vencer | Fecha futura hasta el final del día ubicado a `DOCUMENT_DUE_SOON_DAYS` días. |
| Vencido | La fecha y hora límite ya pasó y el trámite sigue pendiente. |
| No aplicable | El documento es borrador o está cerrado. |

El umbral inicial es tres días calendario. La condición no se persiste porque deriva de la fecha actual, `due_at` y `general_status`.

## Estados sujetos a vencimiento

- `sent`: el destino debe recibir o atender.
- `received`: el destino debe iniciar o completar su tratamiento.
- `in_review`: el destino o responsable actual debe decidir.
- `returned`: el origen debe preparar y enviar una corrección.

No se generan recordatorios para `draft`, `approved`, `rejected` ni `annulled`. Las copias informativas tampoco reciben recordatorios de actuación. Una corrección conserva el `due_at` original; no existe extensión de plazo en esta fase.

## Consultas e interfaz

El modelo `Document` incorpora scopes para pendientes con fecha, próximos a vencer y vencidos. Las condiciones de estado y fecha se ejecutan en SQL y aprovechan los índices existentes; no se cargan todos los documentos para filtrarlos en PHP.

El listado `/documentos` permite filtrar por:

- próximos a vencer;
- vencidos.

Listado y detalle muestran la fecha límite y los badges textuales `PRÓXIMO A VENCER` o `VENCIDO`. No se depende únicamente del color y los documentos cerrados no muestran alertas activas. No se creó un dashboard definitivo.

## Destinatarios de recordatorios

`DocumentDeadlineRecipientResolver` aplica responsabilidad operativa:

- destinos personales formales: persona activa indicada;
- destinos por unidad: Jefe de Carrera y Secretaria activos;
- revisión activa: incluye `current_handler` cuando es Jefe, Secretaria o Auxiliar;
- vencidos en destino: además informa al creador/remitente activo;
- devueltos: creador/remitente y Jefe/Secretaria activos del origen.

Los usuarios Extra, inactivos y las copias informativas quedan excluidos. La colección final se deduplica por usuario.

## Idempotencia y transacciones

No fue necesaria persistencia adicional. La fila oficial de Laravel en `notifications` es también el marcador estructurado del hito y contiene `deadline_milestone` y `deadline_due_at`; la decisión no depende del texto visible.

Antes de enviarla se calcula un UUID v5 determinista a partir de:

```text
document_public_id + user_public_id + due_at + milestone
```

La clave primaria de `notifications` impide duplicados. Además, cada documento se procesa dentro de una transacción con `SELECT ... FOR UPDATE`; una segunda ejecución concurrente espera, observa el UUID existente y omite el aviso. Si insertar la notificación falla, la transacción revierte y no queda un marcador falso.

El Scheduler usa también `withoutOverlapping()`. Esta protección operativa complementa, pero no reemplaza, la idempotencia de datos.

## Notificación y confidencialidad

`DocumentDeadlineNotification` utiliza exclusivamente `database`. Para documentos normales muestra código y fecha cuando corresponde. Para `restricted` utiliza texto genérico y no guarda asunto, motivo ni código.

El aviso almacena una ruta relativa al documento. Abrirla vuelve a ejecutar `DocumentPolicy`; recibir un recordatorio no concede acceso.

## Comando y Scheduler

Ejecución manual:

```bash
php artisan documents:process-deadlines
```

La salida informa únicamente documentos evaluados, recordatorios generados y documentos omitidos. No imprime asuntos, códigos confidenciales ni destinatarios.

El Scheduler registra el comando una vez al día a las 08:00, con zona `America/La_Paz`. La hora se configura mediante:

```dotenv
DOCUMENT_DUE_SOON_DAYS=3
DOCUMENT_DEADLINE_REMINDER_HOUR=8
```

Laravel necesita que el sistema operativo ejecute `php artisan schedule:run` cada minuto. En Linux puede utilizarse cron; en Windows, el Programador de tareas; y en un despliegue administrado puede mantenerse `schedule:work` mediante `systemd`.

## Pruebas y cierre

Las pruebas usan `CarbonImmutable::setTestNow()` y no dependen del reloj real.

- específicas de Fase 15: 10 pruebas, 59 aserciones;
- PostgreSQL específico: 2 pruebas, 11 aserciones;
- suite funcional completa: 166 pruebas, 1.039 aserciones;
- suite PostgreSQL completa: 56 pruebas, 199 aserciones.

La integración PostgreSQL verifica `TIMESTAMPTZ`, índice parcial e idempotencia con dos procesos simultáneos. También se comprobó que una corrección conserva `due_at`.

## Archivos principales

### Creados

- `app/Enums/DocumentDeadlineStatus.php`
- `app/Services/DocumentDeadlineService.php`
- `app/Services/DocumentDeadlineRecipientResolver.php`
- `app/Services/DocumentDeadlineReminderService.php`
- `app/Notifications/DocumentDeadlineNotification.php`
- `app/Console/Commands/ProcessDocumentDeadlines.php`
- `tests/Feature/DocumentDeadlineTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentDeadlineTest.php`
- `docs/FASE_15_FECHAS_LIMITE_RECORDATORIOS.md`

### Modificados

- `app/Enums/DocumentStatus.php`
- `app/Models/Document.php`
- `app/Http/Controllers/DocumentController.php`
- `resources/views/documents/index.blade.php`
- `resources/views/documents/show.blade.php`
- `routes/console.php`
- `config/documents.php`
- `.env.example`
- `tests/Feature/DocumentVersioningTest.php`
- `README.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/arquitectura/FLUJO_DOCUMENTAL.md`

## Límite de fase

No se implementaron correo, SMS, push, ampliación de plazo, dashboards definitivos, reportes ni auditoría general. La Fase 16 no fue iniciada.
