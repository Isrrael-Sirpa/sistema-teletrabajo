# Fase 10: archivos adjuntos

Actualización del 19/10/2026: la misma ruta privada admite visualización inline autorizada de PDF, JPEG y PNG. Los grants de ficha no conceden acceso al contenido. Véase [Autoridad formal y visor privado](AUTORIDAD_Y_VISOR_PRIVADO.md) para la seguridad, experiencia y pruebas del visor añadido posteriormente.

## Resultado

La gestión inicial de archivos documentales quedó implementada sobre las tablas `document_versions` y `document_files` creadas en la Fase 2. Los archivos pertenecen a la versión inicial `1`, se almacenan fuera del directorio público y solo pueden descargarse mediante una ruta autenticada y autorizada.

Esta fase no envía documentos, no crea códigos oficiales, no genera versiones posteriores y no inicia el flujo de estados.

## Reglas funcionales

- Solo se pueden adjuntar o retirar archivos mientras el documento está en `draft`.
- Secretaria puede gestionar archivos únicamente en sus borradores propios y en su carrera.
- Administrador y Co-administrador pueden gestionar borradores bajo su ámbito global.
- La descarga exige el permiso `documents.download` y autorización de lectura sobre el documento padre.
- Jefe puede descargar documentos visibles de su carrera, pero no modificar archivos de un borrador ajeno.
- Auxiliar, Extra y usuarios sin ámbito documental no obtienen acceso implícito.
- Todos los archivos iniciales de un documento comparten `version_number = 1`.
- No queda una versión vacía: si se retira el último archivo, también se elimina el contenedor preliminar de versión.
- Un documento enviado conserva sus archivos de forma inmutable; continúa siendo descargable por participantes autorizados.

## Almacenamiento y metadatos

El disco configurable `documents` usa `storage/app/documents`, visibilidad privada y `serve = false`. No existe enlace público ni se entrega `path` al navegador.

La ruta interna sigue este esquema:

```text
{uuid-publico-documento}/version-1/{uuid-interno}.{extension}
```

Por archivo se conserva:

- nombre original;
- nombre interno aleatorio;
- extensión normalizada;
- MIME detectado en el servidor;
- tamaño exacto en bytes;
- SHA-256 calculado desde el archivo ya almacenado;
- usuario que cargó;
- fecha de carga;
- disco y ruta privados.

El nombre original nunca determina la ruta física.

## Validación de seguridad

Formatos habilitados: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG/JPEG, PNG y ZIP.

La aplicación valida antes de guardar:

- cantidad entre 1 y `DOCUMENT_MAX_FILES_PER_REQUEST` (10 por defecto);
- límite por archivo de `DOCUMENT_MAX_FILE_SIZE_KB` (20 MB por defecto);
- nombre original sin separadores de ruta, controles ni longitud excesiva;
- extensión permitida;
- MIME real obtenido mediante Fileinfo y compatible con la extensión;
- cabecera PDF;
- estructura de imagen mediante `getimagesize`;
- firma OLE en documentos Office antiguos;
- consistencia ZIP y entradas mínimas en DOCX, XLSX y PPTX.

Los ZIP se verifican, pero nunca se extraen. `application/octet-stream` no se acepta como sustituto de una detección válida.

## Integridad, transacciones y concurrencia

La carga valida primero el lote completo. Luego una transacción incrementa `documents.lock_version`, obtiene o crea la versión 1 y persiste los metadatos. La restricción única `(document_id, version_number)` protege además la unicidad en PostgreSQL.

Si falla la persistencia después de escribir un archivo, el servicio elimina todos los archivos físicos creados por esa operación y PostgreSQL revierte la versión, los metadatos y `lock_version`.

Para retirar un archivo, el servicio:

1. bloquea su fila dentro de la transacción;
2. valida pertenencia al documento y `lock_version`;
3. mueve temporalmente el archivo a una ruta privada de compensación;
4. elimina el metadato y, si corresponde, la versión vacía;
5. elimina la copia temporal después del commit.

Si la base falla, el archivo se restaura a su ruta original. Las cargas y retiros comparten el mismo bloqueo optimista que la edición del borrador y los destinatarios, por lo que una pestaña obsoleta no puede sobrescribir cambios recientes.

## Interfaz y rutas

En el detalle del documento se presenta una sección responsive de archivos con nombre original, formato, tamaño, autor, versión y acciones permitidas.

| Método | Ruta | Uso |
|---|---|---|
| `POST` | `/documentos/{document}/archivos` | Carga múltiple privada. |
| `GET` | `/documentos/{document}/archivos/{documentFile}/descargar` | Descarga autorizada. |
| `DELETE` | `/documentos/{document}/archivos/{documentFile}` | Retiro desde borrador. |

Los documentos y archivos usan UUID públicos en estas rutas.

## Configuración del servidor

Valores validados en el PHP utilizado por el proyecto:

```ini
upload_max_filesize = 20M
post_max_size = 210M
max_file_uploads = 20
```

Fileinfo, ZIP y GD están habilitados. `post_max_size` admite el lote configurable de 10 archivos de 20 MB más el margen del formulario. Después de cambiar `php.ini`, debe reiniciarse el servidor PHP que atienda la aplicación.

Variables de aplicación:

```dotenv
DOCUMENTS_DISK=documents
DOCUMENT_MAX_FILE_SIZE_KB=20480
DOCUMENT_MAX_FILES_PER_REQUEST=10
```

En producción, los límites del servidor web y PHP-FPM deben ser iguales o superiores a estos valores.

## Pruebas

Las pruebas funcionales cubren PDF, DOCX, XLSX, PNG y ZIP reales; carga múltiple; metadatos y hash; rutas internas aleatorias; rechazo de extensión, MIME y tamaño; autorización positiva y negativa; descarga; cruce entre documentos; retiro; inmutabilidad después del envío; reutilización de versión 1; concurrencia obsoleta y compensación ante fallo de base de datos.

La integración PostgreSQL valida el servicio real, constraints de número de versión, tamaño y hash, unicidad de versión inicial, unicidad de ruta y bloqueo optimista.

## Archivos principales

- `app/Services/DocumentFileService.php`
- `app/Http/Controllers/DocumentFileController.php`
- `app/Http/Requests/StoreDocumentFileRequest.php`
- `app/Http/Requests/DestroyDocumentFileRequest.php`
- `app/Models/DocumentVersion.php`
- `app/Models/DocumentFile.php`
- `resources/views/documents/show.blade.php`
- `tests/Feature/DocumentFileManagementTest.php`
- `tests/PostgreSQL/PostgreSqlDocumentFileTest.php`

## Fuera de alcance

No se implementaron envío, recepción, revisión, decisiones, auditoría completa, notificaciones, versión 2, firma digital, previsualización pública ni almacenamiento en nube. La Fase 11 no fue iniciada.
