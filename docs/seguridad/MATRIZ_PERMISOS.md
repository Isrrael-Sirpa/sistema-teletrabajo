# Matriz inicial de roles y permisos

> **Estado:** RBAC implementado en la Fase 4; ámbito documental aplicado por `DocumentPolicy` desde las Fases 8 a 11.

## 1. Principio de autorización

Un permiso indica **qué** acción puede realizar una persona. La unidad organizacional y la relación con un recurso determinan **dónde** puede realizarla.

Tener `documents.approve` no concede acceso global. Las Policies documentales combinan:

1. usuario autenticado y activo;
2. permiso atómico;
3. estado compatible del documento;
4. unidad, participación, destinatario o responsabilidad;
5. confidencialidad y concesiones explícitas;
6. delegación temporal vigente, cuando corresponda.

El RBAC no sustituye estas comprobaciones de ámbito. Ocultar botones tampoco constituye autorización.

## 2. Roles institucionales

| Slug interno | Nombre visible | Ámbito previsto |
|---|---|---|
| `administrator` | Administrador | Global; la unidad puede ser nula. |
| `co_administrator` | Co-administrador | Global; la unidad puede ser nula. |
| `career_head` | Jefe de Carrera | Unidad/carrera asociada. |
| `secretary` | Secretaria | Unidad/carrera asociada. |
| `secretary_assistant` | Auxiliar de Secretaría | Unidad/carrera asociada. |
| `extra` | Extra | Únicamente recursos concedidos explícitamente. |

No se usan identificadores numéricos para decidir roles. Los slugs están centralizados en `App\Enums\RoleName`.

## 3. Catálogo de permisos

### Usuarios

- `users.view`
- `users.create`
- `users.update`
- `users.activate`
- `users.deactivate`
- `users.reset_password`
- `users.assign_roles`

### Unidades

- `units.view`
- `units.create`
- `units.update`
- `units.activate`
- `units.deactivate`
- `units.manage_kardex`

### Tipos documentales

- `document_types.view`
- `document_types.manage`

### Documentos

- `documents.view`
- `documents.create`
- `documents.update_draft`
- `documents.discard_draft`
- `documents.send`
- `documents.receive`
- `documents.review`
- `documents.assistant_review`
- `documents.approve`
- `documents.return`
- `documents.reject`
- `documents.download`
- `documents.version`
- `documents.comment`
- `documents.grant_access`
- `documents.annul`
- `documents.view_history`

### Administración y soporte

- `reports.view_global`
- `reports.view_unit`
- `reports.export`
- `audit.view`
- `roles.manage`
- `permissions.manage`
- `settings.manage`
- `notifications.send`
- `delegations.manage_global`
- `delegations.manage_unit`

Los permisos `delegations.manage_global` y `delegations.manage_unit` controlan el módulo implementado en la Fase 19. Las capacidades temporales se guardan aparte y se resuelven dinámicamente; nunca se agregan a los pivotes de Spatie.

## 4. Asignación exacta por rol

### Administrador

Recibe los 42 permisos del catálogo. No se implementa un `Gate::before` que ignore permisos: toda capacidad continúa siendo explícita y revocable.

### Co-administrador

Recibe los 42 permisos actuales, igual que Administrador. Desde la Fase 6, `UserPolicy` impide que cree, edite, desactive o restablezca la contraseña de cualquier cuenta Administrador. Esta protección conservadora evita depender de una identidad o ID especial; el Co-administrador sí puede ver esas cuentas por su ámbito global.

### Jefe de Carrera

```text
units.view
users.view
users.create
users.update
users.activate
users.deactivate
users.reset_password
users.assign_roles
document_types.view
documents.view
documents.create
documents.update_draft
documents.discard_draft
documents.send
documents.receive
documents.review
documents.assistant_review
documents.approve
documents.return
documents.reject
documents.download
documents.version
documents.comment
documents.grant_access
documents.view_history
reports.view_unit
reports.export
notifications.send
delegations.manage_unit
```

Todos permanecen sujetos a la unidad del Jefe y a la Policy del recurso.

### Secretaria

```text
units.view
document_types.view
documents.view
documents.create
documents.update_draft
documents.discard_draft
documents.send
documents.receive
documents.review
documents.approve
documents.return
documents.reject
documents.download
documents.version
documents.comment
documents.grant_access
documents.view_history
reports.view_unit
reports.export
notifications.send
```

La Secretaria puede aprobar, devolver y rechazar dentro de su ámbito. Una regla futura podrá exigir aprobación exclusiva de Jefatura para determinados tipos documentales sin cambiar el significado del permiso.

### Auxiliar de Secretaría

```text
units.view
document_types.view
documents.view
documents.review
documents.assistant_review
documents.download
documents.comment
reports.view_unit
reports.export
notifications.send
```

No recibe `documents.approve`, `documents.return`, `documents.reject`, `documents.annul` ni permisos administrativos.

### Extra

```text
documents.view
documents.download
```

Estos permisos solo habilitan consulta y descarga. Extra no participa automáticamente en destinos de unidad, no puede seleccionarse como destino personal y no cambia estados. Su acceso documental se origina únicamente en una concesión explícita vigente mediante `document_access_grants`.

## 5. Reglas invariantes

- Administrador y Co-administrador no dependen de una unidad organizacional y se guardan con unidad nula.
- Jefe, Secretaria, Auxiliar y Extra requieren una carrera activa.
- El Jefe solo administra Secretaria, Auxiliar y Extra de su propia carrera.
- Solo puede existir un Jefe principal activo por carrera; los encargos temporales se resuelven mediante delegaciones implementadas y auditadas.
- Nadie puede desactivar su propia cuenta ni dejar el sistema sin un Administrador activo.
- Auxiliar nunca toma decisiones definitivas ni anula documentos.
- Extra nunca crea, modifica, envía ni cambia estados.
- La descarga usa la Policy del documento y del archivo; no existe previsualización pública.
- Los documentos confidenciales siempre requieren comprobaciones adicionales y auditoría.
- Toda delegación temporal verifica permiso concreto, vigencia, revocación y ámbito en cada solicitud.
- Las restricciones se aplican en backend mediante Gate, middleware y Policies; la interfaz solo refleja el resultado.

## 6. Evidencia de aceptación final

La Fase 24 revalidó los seis roles mediante escenarios positivos y negativos sobre dashboard, documentos, búsqueda, reportes, auditoría, accesos explícitos y delegaciones. `ProjectAcceptanceTest` crea identidades ficticias efímeras para cada rol; ningún usuario o password de aceptación forma parte de seeders productivos.

La matriz completa y reutilizable para UAT institucional se encuentra en `docs/PRUEBAS_ACEPTACION.md`. La UAT no puede ampliar permisos: cualquier necesidad nueva debe tratarse como mejora futura y pasar por una revisión de RBAC y Policies.
