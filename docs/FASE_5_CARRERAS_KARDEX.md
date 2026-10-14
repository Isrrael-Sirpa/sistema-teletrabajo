# Fase 5 — Carreras y Kardex

> **Estado:** implementada y validada el 8 de octubre de 2026.

## 1. Alcance completado

Se implementó la estructura organizacional operativa reutilizando `organizational_units`. La Facultad, las carreras y Kardex continúan siendo variantes de la misma entidad; no se crearon tablas separadas.

La gestión administrativa permite listar, consultar, crear, editar, activar y desactivar carreras. La Facultad y Kardex se muestran como unidades protegidas y no pueden editarse, desactivarse ni eliminarse mediante el CRUD ordinario.

## 2. Ajuste estructural necesario

La tabla aprobada en la Fase 2 contenía tipo, nombre, sigla, descripción y estado, pero no un campo para expresar la jerarquía. La Fase 5 añadió mediante una migración incremental:

- `parent_id` nullable y autorreferenciado;
- clave foránea con `RESTRICT` al eliminar;
- índice de soporte `units_parent_id_index`;
- restricción `units_parent_presence_check`;
- trigger PostgreSQL que exige que toda carrera y Kardex dependan de una Facultad.

No se modificó ninguna migración anterior.

La Facultad es la única unidad raíz y tiene `parent_id = null`. Las carreras y Kardex requieren un padre de tipo `faculty`. Las restricciones originales continúan impidiendo una segunda Facultad o un segundo Kardex; actualmente la restricción es incluso más estricta y permite un solo registro de cada tipo, independientemente de su estado.

## 3. Estructura institucional sembrada

El seeder idempotente crea únicamente:

```text
Facultad de Tecnología (FTEC)
└── Kardex de la Facultad de Tecnología (KARDEX)
```

No se sembraron `Carrera 1` a `Carrera 9`. Los nombres oficiales no fueron proporcionados y se evitó mezclar datos temporales con la estructura institucional. El CRUD permite registrar nueve, diez o cualquier cantidad futura de carreras sin modificar código.

El seeder se ejecutó dos veces en PostgreSQL y permanecieron exactamente dos unidades: una Facultad y un Kardex.

## 4. Modelo y reglas operativas

`OrganizationalUnit` incorpora:

- enum `OrganizationalUnitType` con `faculty`, `career` y `kardex`;
- relaciones `parent` y `children`;
- relación existente `users`;
- scope `careers`;
- scope `active`;
- scope `availableAsDocumentDestination`.

El último scope deja preparada la regla de que una carrera inactiva no aparezca como destino de operaciones futuras. La unidad permanece almacenada y conserva todas sus relaciones históricas.

## 5. Autorización

La autorización no compara nombres de rol. Se implementó `OrganizationalUnitPolicy` con los permisos de la Fase 4:

- `units.view`;
- `units.create`;
- `units.update`;
- `units.activate`;
- `units.deactivate`.

Las rutas también utilizan el middleware oficial `permission:*`. Administrador y Co-administrador acceden porque poseen esos permisos; Extra recibe `403`.

Las acciones `update`, `activate` y `deactivate` requieren además que la unidad sea de tipo `career`. No existe una ruta `destroy`.

## 6. Validación

El backend:

- exige nombre y sigla;
- limita nombre a 160 y sigla a 20 caracteres;
- normaliza espacios del nombre;
- convierte la sigla a mayúsculas;
- valida duplicados de nombre y sigla sin distinguir mayúsculas, incluso con Unicode;
- limita la descripción opcional a 2000 caracteres;
- ignora cualquier `type`, `parent_id` o `is_active` enviado por el cliente;
- asigna siempre tipo `career`, Facultad activa como padre y estado inicial activo;
- conserva tipo y padre durante la edición.

Los índices únicos y restricciones PostgreSQL constituyen la segunda barrera ante concurrencia o acceso fuera de la interfaz.

## 7. Rutas

