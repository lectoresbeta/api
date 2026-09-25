---
screen: Interacciones con una publicación
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-COM-006, FEAT-COM-008, FEAT-COM-030, FEAT-COM-031, FEAT-COM-032]
actors: [User]
updated: 2026-09-22
---

# Interacciones con una publicación

Secuencia de cinco estados que muestra comentar, valorar un comentario y responderle.

## Barra de acciones de la publicación

```text
♡ 5 Me gusta   💬 1 Comentarios   ⟳ 0 Repost   ↗ 0 Compartido
```

Cuatro contadores, cada uno con su icono. En capturas anteriores aparecían con `999`; aquí
con valores reales, lo que confirma que son contadores vivos y no adorno.

## Zona de comentarios

Aparece bajo la barra de acciones, siempre visible, sin necesidad de desplegar.

| Elemento | Contenido |
|---|---|
| Compositor | Avatar del usuario, campo «Añadir un comentario…» y **selector de emoji** |
| Orden | **«Más relevantes ⌄»**, desplegable |
| Lista | Los comentarios, con sus respuestas anidadas |

El selector de emoji inserta emojis **en el texto**; no es un mecanismo de reacción. Se
maneja en cliente y no añade nada al backend más allá de conservarlos (`RN-4` de
`FEAT-COM-002`).

> Esta frase es la que **derogó `FEAT-COM-007`** («Reaccionar con emoji a una publicación»):
> no había reacciones que construir. Ver el registro de funcionalidades.

«Más relevantes» implica una fórmula de relevancia que nadie ha definido. Es el mismo vacío
que bloquea los rankings (`CM-4`) y la ordenación del muro (`FEAT-COM-024`).

## Un comentario

| Elemento | Contenido |
|---|---|
| Cabecera | Avatar, nombre y antigüedad («Hace 2 semanas»), menú «···» |
| Cuerpo | Texto |
| Acciones | **Me gusta** · **`n` Me gusta** · **Responder** · **`n` respuestas** |

«Me gusta» aparece dos veces con papeles distintos: el primero es el **botón**, el segundo el
**contador**. Al pulsarlo, el botón se resalta y el contador sube de 0 a 1.

## La secuencia

| # | Qué ocurre |
|---|---|
| 1 | Publicación con 1 comentario. El comentario tiene «0 Me gusta» y «0 respuestas» |
| 2 | Se pulsa «Me gusta»: el botón se resalta y el contador pasa a «1 Me gusta» |
| 3 | Se pulsa «Responder»: aparece un compositor **bajo el comentario**, ya rellenado con una **mención** a su autor |
| 4 | Se escribe la respuesta; la mención se mantiene destacada al principio del texto |
| 5 | La respuesta queda publicada **anidada bajo el comentario**, con su propio avatar, autor, antigüedad y acciones «Me gusta · Responder» |

## Hallazgo: los comentarios se pueden valorar

Un comentario tiene su propio «Me gusta» con su contador, independiente del de la
publicación. No estaba documentado.

Es mecánicamente idéntico al de la publicación —alternable, uno por usuario— pero sobre otro
objetivo. Ver `FEAT-COM-030`.

> **Ojo con el nombre.** En el contexto `Feedback` ya existe una «valoración positiva» de un
> comentario (`FEAT-FBK-006`) que **otorga créditos**. Esto es otra cosa: un «me gusta»
> social sobre un comentario del muro, sin efecto económico. Comparten palabra y no concepto.

## Hallazgo: hay respuestas anidadas

Las respuestas cuelgan del comentario, con su propio contador («`n` respuestas»).

Una respuesta tiene a su vez «Responder», así que hay que decidir qué pasa al responder a una
respuesta:

| Opción | Resultado |
|---|---|
| **Aplanar a un nivel** | La respuesta se añade al mismo hilo, con una mención a quien se responde |
| Anidar sin límite | Hilos arbitrariamente profundos, difíciles de mostrar y de paginar |

El diseño muestra **un solo nivel** de anidamiento, y la mención automática del paso 3 encaja
exactamente con el aplanado: mencionar es lo que sustituye a la profundidad. Ver `I-2`.

## Hallazgo: hay menciones

Al pulsar «Responder», el compositor se rellena con una **mención al autor del comentario**,
destacada en negrita, y así se conserva en la respuesta publicada.

