# Plan de implementación

Cada fase termina con migraciones consistentes, autorización backend, rutas verificadas, pruebas mínimas y documentación actualizada. No se considera terminada una pantalla aislada.

| Fase | Alcance | Resultado verificable |
|---:|---|---|
| 0 | Arquitectura, datos, permisos, flujo y riesgos | Documentos de decisión aprobables. |
| 1 | Proyecto base | Laravel/Livewire/Tailwind instalados, configuración institucional, almacenamiento privado y build/tests verdes. |
| 2 | Base de datos inicial | Esquema documental inicial, integridad, índices y pruebas PostgreSQL. **Completada.** |
| 3 | Autenticación | Ingreso/salida, bloqueo de inactivos, rate limiting y preparación para recuperación. **Completada.** |
| 4 | Roles y permisos | RBAC sembrado, middleware, Gate y pruebas de separación. **Completada.** |
| 5 | Carreras y Kardex | CRUD de carreras, único Kardex, activación/desactivación. **Completada.** |
| 6 | Gestión de usuarios | Administración global y por carrera con restricciones. **Completada.** |
| 7 | Tipos documentales | Catálogo administrable y desactivación segura. **Completada.** |
| 8 | Gestión documental básica | Borradores sin código oficial, detalle, listado inicial y edición con bloqueo optimista. **Completada.** |
| 9 | Destinatarios | Unidad, persona, múltiples destinos y copias informativas. **Completada.** |
| 10 | Archivos | Carga privada, validación, descarga autorizada y hashes. **Completada.** |
| 11 | Estados | Recepción, revisión y decisiones transaccionales. **Completada.** |
| 12 | Historial | Línea de tiempo append-only y eventos completos. **Completada.** |
| 13 | Versionado | Corrección tras devolución sin sobrescritura. **Completada.** |
| 14 | Notificaciones | Centro interno, lectura y acceso contextual. **Completada.** |
| 15 | Fechas límite y recordatorios | Cálculo separado del estado, filtros, avisos internos idempotentes y Scheduler. **Completada.** |
| 16 | Dashboard operativo | Panel por rol, trabajo prioritario, conteos accionables y filtros enlazables. **Completada.** |
| 17 | Accesos explícitos | Concesiones view/download, vigencia, revocación, ámbito y concurrencia. **Completada.** |
| 18 | Auditoría | Registro transversal seguro, sanitización y consulta global. **Completada.** |
| 19 | Delegaciones | Vigencia automática, revocación, capacidades temporales y concurrencia PostgreSQL. **Completada.** |
| 20 | Búsqueda | Filtros combinables, paginación e índices. **Completada.** |
| 21 | Reportes | Reportes por ámbito y exportaciones justificadas. **Completada.** |
| 22 | Anulación documental | Anulación formal irreversible, historial, auditoría, notificaciones y concurrencia. **Completada.** |
| 23 | Producción y seguridad | Linux, Nginx, PHP-FPM, PostgreSQL, HTTPS, headers, backups, restauración y checklist. **Completada.** |
| 24 | Aceptación y cierre técnico | Flujos integrales, regresión, matriz UAT, checklist de entrega, riesgos y documentación final. **Completada.** |

## Puertas de calidad transversales

- `php artisan route:list` no muestra rutas duplicadas o controladores inexistentes.
- `composer test` pasa.
- `npm run build` pasa.
- Las operaciones críticas tienen una prueba de autorización negativa.
- Las descargas nunca usan rutas públicas.
- Los mensajes de validación visibles están en español.
- Las vistas funcionan con teclado, zoom y pantallas pequeñas.
- Cada migración se valida en PostgreSQL, aunque las pruebas unitarias rápidas usen SQLite en memoria.

## Estado actual

