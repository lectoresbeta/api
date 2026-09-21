# Especificaciones de interfaz (Figma)

Aquí se documenta lo que cada pantalla de Figma revela sobre el comportamiento de la
plataforma. **Este backend no implementa la interfaz**, pero el diseño es la fuente más
precisa de qué funcionalidades existen y cómo se comportan.

> Estado: vacío. Se irá completando a medida que se incorporen las páginas de Figma.

## Para qué sirve esta carpeta

Una pantalla de Figma contiene información que no está en ningún otro sitio: qué datos se
muestran juntos, qué acciones existen, qué estados tiene cada elemento, qué se valida y qué
se le dice al usuario cuando algo falla.

Traducir eso a requisitos de backend es el trabajo que se documenta aquí.

## Flujo de trabajo al incorporar una página

1. **Leer la pantalla** e identificar todo lo que implique backend: datos mostrados,
   acciones disponibles, estados, validaciones, mensajes de error, paginación, permisos.
2. **Crear el documento de pantalla** a partir de
   [`../_templates/ui-screen.md`](../_templates/ui-screen.md).
3. **Contrastar con el registro maestro**:
   - ¿Existe ya la funcionalidad? Se enlaza y se detalla su ficha.
   - ¿Es nueva? Se añade al registro con un `FEAT-` nuevo.
   - ¿Contradice lo documentado? **Se marca la contradicción de forma explícita** y se
     resuelve antes de seguir. Una contradicción silenciada se convierte en un bug.
4. **Detallar las fichas** afectadas: reglas de negocio, flujos, errores, criterios de
   aceptación.
5. **Definir el contrato de API** que la pantalla necesita, en `api/endpoints/` y `openapi/`.
6. **Actualizar el estado** de la ficha, normalmente de `PENDING` a `DRAFT` o `REVIEW`.

## Qué mirar en una pantalla

| Aspecto | Qué revela |
|---|---|
| Datos mostrados | Qué debe devolver el endpoint, y qué **no** debe devolver |
| Acciones y botones | Qué operaciones existen y cuándo están disponibles |
| Estados vacíos | Qué ocurre sin datos, y si es un caso de negocio distinto |
| Estados de carga | Si la operación es asíncrona |
| Mensajes de error | Qué validaciones y reglas de negocio existen |
| Listas | Paginación, filtros, ordenación y su valor por defecto |
| Elementos deshabilitados | Reglas de autorización o de estado |
| Contadores y agregados | Read models o campos calculados |
| Diferencias entre roles | Reglas de autorización |
| Formularios | Campos obligatorios, formatos, límites |

La pregunta más productiva ante cualquier pantalla: **¿qué tiene que ser cierto en el
backend para que esto se pueda dibujar?**

## Convenciones

- Un documento por pantalla o por flujo coherente.
- Nombre en inglés y `kebab-case`: `work-editor.md`, `beta-reader-requests.md`.
- Cada documento enlaza las funcionalidades que cubre, y cada ficha enlaza de vuelta.
- Se anotan siempre las **preguntas que la pantalla no responde**. Son tan valiosas como
  lo que sí resuelve.