Tiene más consecuencias de las que parece:

- **La mención se muestra con el nombre** («Juanjo Estévez»), no con el `@usuario`.
- **Hay que guardar la referencia al usuario, no el texto.** Si se guardase «Juanjo Estévez»
  como texto plano, al cambiar esa persona su nombre la mención seguiría mostrando el
  anterior. Peor aún con `@usuario`: los nombres de usuario se reciclan pasados 30 días
  (`decision:0005`), así que una mención guardada como texto podría acabar señalando a otra
  persona.
- Una mención debería **avisar** al mencionado.
- Y **no debe revelar** nada: mencionar a alguien en una publicación que no puede ver no le
  da acceso a ella.

Ver `FEAT-COM-032`.

## Revisión del layout

Estas capturas traen un layout distinto del documentado en
[app-layout-and-navigation.md](app-layout-and-navigation.md):

| | Documentado | En estas capturas |
|---|---|---|
| Menú lateral | 241 px, con etiquetas | **Estrecho**, icono sobre etiqueta |
| Elementos | Inicio, Leer, Escribir, Mensajes, Recursos, Ayuda | Inicio, Leer, Escribir, **Mensaje**, **Avisos**, Recursos, y «?» al pie |
| Notificaciones | Campana en la cabecera | **«Avisos» en el menú lateral** |
| Créditos | Bloque al pie del menú lateral | **En la cabecera**, junto al avatar |
| Búsqueda | Icono de lupa | **Campo de búsqueda visible** en la cabecera |
| Usuario | Solo avatar | Avatar **con el nombre** y desplegable |
| Enlace legal | «Terms & Conditions» al pie del menú | No aparece |
| Insignia | «0 Level» | **«Nivel 5»**, ya en castellano |

**Esto resuelve `L-1`**: la nota de diseño situaba «Avisos» en el menú lateral y el diseño
anterior lo ponía en la cabecera. Aquí está en el menú lateral, como decía la nota.

No se ha reescrito la especificación del layout: **hay dos iteraciones y no consta cuál es la
vigente**. Ver `I-6`.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Comentar una publicación | `FEAT-COM-006` |
| 2 | Ordenar los comentarios por relevancia o fecha | `FEAT-COM-006`, `I-1` |
| 3 | Contador de comentarios por publicación | `FEAT-COM-006` |
| 4 | «Me gusta» sobre un comentario, con su contador | `FEAT-COM-030` — **implementado** |
| 5 | Responder a un comentario, con contador de respuestas | `FEAT-COM-031` |
| 6 | Menciones guardadas como **referencia al usuario** | `FEAT-COM-032` |
| 7 | Aviso al usuario mencionado | `FEAT-COM-032`, `FEAT-NOT-009` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **I-1** | ¿Cómo se calcula «Más relevantes»? ¿Qué otras opciones tiene el desplegable? | Mismo vacío que `CM-4`. Sin fórmula no hay consulta |
| **I-2** | ¿Responder a una respuesta aplana el hilo o lo anida más? | El diseño muestra un solo nivel; la mención automática encaja con aplanar |
| I-3 | ¿Se pueden editar o eliminar los comentarios propios? El menú «···» existe pero no tiene diseño | Ciclo de vida del comentario |
| I-4 | ¿Se paginan los comentarios? La captura solo muestra uno | Con volumen hace falta |
| I-5 | ¿Se puede mencionar a cualquiera escribiendo «@», o solo aparece la mención automática al responder? | Define si hace falta un buscador de usuarios en el compositor |
| **I-6** | ¿Cuál es el layout vigente, el de estas capturas o el anterior? | Afecta a `FEAT-USR-027` y a toda la navegación |
| I-7 | ¿Un comentario admite adjuntos, o solo texto y emojis? | El compositor solo ofrece emoji |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | Tras publicar la respuesta, el comentario sigue diciendo «0 respuestas» | Inconsistencia de maqueta: debería decir «1 respuesta» |
| A-2 | El menú lateral dice «Mensaje» en singular, frente a «Mensajes» antes | Unificar |
| A-3 | La insignia «Nivel 5» sigue presente | Sistema de niveles descartado. **No se implementa** |
| A-4 | La biografía dice «soy Laura» en el perfil de Beatriz Alonso | Texto de maqueta |
