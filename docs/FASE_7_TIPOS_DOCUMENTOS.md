# Fase 7 — Tipos de documentos

> **Estado:** implementada y validada el 9 de octubre de 2026.

## 1. Alcance completado

Se implementó el catálogo institucional de tipos documentales reutilizando la tabla `document_types` creada en la Fase 2. El módulo permite listar, consultar, crear, editar, activar y desactivar tipos sin eliminación física.

No se implementó creación de documentos, archivos, destinatarios, estados, historial, versionado ni flujo de aprobación.

## 2. Revisión estructural

No fue necesaria una migración incremental. La estructura existente ya contiene:

- clave interna `id`;
- UUID público `public_id` para URLs;
- `name` de hasta 120 caracteres;
- `code` estable de hasta 50 caracteres;
- `description` opcional;
- `is_active`;
- `created_by` nullable con FK restrictiva;
- timestamps con zona horaria;
- índice por estado y nombre;
- índices PostgreSQL únicos sobre `LOWER(name)` y `LOWER(code)`.

La relación `documents.document_type_id` utiliza `RESTRICT`, por lo que un tipo históricamente utilizado tampoco puede eliminarse accidentalmente desde la base mediante una eliminación en cascada.

## 3. Código interno estable

Se reutilizó el campo `code` como identificador técnico estable. Para los tipos creados desde la interfaz:

- se genera automáticamente desde el nombre;
- se normaliza en minúsculas y formato `snake_case`;
- se limita a 50 caracteres;
- se resuelven posibles colisiones determinísticamente;
- permanece sin cambios cuando administración edita el nombre.

El código no se utiliza todavía para introducir reglas documentales especiales. `Otros` y `Documento de Kardex` participan en el mismo catálogo general.

## 4. Catálogo inicial

El seeder idempotente carga:

1. Carta
2. Informe
3. Memorándum
4. Circular
5. Solicitud
6. Resolución
7. Nota
8. Formulario
9. Certificación
10. Correspondencia
11. Documento de Kardex
12. Otros

El seeder identifica cada registro por código o nombre sin distinguir mayúsculas. Si encuentra un registro existente, no modifica su nombre, descripción, estado ni creador. De esta forma, sucesivas ejecuciones agregan únicamente tipos institucionales ausentes y conservan personalizaciones administrativas.

Se ejecutó dos veces sobre PostgreSQL sin duplicar registros.

## 5. Autorización

`DocumentTypePolicy` utiliza exclusivamente los permisos existentes:

- `document_types.view`: listado y consulta;
- `document_types.manage`: creación, edición, activación y desactivación.

Administrador y Co-administrador poseen ambos permisos. Los roles que solo poseen `document_types.view` pueden consultar el catálogo, pero reciben `403` al intentar administrarlo. Extra no puede acceder.

Las rutas también están protegidas mediante middleware `permission:*`. No se realizan comparaciones por nombre o ID de rol.

## 6. Validación y operaciones

El backend:

- exige nombre;
- limita el nombre a 120 caracteres;
- normaliza espacios;
- rechaza nombres duplicados sin distinguir mayúsculas o minúsculas;
- admite descripción opcional de hasta 2000 caracteres;
- ignora `code`, `is_active` y `created_by` enviados por el cliente;
- crea los tipos en estado activo;
- registra al usuario creador;
- modifica el estado únicamente mediante acciones dedicadas;
- ejecuta las escrituras dentro de transacciones.

Los índices únicos PostgreSQL son la segunda barrera frente a concurrencia o solicitudes fuera de la interfaz.

## 7. Tipos inactivos

Desactivar un tipo solo modifica `is_active`. El registro y sus futuras relaciones históricas permanecen almacenados.

El modelo expone:

```php
DocumentType::query()->availableForNewDocuments()
```

Este scope devuelve únicamente tipos activos ordenados por nombre y queda preparado para la Fase 8. Todavía no se creó ningún formulario documental.

## 8. Interfaz

La interfaz Blade/Tailwind incluye:

- listado paginado de 15 registros;
- nombre, descripción, estado y acciones;
- creación y edición sencilla;
- vista de detalle;
- confirmación antes de desactivar;
- explicación de que la desactivación no elimina documentos históricos;
- mensajes y errores en español;
- controles de tamaño legible y diseño responsive.

## 9. Rutas

| Método | Ruta | Nombre | Permiso |
|---|---|---|---|
| `GET` | `/administracion/tipos-documentales` | `document-types.index` | `document_types.view` |
| `GET` | `/administracion/tipos-documentales/crear` | `document-types.create` | `document_types.manage` |
| `POST` | `/administracion/tipos-documentales` | `document-types.store` | `document_types.manage` |
| `GET` | `/administracion/tipos-documentales/{documentType}` | `document-types.show` | `document_types.view` |
| `GET` | `/administracion/tipos-documentales/{documentType}/editar` | `document-types.edit` | `document_types.manage` |
| `PUT` | `/administracion/tipos-documentales/{documentType}` | `document-types.update` | `document_types.manage` |
| `PATCH` | `/administracion/tipos-documentales/{documentType}/activar` | `document-types.activate` | `document_types.manage` |
| `PATCH` | `/administracion/tipos-documentales/{documentType}/desactivar` | `document-types.deactivate` | `document_types.manage` |

No existe una ruta `destroy`.

## 10. Pruebas y verificaciones

### Catálogo documental

Resultado: **10 pruebas y 52 aserciones aprobadas**.

### Suite completa

Resultado: **72 pruebas y 368 aserciones aprobadas**.

### Integración PostgreSQL específica

Resultado: **4 pruebas y 11 aserciones aprobadas**.

La integración verifica catálogo inicial, idempotencia sin sobrescritura, unicidad de nombre y código sin distinguir mayúsculas, y exclusión de tipos inactivos.

### Integración PostgreSQL completa

Resultado: **29 pruebas y 80 aserciones aprobadas**.

También se verificaron las ocho rutas, Policy, middleware RBAC, Pint, Composer, Vite y el estado de migraciones.

## 11. Archivos creados

- `app/Models/DocumentType.php`
- `app/Policies/DocumentTypePolicy.php`
- `app/Services/DocumentTypeService.php`
- `app/Http/Controllers/DocumentTypeController.php`
- `app/Http/Requests/StoreDocumentTypeRequest.php`
- `app/Http/Requests/UpdateDocumentTypeRequest.php`
- `database/seeders/DocumentTypeSeeder.php`
- `resources/views/document-types/index.blade.php`
- `resources/views/document-types/create.blade.php`
- `resources/views/document-types/edit.blade.php`
- `resources/views/document-types/show.blade.php`
- `resources/views/document-types/_form.blade.php`
- `tests/Feature/DocumentTypeManagementTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentTypeTest.php`
- `docs/FASE_7_TIPOS_DOCUMENTOS.md`

## 12. Archivos modificados

- `app/Providers/AppServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `routes/web.php`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 13. Límites de la fase

- No se implementó gestión documental.
- No se crearon archivos, destinatarios ni estados documentales.
- No se implementaron historial, versionado, reportes ni notificaciones documentales.
- No se añadió ni modificó ninguna migración.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 8 no fue iniciada.
