# Fase 19: Delegaciones temporales

## Resultado

Se implementó la sustitución temporal de una Jefatura de Carrera sin modificar el rol permanente del Jefe ni el de la Secretaria. La autorización final conserva la regla `permiso permanente O capacidad delegada efectiva`, siempre limitada por la Policy del recurso y por una única carrera.

No se inició la Fase 20.

## Estructura incremental

La migración `2026_10_12_000100_extend_temporary_delegations_for_career_head.php` se aplicó sobre la estructura existente, sin modificar migraciones anteriores y sin borrar información.

- `temporary_delegations.delegator_user_id` es obligatorio, tiene índice y FK `RESTRICT` a `users`.
- `delegator_user_id` identifica al Jefe titular; `authorized_by` identifica al actor que autorizó el alta. No son intercambiables.
- `temporary_delegation_permissions` guarda `temporary_delegation_id`, `permission_id` y `created_at`.
- La clave primaria compuesta impide repetir una capacidad y ambas FKs usan `RESTRICT`.
- El `down()` elimina primero el pivote y luego la columna. Por protección histórica, se niega a revertir mientras existan delegaciones o capacidades registradas.

## Reglas institucionales

Pueden crear o revocar:

- Administrador y Co-administrador, con supervisión global;
- el Jefe titular activo, únicamente para su propia carrera y su propia autoridad.

El beneficiario debe ser una Secretaria activa de la misma carrera. No se admiten Auxiliar, Extra, usuarios globales, inactivos ni personas de otra carrera. La Secretaria no puede autoasignarse la delegación y el delegado no puede encadenarla a otra persona.

La validación backend comprueba nuevamente dentro de la transacción que el delegante continúa activo, mantiene el rol `career_head`, pertenece a la carrera y es el Jefe titular aplicable.

## Capacidades

`CareerHeadDelegationCatalog` define una lista cerrada formada únicamente por permisos reales del rol Jefe. La creación exige que:

```text
capacidades solicitadas
⊆ catálogo delegable de Jefatura
⊆ permisos actuales del rol Career Head
```

Se excluyen auditoría global, roles, configuración, administración global, reportes globales y cualquier capacidad ajena al catálogo. Una manipulación del formulario vuelve a validarse en `TemporaryDelegationService`.

La delegación no llama `assignRole()` ni `givePermissionTo()` y no escribe en `model_has_roles` o `model_has_permissions`. La tabla `permissions` de Spatie funciona únicamente como catálogo referencial.

## Vigencia y estado

Los intervalos utilizan semántica `[starts_at, ends_at)`:

```text
starts_at <= ahora
y ahora < ends_at
y revoked_at es null
```

Por ello, dos intervalos adyacentes son válidos. La autorización no depende de `status`, no requiere Scheduler y se vuelve efectiva o expira en la siguiente petición, incluso si la sesión ya estaba iniciada.

La interfaz deriva los estados:

- Próxima: aún no llegó `starts_at`.
- Activa: está dentro del intervalo y no fue revocada.
- Expirada: llegó el fin exclusivo.
- Revocada: existe `revoked_at`.

`status` se conserva por compatibilidad; una revocación lo establece en `revoked` conforme al CHECK existente.

## Concurrencia

Toda alta sigue este orden:

1. inicia una transacción;
2. bloquea la fila estable de la carrera con `SELECT ... FOR UPDATE`;
3. valida delegante, beneficiario, actor, período y capacidades;
4. busca cualquier delegación no revocada con `existing.starts_at < requested.ends_at` y `existing.ends_at > requested.starts_at`;
5. crea delegación y capacidades;
6. registra auditoría;
7. confirma la transacción.

La carrera es el mutex común de todas las altas. La prueba PostgreSQL lanza dos procesos y conexiones independientes con períodos solapados: uno crea y el otro espera, reevalúa y es rechazado. Solo queda una delegación no revocada.

## Revocación e historia

La revocación bloquea la delegación, exige motivo, registra `revoked_by`, `revoked_at` y `revocation_reason`, y conserva el pivote de capacidades. Después de confirmar, la siguiente autorización deja de reconocerla. No hay edición silenciosa de fechas o permisos ni eliminación física.

Creación y revocación se registran en `audit_logs`, con actor, delegante, delegado, carrera, intervalo, capacidades y motivo administrativo permitido. No se crean `document_events`, porque no es una transición documental.

## Integración de autorización

- `DelegatedAuthorizationService` contiene la definición única de delegación efectiva y no almacena resultados en sesión.
- `EnsurePermissionOrDelegation` permite alcanzar únicamente rutas cuyo permiso permanente o delegado corresponda.
- `UserPolicy`, `DocumentPolicy`, `Document::visibleTo()`, los servicios de usuarios, consultas operativas y dashboard vuelven a aplicar capacidad y ámbito.
- La gestión temporal de usuarios continúa limitada a roles secundarios de la misma carrera; nunca alcanza Administradores, Co-administradores ni otra unidad.
- Los documentos `restricted` solo adquieren la visibilidad equivalente al Jefe dentro de la carrera delegada, nunca visibilidad institucional global.
- El dashboard mantiene las funciones habituales de Secretaria y agrega el bloque “Jefatura temporal activa”; desaparece automáticamente al expirar o revocar.

## Interfaz

El módulo está disponible en `/administracion/delegaciones` y muestra carrera, Jefe titular, Secretaria delegada, período, estado derivado, autorizador, motivo y capacidades. La revocación solicita un motivo obligatorio. Para un Jefe, la carrera y el delegante se derivan del backend y no se aceptan como elección libre.

## Verificación

Las pruebas específicas comprueban creación por los tres actores autorizados, autoasignación denegada, elegibilidad, fechas, períodos adyacentes, solapamiento, vigencia determinista, revocación, ámbito de usuarios, documentos restringidos, recepción y decisión documental, dashboard, auditoría, ausencia de `document_events` y conservación exacta de RBAC permanente.

La integración PostgreSQL comprueba `TIMESTAMPTZ`, nulabilidad, FKs `RESTRICT`, PK compuesta, CHECK de fechas, intervalos adyacentes y concurrencia con procesos independientes.

Resultados finales del 12 de octubre de 2026:

- pruebas específicas de Fase 19: 17 pruebas, 73 aserciones;
- suite general: 223 pruebas, 1309 aserciones;
- suite PostgreSQL: 63 pruebas, 246 aserciones;
- concurrencia específica PostgreSQL: 3 pruebas, 16 aserciones;
- todas las 19 migraciones figuran como `Ran`;
- Pint: aprobado;
- compilación de Blade: aprobada;
- Vite: compilación de producción aprobada.
