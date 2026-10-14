# Mejoras futuras

Estas capacidades quedan expresamente fuera del cierre técnico actual. Deben pasar por análisis, aprobación, diseño de amenazas, migración incremental y pruebas propias antes de implementarse.

| Mejora | Valor potencial | Consideraciones previas |
|---|---|---|
| Firma digital | Autenticidad y no repudio de documentos | Normativa boliviana, proveedor, certificados, sellado de tiempo y preservación |
| Correo institucional | Avisos fuera del sistema | Plantillas sin contenido restricted, rebotes, SPF/DKIM/DMARC, colas y preferencias |
| S3 / MinIO / R2 | Almacenamiento distribuido y escalable | Cifrado, URLs firmadas, residencia, versionado, backup y costos de salida |
| Antivirus | Detección de malware en adjuntos | Cuarentena, actualización de firmas, límites, falsos positivos y cola asíncrona |
| OCR | Digitalización y metadatos extraídos | Privacidad, calidad, idiomas, costo y revisión humana |
| Búsqueda dentro de archivos | Recuperación por contenido | Indexación autorizada, restricted, OCR, cifrado y borrado lógico |
| Exportaciones XLSX/PDF | Presentaciones formales de reportes | Plantillas, paginación, fórmulas, consumo de memoria y auditoría |
| SIEM | Correlación y alertas de seguridad | Sanitización, retención, canales seguros, responsables y respuesta a incidentes |
| Redis y queues | Procesamiento asíncrono de correo, antivirus u OCR | Worker supervisado, reintentos, idempotencia, dead-letter y observabilidad |
| Alta disponibilidad | Menor interrupción y recuperación regional | Balanceo, sesiones/cache compartidos, PostgreSQL HA, storage compartido, RTO/RPO y simulacros |

No constituyen bugs ni bloqueos del alcance aprobado. El sistema actual usa almacenamiento privado local respaldado, notificaciones internas, búsqueda por metadatos, CSV y ejecución síncrona deliberadamente.
