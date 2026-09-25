---
screen: Leer un capítulo y corregirlo
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-WRK-004, FEAT-FBK-003, FEAT-FBK-011, FEAT-FBK-012, FEAT-WRK-014, FEAT-COM-036, FEAT-CRD-016]
actors: [User, BetaReader]
updated: 2026-09-22
---

# Leer un capítulo y corregirlo

La pantalla central del producto: donde se lee una obra y donde se produce el feedback que
mueve la economía de créditos.

## Estructura

```text
‹ [portada] Los guardianes del desierto          (avatar) Pedro Martinez
                                                                        ┌──────────┐
                     Título de capítulo 1                               │ Empezar  │
         Lecturas 68K  ·  Likes 327K  ·  Comentarios 34                 │corrección│
                  3 min lectura   [8 Créditos]                          └──────────┘
                                                                          (pestaña
         …texto del capítulo…                                              flotante)

         ← Anterior capítulo            Siguiente capítulo →
         ─────────────────────────────────────────────────
         ↗ Comparte        ♡ Like        💬 Comenta
         [ Añadir un comentario…                        ]
         Más relevantes ⌄
         …comentarios y respuestas…

  También te puede interesar
  [ tarjeta ] [ tarjeta ]                        Explorar más relatos →
```

## Cabecera

Migas de pan con la **obra** —portada y título— y el **autor** con su avatar. Permite volver
a la obra y llegar al perfil de quien la escribió sin salir de la lectura.

## Metadatos del capítulo

| Dato | Ejemplo |
|---|---|
| Título del capítulo | «Título de capítulo 1» |
| Lecturas | 68K |
| Likes | 327K |
| Comentarios | 34 |
| Tiempo de lectura | «3 min lectura» |
| Créditos | «8 Créditos» |

Las métricas son **del capítulo**, no de la obra. Eso implica agregados por capítulo, no solo
por obra (`R-1`).

## Navegación entre capítulos

**«← Anterior capítulo»** y **«Siguiente capítulo →»**. La lectura es secuencial dentro de la
obra, lo que confirma que `Chapter` tiene orden.

## Interacciones sociales: Comparte · Like · Comenta

Bajo el texto, la misma barra que una publicación del muro, con su compositor de comentarios,
su «Más relevantes» y sus respuestas anidadas.

> ### Comentar un capítulo **no es** corregirlo
>
> Es la distinción más importante de esta pantalla, y la más fácil de perder de vista.
>
> | | Comentario | Corrección |
> |---|---|---|
> | Dónde | Bajo el texto, abierto | Panel «Empezar corrección» |
> | Qué es | Reacción social, libre | Respuesta al cuestionario del autor |
> | Créditos | **Ninguno** | **Los mueve: cuesta al autor y recompensa al lector** |
> | Cuántas caben | Las que sean | **Una por lector y capítulo** (`R-2`) |
> | Modelo | `PostComment` o equivalente | `Feedback` |
> | Quién | Cualquiera que pueda leer | Quien tenga acceso de lector beta |
>
> Hasta ahora la documentación asumía que el comentario sobre una obra **era** el feedback.
> No lo es: son dos cosas que conviven en la misma pantalla.
>
> Esto aclara `D-1` y `F-1`: el feedback **no** es un comentario suelto sobre un fragmento,
> es el cuestionario respondido.

## «Empezar corrección»

Pestaña flotante fijada al borde derecho, siempre visible mientras se lee. Abre el panel del
cuestionario.

Que esté anclada y no al final del texto es deliberado: la corrección es la acción que el
producto quiere fomentar.

## El panel de corrección

| Elemento | Contenido |
|---|---|
| Título | «Responde y envía el cuestionario creado por el autor» |
| Preguntas | Cada una con su área de texto y un **ejemplo** como marcador |
| Contador | **`0 / 100`** por respuesta, **en palabras** (`R-5`, resuelta) |
| Acciones | **«Enviar»** (deshabilitado hasta que haya contenido) y **«Guardar»** |

Preguntas del ejemplo:

1. ¿Qué impresión general te dejó la historia?
2. ¿El ritmo de la narrativa te pareció adecuado?
3. ¿Cómo describirías la conexión entre el protagonista, Ryn, y su misión como Guardián del Horizonte?
4. ¿Qué te pareció el final de la historia?
5. Si tienes algún comentario adicional o sugerencias, por favor compártelos aquí:

La tercera menciona al personaje por su nombre: **las preguntas las escribe el autor** para su
obra concreta, no salen de una plantilla fija.

La cuarta —«¿Qué te pareció el final de la historia?»— es de **obra entera**, y la corrección
es **por capítulo** (`R-2`). Preguntada en el capítulo 1, no tiene respuesta. Ver `W-17` en
[`FEAT-WRK-014`](../features/work/FEAT-WRK-014-configure-questionnaire.md).

### «Guardar» es un borrador

Dos botones con significados distintos:

- **Enviar** cierra la corrección: se entrega al autor y **mueven los créditos**.
- **Guardar** conserva lo escrito sin entregarlo, para seguir después.

