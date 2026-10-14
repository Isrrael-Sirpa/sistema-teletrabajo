# Fase 18 — Auditoría transversal

## 1. Alcance cerrado

La fase implementa auditoría de seguridad y administración sobre la tabla existente `audit_logs`. No se creó una tabla paralela, no se modificó el esquema y no se inició la Fase 19.

La separación funcional es estricta:

- `document_events` explica el ciclo documental a usuarios autorizados;
- `audit_logs` registra autenticación, administración, envío oficial, descargas privadas y concesiones explícitas;
- recepción, revisión, aprobación, devolución y rechazo continúan únicamente en el historial documental, evitando duplicación masiva.

## 2. Estructura reutilizada

`audit_logs` ya incluía lo necesario: actor nullable con FK `RESTRICT`, acción, tipo e identificador del recurso, IP, user-agent, valores anteriores y nuevos `jsonb`, UUID de solicitud y `occurred_at` con zona horaria. Sus índices cubren actor/fecha, acción/fecha, recurso e identificador de solicitud.

No existe columna `metadata` ni `result`. No se añadió una migración solo por ello: el resultado se deriva de acciones canónicas y cerradas (`success`, `denied` o `failed`), por lo que puede mostrarse y filtrarse sin mezclarlo con los valores del recurso. Los identificadores públicos mínimos se guardan en valores permitidos cuando permiten reconocer un recurso eliminado lógicamente.

## 3. Servicio central y sanitización

`AuditLogService` es el único punto de creación ordinaria de registros. Obtiene la IP mediante `Request::ip()`, limita el user-agent a 1000 caracteres, reutiliza un UUID por solicitud y sanitiza recursivamente valores antes de persistirlos.

Se excluyen como mínimo contraseñas, confirmaciones, `remember_token`, tokens, secretos, encabezados de autorización, cookies, identificadores de sesión, recursos binarios y archivos subidos. Los textos permitidos tienen longitud acotada. Ningún punto de integración guarda `$request->all()`.

Los intentos fallidos almacenan únicamente una huella SHA-256 truncada del correo normalizado y un motivo genérico. Un restablecimiento administrativo registra actor, usuario afectado, acción y fecha, sin valores de contraseña ni hashes.

## 4. Acciones registradas

- autenticación exitosa, fallida, denegada por inactividad, limitada por frecuencia y logout;
- creación, edición, activación y desactivación de usuarios;
- cambio de rol y cambio de unidad;
- restablecimiento administrativo de contraseña;
- creación, edición, activación y desactivación de carreras;
- creación, edición, activación y desactivación de tipos documentales;
- envío oficial inicial de documentos;
- descarga autorizada de archivos privados;
- creación, cambio de nivel y revocación de accesos explícitos.

La expiración de una concesión se deriva del tiempo y no genera un evento activo. Tampoco se audita cada lectura ordinaria ni cada transición ya cubierta por `document_events`. La anulación se integrará cuando esa capacidad exista.

## 5. Transacciones e inmutabilidad

Los registros de éxito se escriben dentro de la misma transacción que usuarios, carreras, tipos documentales, envío oficial y accesos explícitos. Si la operación principal revierte, el registro también revierte. Los intentos fallidos de autenticación se registran fuera de una transacción de éxito.

`AuditLog` rechaza actualizaciones y eliminaciones mediante eventos de Eloquent. No existen rutas para crear, editar, eliminar, restaurar o exportar auditoría. La tabla permanece append-only y no tiene política automática de retención; esa política deberá definirse posteriormente según normativa institucional.

## 6. Descargas y documentos restringidos

Cada descarga autorizada registra actor, documento, archivo público, versión, clasificación, IP, fecha y resultado. Nunca guarda bytes, hash del archivo, ruta física, nombre original, asunto ni descripción.

Las acciones sobre documentos `restricted` conservan únicamente identificadores, clasificación y datos operativos mínimos. Las descargas denegadas no se registran de forma masiva para evitar ruido por errores 403 ordinarios.

## 7. IP y proxies

La dirección se obtiene con el mecanismo estándar de Laravel. En la configuración actual no se confían proxies de manera global, por lo que cabeceras reenviadas no se aceptan indiscriminadamente. Si el despliegue incorpora un proxy inverso, sus direcciones y cabeceras deberán configurarse explícitamente antes de considerarlas confiables.

## 8. Consulta y autorización

El módulo de solo lectura está disponible en `/administracion/auditoria` y exige autenticación, cuenta activa y el permiso `audit.view`. El catálogo existente concede ese permiso únicamente a Administrador y Co-administrador. Jefe, Secretaria, Auxiliar y Extra reciben 403 aunque conozcan la URL.

La pantalla muestra fecha/hora, actor, acción, recurso, resultado e IP. Permite filtrar por rango de fechas, actor, acción, tipo de recurso y resultado. Los detalles presentan valores permitidos de forma legible, no un bloque JSON crudo. La consulta precarga al actor, usa los índices existentes y pagina según `AUDIT_LOGS_PER_PAGE`, con 25 registros por defecto.

La pantalla no ofrece acciones de escritura y no permite al Co-administrador modificar cuentas Administrador.

## 9. Verificación

Se cubren autenticación, administración de usuarios, carreras y tipos documentales, envío oficial, descarga, concesiones, rollback, RBAC, valores anteriores/nuevos, sanitización, IP, confidencialidad, filtros, paginación e inmutabilidad. La integración PostgreSQL verifica `inet`, `jsonb`, UUID, `timestamptz`, índices y FK `RESTRICT`.

Resultados finales:

- pruebas específicas de auditoría: 11 pruebas, 64 aserciones;
- integración PostgreSQL específica: 1 prueba, 14 aserciones;
- suite principal completa: 206 pruebas, 1236 aserciones;
- suite PostgreSQL completa: 60 pruebas, 230 aserciones;
- Pint, compilación Blade, rutas y Vite: correctos;
- las 18 migraciones existentes figuran como ejecutadas.

No se ejecutó `migrate:fresh`, `db:wipe` ni una operación destructiva. Tampoco se implementaron delegaciones, SIEM, exportación masiva, retención automática, alertas, correo o reportes.