- Fase 0: aprobada y cerrada con decisiones definitivas el 8 de octubre de 2026.
- Fase 1: implementada, probada y documentada; revalidada después del cierre de Fase 0.
- Fase 2: implementada y validada sobre PostgreSQL 18.3 el 8 de octubre de 2026.
- Fase 3: autenticación básica implementada, probada y documentada el 8 de octubre de 2026.
- Fase 4: RBAC implementado, sembrado en PostgreSQL, probado y documentado el 8 de octubre de 2026.
- Fase 5: estructura organizacional, Facultad/Kardex y CRUD de carreras implementados y validados el 8 de octubre de 2026.
- Fase 6: gestión de usuarios global y por carrera implementada, probada y validada en PostgreSQL el 9 de octubre de 2026.
- Fase 7: catálogo de tipos documentales implementado, sembrado y validado en PostgreSQL el 9 de octubre de 2026.
- Fase 8: gestión documental básica en estado borrador implementada, probada y validada en PostgreSQL el 9 de octubre de 2026.
- Fase 9: destinatarios de unidad y persona preparados en borradores, con copias informativas y concurrencia, implementados y validados en PostgreSQL el 10 de octubre de 2026.
- Fase 10: archivos privados de versión inicial, validación de contenido, integridad SHA-256, compensación de filesystem y descarga autorizada implementados y validados en PostgreSQL el 10 de octubre de 2026.
- Fase 11: envío oficial, códigos correlativos, recepción explícita, revisión, decisiones, estado agregado y concurrencia real implementados y validados en PostgreSQL el 10 de octubre de 2026.
- Fase 12: línea de tiempo documental append-only, eventos transaccionales, descartes lógicos y paginación implementados y validados en PostgreSQL el 10 de octubre de 2026.
- Fase 13: versiones posteriores, corrección separada del reenvío, inmutabilidad histórica y concurrencia PostgreSQL implementadas y validadas el 10 de octubre de 2026.
- Fase 14: notificaciones `database`, destinatarios por participación real, despacho after-commit, confidencialidad, centro de lectura y paginación implementados y validados en PostgreSQL el 10 de octubre de 2026.
- Fase 15: fechas límite, condición vigente/próxima/vencida, filtros, recordatorios internos idempotentes, ejecución diaria y concurrencia PostgreSQL implementados y validados el 11 de octubre de 2026.
- Fase 16: dashboard operativo por rol, filtros accionables, priorización, concesiones explícitas de consulta y rendimiento acotado implementados y validados en PostgreSQL el 11 de octubre de 2026.
- Fase 17: concesiones explícitas `view`/`download`, vigencia, revocación histórica, ámbito y concurrencia implementadas y validadas en PostgreSQL el 11 de octubre de 2026.
- Fase 18: auditoría transversal append-only, sanitización central, integración transaccional, descargas privadas y consulta global de solo lectura implementadas y validadas en PostgreSQL el 12 de octubre de 2026.
- Fase 19: delegaciones temporales de Jefatura, capacidades explícitas, vigencia automática, revocación, auditoría y exclusión concurrente implementadas y validadas en PostgreSQL el 12 de octubre de 2026.
- Fase 20: búsqueda avanzada sobre el ámbito documental autorizado, filtros combinables, paginación, orden seguro y revisión de índices implementados y validados en PostgreSQL el 12 de octubre de 2026.
- Fase 21: reportes administrativos por ámbito, agregaciones SQL, filtros reutilizados y exportación CSV limitada, segura y auditada implementados y validados en PostgreSQL el 12 de octubre de 2026.
- Fase 22: anulación formal de documentos oficiales, conservación íntegra del expediente, historial, auditoría, notificaciones internas y concurrencia real implementadas y validadas en PostgreSQL el 12 de octubre de 2026.
- Fase 23: endurecimiento de configuración, headers, errores seguros, despliegue reproducible, backups y restauración implementados, probados y documentados el 12 de octubre de 2026.
- Fase 24: aceptación automatizada integral, regresión final, checklist de entrega y cierre técnico completados y documentados el 13 de octubre de 2026. La UAT con personal real y el despliegue definitivo permanecen como actividades operativas posteriores, no como nuevos módulos.
- Fase 25: actividades de la unidad implementadas el 19 de noviembre de 2026. Amplía las revisiones internas con actividades independientes o vinculadas a correspondencia, responsables, avances, productos, observaciones y conclusión por Jefatura.