Un borrador de corrección **no cuesta ni recompensa nada**: hasta que no se envía, no ha
ocurrido el hecho de negocio. Ver `FEAT-FBK-011`.

### El cuestionario determina los créditos

Lo confirma producto: **el autor configura el formulario, y esa configuración decide cuánto le
cuesta recibir una respuesta y cuánto gana el lector que la da.** Puede ser desde un único
campo libre hasta varias preguntas concretas.

Es una refinación importante del modelo de créditos:

| | Modelo anterior | Decidido |
|---|---|---|
| Factores | Tramos por extensión + recargo por preguntas | **`techo(palabras/1.000) + techo(palabras exigidas/100)`** |
| Unidad | La obra | **El capítulo** |
| Coste y recompensa | Dos cifras | **La misma cifra**: una corrección mueve créditos, no los crea |
| Rango | — | Entre 2 y 20 |

Las «palabras exigidas» son la suma de los mínimos que el autor fija en sus preguntas: un solo
número que captura toda la exigencia del cuestionario.

El modelo completo está en [`decision:0006`](../decisions/0006-credit-system.md).


## «También te puede interesar»

Dos tarjetas de obra al pie, más «Explorar más relatos →». Misma tarjeta y previsiblemente el
mismo motor que las recomendaciones de la Home (`FEAT-COM-017`).

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Servir el contenido de un capítulo a quien tenga derecho | `FEAT-WRK-004` |
| 2 | Navegación entre capítulos, con orden | `FEAT-WRK-004` |
| 3 | Métricas por capítulo: lecturas, likes y comentarios | `FEAT-COM-036`, `R-1` |
| 4 | Registrar una lectura | `H-3` |
| 5 | Like, comentario y compartido **sobre un capítulo** | `FEAT-COM-036` |
| 6 | Servir el cuestionario configurado por el autor | `FEAT-WRK-014` |
| 7 | Guardar un borrador de corrección | `FEAT-FBK-011` |
| 8 | Enviar la corrección y disparar los créditos | `FEAT-FBK-003` |
| 9 | Calcular coste y recompensa a partir del cuestionario | `FEAT-CRD-016` |
| 10 | Recomendaciones relacionadas | `FEAT-COM-017` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-12 | ¿Se avisa al autor de que alguien ha empezado a corregirle? | Le permitiría reponer saldo y evitar que la corrección llegue bloqueada |
| **W-17** | ¿Se repiten en cada capítulo preguntas que hablan de «la historia» o «el final»? | El autor pagaría por preguntas sin respuesta posible |
| **R-10** | ¿Hay tope de correcciones por obra? | Trocear una novela en cuarenta capítulos multiplica el coste por cuarenta |
| R-4 | ¿Quién puede ver «Empezar corrección»? | **Resuelta:** en obra `PUBLIC`, cualquiera; empezar concede el acceso. `ON_REQUEST` y `PRIVATE` exigen permiso previo |
| R-6 | ¿Se puede leer el capítulo sin acceso de lector beta? | La pantalla no distingue |
| R-8 | ¿Caduca un borrador de corrección? | Con un borrador por capítulo, el saldo inmovilizado se multiplica |
| H-3 | ¿Qué cuenta como «lectura»? | Tercera vez que aparece sin definir |
| R-11 | ¿Las métricas por capítulo se agregan también a nivel de obra? | La tarjeta del catálogo muestra cifras de obra |

Resueltas: **`R-2`** (la corrección es **por capítulo**), **`R-5`** (el contador es en
**palabras**), `R-3` (los factores son longitud y cuestionario; la fórmula se define después),
`R-7` (un borrador por capítulo) y **`R-9`**: los comentarios de un capítulo son un tipo
aparte, `ChapterComment`, y viven en `Community`
([`FEAT-COM-036`](../features/community/FEAT-COM-036-chapter-interactions.md)). No son
`PostComment` porque heredan audiencias distintas —una publicación frente a la regla de
lectura de una obra— y dos caminos de autorización en una entidad acaban aplicando el que no
toca.

**`R-1` queda parcialmente resuelta**: los contadores de apoyos y comentarios son por capítulo
y están implementados; el de lecturas sigue esperando a `H-3`.

**`R-1` es la que hereda el peso de `R-2`.** El acceso de lector beta se concede por obra,
pero ahora el gasto ocurre capítulo a capítulo: las dos granularidades han dejado de
coincidir y la reserva previa de
[`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md) ya no cubre
automáticamente lo que se va a gastar.

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | La miga de pan dice «Los guardianes del desierto» y el texto habla de «Guardianes del Horizonte» | Datos de maqueta |
| A-2 | «Pedro Martinez» sin tilde en la cabecera, con tilde en otras pantallas | Unificar |
| A-3 | 327K likes sobre 68K lecturas: más likes que lecturas | Datos de maqueta |
| A-4 | El contador `0 / 100` | **Resuelta:** son palabras, no caracteres |
