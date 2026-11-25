# Fase 25: actividades de la unidad

## Propósito

La fase incorpora el seguimiento de labores encomendadas dentro de una Carrera o Kardex sin duplicar el flujo de correspondencia. Una actividad puede vincularse a un documento visible para la unidad o existir de manera independiente.

La interfaz utiliza términos administrativos claros: **actividad**, **labor encomendada**, **responsable principal**, **personal de apoyo**, **avance**, **producto esperado**, **resultado presentado**, **observaciones de Jefatura** y **actividad concluida**.

## Alcance implementado

- Jefatura asigna actividades únicamente a personal activo de su propia unidad.
- Cada actividad tiene responsable principal y puede incluir personal de apoyo.
- Tipos iniciales: revisión de correspondencia, redacción de nota/carta/oficio, elaboración de informe, recopilación de antecedentes, verificación de información y actividad interna.
- Correspondencia relacionada opcional, prioridad y fecha límite.
- Registro cronológico de avances y porcentaje estimado opcional.
- Presentación de un resultado textual y vinculación opcional del borrador documental preparado por el responsable.
- Revisión de Jefatura: concluir o devolver con observaciones.
- Cancelación motivada sin eliminación del registro.
- Notificaciones internas y auditoría para las acciones principales.
- Bandeja única que también muestra las revisiones documentales pendientes ya existentes.

## Separación de responsabilidades

La actividad organiza el trabajo; no envía ni aprueba correspondencia. Cuando el producto es una nota, carta u oficio, el responsable prepara el borrador mediante el módulo documental y lo vincula al presentar el resultado. La autorización y el envío oficial continúan sujetos al flujo documental existente.

El detalle del documento solo ofrece **Asignar actividad**. La creación, los avances y la revisión se realizan en páginas propias para evitar sobrecargar la pantalla de correspondencia.

## Estados

`Asignada` → `En ejecución` → `Presentada para revisión` → `Concluida`.

Jefatura puede devolver una presentación como `Con observaciones`; al registrar un nuevo avance vuelve a `En ejecución`. Una actividad abierta también puede quedar `Cancelada` con motivo obligatorio.

## Seguridad

- Jefe de Carrera o Encargado de Kardex: asigna, revisa y cancela dentro de su unidad.
- Secretaria, Auxiliar de Secretaría y Extra: solo ven actividades en las que fueron asignados; registran avances y el responsable principal presenta el resultado.
- Una asignación vinculada concede visibilidad del documento necesario para ejecutar la labor, pero no autoriza decisiones oficiales.
- Las consultas y Policies vuelven a comprobar unidad, rol, estado y participación en cada petición.

## Persistencia

- `actividades_unidad`: definición, vínculo documental, estado, plazo y resultado.
- `responsables_actividades`: responsable principal y personal de apoyo.
- `avances_actividades`: seguimiento cronológico append-only.

La migración actualiza también el catálogo RBAC en instalaciones existentes.

## Validación

`WorkActivityTest` cubre asignación, aislamiento entre unidades, colaboración, avances, presentación de un borrador, devolución con observaciones y conclusión. La regresión focalizada conserva el flujo anterior de revisiones documentales.