| Método | Ruta | Nombre | Permiso |
|---|---|---|---|
| `GET` | `/administracion/unidades` | `units.index` | `units.view` |
| `GET` | `/administracion/unidades/crear` | `units.create` | `units.create` |
| `POST` | `/administracion/unidades` | `units.store` | `units.create` |
| `GET` | `/administracion/unidades/{unit}` | `units.show` | `units.view` |
| `GET` | `/administracion/unidades/{unit}/editar` | `units.edit` | `units.update` |
| `PUT` | `/administracion/unidades/{unit}` | `units.update` | `units.update` |
| `PATCH` | `/administracion/unidades/{unit}/activar` | `units.activate` | `units.activate` |
| `PATCH` | `/administracion/unidades/{unit}/desactivar` | `units.deactivate` | `units.deactivate` |

Las URLs utilizan `public_id` UUID mediante el route key del modelo.

## 8. Interfaz

Se creó una interfaz Blade/Tailwind responsive con:

- navegación autenticada;
- tabla con nombre, sigla, tipo, estado y acciones;
- formularios accesibles para crear y editar carreras;
- vista de detalle;
- mensajes y errores en español;
- confirmación antes de activar o desactivar;
- explicación explícita de que desactivar no elimina el historial;
- acciones visibles únicamente cuando Gate las permite.

La protección real permanece en backend mediante middleware y Policy.

## 9. Pruebas

### Suite específica

```bash
php artisan test tests/Feature/OrganizationalUnitManagementTest.php
```

Resultado: 12 pruebas y 52 aserciones aprobadas.

### Suite completa

```bash
php artisan test
```

Resultado: 42 pruebas y 177 aserciones aprobadas.

### Integración PostgreSQL

```bash
php vendor/bin/phpunit --configuration phpunit.postgresql.xml
```

Resultado: 20 pruebas y 57 aserciones aprobadas.

La integración comprueba la Facultad y Kardex únicos, jerarquía, índice único sin distinción de mayúsculas, rechazo de un segundo Kardex activo, rechazo de una segunda Facultad, padre inválido e idempotencia real.

## 10. Archivos creados

- `app/Enums/OrganizationalUnitType.php`
- `app/Policies/OrganizationalUnitPolicy.php`
- `app/Services/OrganizationalUnitService.php`
- `app/Http/Controllers/OrganizationalUnitController.php`
- `app/Http/Requests/StoreCareerRequest.php`
- `app/Http/Requests/UpdateCareerRequest.php`
- `database/migrations/2026_10_08_001300_add_hierarchy_to_organizational_units.php`
- `database/seeders/InstitutionalStructureSeeder.php`
- `resources/views/components/layouts/authenticated.blade.php`
- `resources/views/organizational-units/index.blade.php`
- `resources/views/organizational-units/create.blade.php`
- `resources/views/organizational-units/edit.blade.php`
- `resources/views/organizational-units/show.blade.php`
- `resources/views/organizational-units/_form.blade.php`
- `tests/Feature/OrganizationalUnitManagementTest.php`
- `tests/PostgreSQL/PostgreSqlOrganizationalUnitTest.php`
- `docs/FASE_5_CARRERAS_KARDEX.md`

## 11. Archivos modificados

- `app/Models/OrganizationalUnit.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/dashboard.blade.php`
- `routes/web.php`
- `docs/arquitectura/MODELO_DATOS.md`
- `docs/PLAN_IMPLEMENTACION.md`
- `README.md`

## 12. Límites de la fase

- No se cargaron carreras con nombres no confirmados.
- No se implementó gestión de usuarios ni asignación de Jefes, Secretarias, Auxiliares o Extra.
- No se implementaron documentos, destinatarios, flujo, reportes o auditoría funcional.
- No se implementaron delegaciones temporales.
- No se ejecutaron `migrate:fresh`, `db:wipe` ni operaciones destructivas.
- La Fase 6 no ha comenzado.
