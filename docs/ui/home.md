---
screen: Home
figma: https://www.figma.com/design/egiA5ltTOBRXqwerrqC1jW/WebApp_LectoresBeta?node-id=1800-14717
features: [FEAT-COM-017, FEAT-COM-018, FEAT-COM-019, FEAT-COM-020, FEAT-COM-021, FEAT-COM-022, FEAT-COM-023, FEAT-COM-024, FEAT-USR-026, FEAT-CRD-013, FEAT-CRD-014, FEAT-CRD-015]
actors: [User]
updated: 2026-09-22
---

# Home

Secciones de Figma: **🟢 Home_Tour** (`1800:14717`) y **🟠 Home - Empty State (No sigue
autores)** (`1820:15883`).

El layout que la envuelve está en [app-layout-and-navigation.md](app-layout-and-navigation.md).

## Propósito

Es la pantalla a la que llega el usuario al terminar el onboarding y la que ve cada vez que
entra. Tiene dos trabajos distintos apilados:

1. **Darle algo que leer** — recomendaciones de obras según sus géneros.
2. **Mostrarle la comunidad** — el muro de publicaciones.

## Estructura

```text
Buenos días, {Nombre} 👋
Aquí tienes algunas recomendaciones seleccionadas expresamente para ti.

  ┌──── carrusel de obras recomendadas ────┐
  │ portada │ título, métricas, sinopsis,  │  ‹ ›
  │         │ géneros, min lectura, créd.  │
  └────────────────────────────────────────┘
                     «Explorar más relatos →»
─────────────────────────────────────────────
¿Qué está pasando en Lectores Beta?
Aquí tienes algunas recomendaciones seleccionadas expresamente para ti.

  [ ¿Qué estás pensando?                    Publicar ]
  ┌──── publicación ────┐
  │ autor · 1 día   ··· │
  │ texto / imagen /    │
  │ artículo            │
  │ ♡ 999  💬 999  ⟳ 999  ↗ 999 │
  └─────────────────────┘
  ┌──── si no sigue a nadie ────┐
  │ Todavía no sigues a ningún  │
  │ autor + sugerencias         │
  └─────────────────────────────┘
```

## 1. Saludo

«**Buenos días, Beatriz** 👋» más el subtítulo «Aquí tienes algunas recomendaciones
seleccionadas expresamente para ti.»

Dos observaciones:

- El saludo **depende de la hora** («Buenos días»). Es cálculo de cliente: el backend no
  conoce la zona horaria del usuario y no debería adivinarla.
- Usa el **nombre público** (`FEAT-USR-022`), coherente con que sea el referente de
  identificación de la plataforma.

> El mismo subtítulo se repite bajo «¿Qué está pasando en Lectores Beta?», donde no
> encaja: ahí no hay recomendaciones sino actividad de la comunidad. Ver `H-6`.

## 2. Carrusel de obras recomendadas

Tarjeta horizontal con portada a la izquierda y datos a la derecha.

| Dato | Ejemplo | Origen |
|---|---|---|
| Portada | imagen | `Work` |
| Título | «El secreto de Teresa» | `Work` |
| Fragmentos | `1 / 1` | `Work` — leídos / totales, ver `H-2` |
| Me gusta | `0` | Agregado |
| Vistas | `2` | Agregado. **Concepto nuevo** (`H-3`) |
| Sinopsis | texto largo truncado | `Work` |
| Géneros | `YoungAdult` `Ficción` | `Genre` |
| Tiempo de lectura | «8 min lectura» | Derivado del número de palabras |
| Créditos | «6 Créditos» | `Credits` — ver el aviso de abajo |

Navegación: flechas ‹ › y enlace «Explorar más relatos →» (en otra variante, «Explorar todas
las categorías»).

Flujos que la nota de diseño asocia a esta sección: *«Leer relato sugerido / Solicitar otro
relato / Acceder a sección "Leer"»*. **«Solicitar otro relato» no tiene equivalente en la
documentación**: implica poder descartar una sugerencia y pedir otra (`H-4`).

> ### Aviso sobre la insignia de créditos
>
> Las cifras del diseño **no encajan con la tabla de `credit-system.pdf`**:
>
> | Obra | Tiempo | Palabras aprox. | `TextTier` | Tabla | Diseño |
> |---|---|---|---|---|---|
> | El secreto de Teresa | 8 min | ~1.600–2.000 | `SHORT_STORY` | 15 | **6** |
> | Slush, daiquiris, pizza | 10 min | ~2.000–2.500 | `SHORT_STORY` | 15 | **8** |
>
> Además la insignia es ambigua: en una tarjeta dirigida al **lector** puede significar «lo
> que ganas comentando esta obra» o «lo que cuesta». Según el propio tour, *«Gana créditos
> comentando obras de otros autores»*, lo que apunta a lo primero.
>
> Las dos preguntas están abiertas en `FEAT-CRD-013` (`H-1`). No se ha elegido
> interpretación: afecta al motor de créditos entero.

## 3. Muro

### Caja de publicación

Avatar, campo «¿Qué estás pensando?» y botón «Publicar».

La nota de diseño precisa los **formatos** de publicación: *«Solo texto, Texto+imagen,
Artículo (link)»*. Es una dimensión **distinta** del tipo de publicación que ya estaba
documentado (buscar LB, buscar writing buddy, ofrecerse como LB). Ver la sección
[Dos dimensiones de una publicación](#dos-dimensiones-de-una-publicación).

### Tarjeta de publicación

| Zona | Contenido |
|---|---|
| Cabecera | Avatar, nombre del autor, antigüedad («1 día»), menú «···» |
| Cuerpo | Texto, y según formato: imagen, o tarjeta de artículo con título y descripción |
| Acciones | ♡ Me gusta · 💬 Comentarios · ⟳ **Repost** · ↗ **Compartido**, cada una con su contador |

**Repost y Compartir son interacciones nuevas**: la documentación previa solo contemplaba
comentar, reaccionar con emoji y dar «me gusta».

> El diseño muestra **«Me gusta» con corazón, y ninguna reacción con emoji**. El documento
> de casos de uso las listaba como dos mecanismos distintos (`CM-1`). O el diseño las ha
> fusionado, o las reacciones están pendientes de diseñar. Ver `H-5`.

### Menú «···» de la publicación

Según la nota de diseño: *«Ocultar post, Dejar de seguir al autor del post, Denunciar,
Guardar»*. Cuatro acciones, tres de ellas nuevas:

| Acción | Funcionalidad |
|---|---|
| Ocultar post | `FEAT-COM-022` — **nueva** |
| Dejar de seguir al autor | `FEAT-COM-010`, ya documentada |
| Denunciar | `FEAT-COM-023` — **nueva** |
| Guardar | `FEAT-COM-021` — **nueva** |

### Orden y filtrado

La nota pide *«Filtrar x tipo de post (buscador?) / Ordenar posts + relevante, +reciente»*.
El filtrado ya estaba en `FEAT-COM-009`; la ordenación es nueva (`FEAT-COM-024`).

«Más relevante» exige una fórmula de relevancia que nadie ha definido (`H-7`). Es el mismo
vacío que bloquea los rankings (`CM-4`).

## 4. Estado vacío: no sigue a ningún autor

Bloque que aparece **dentro del muro** cuando el usuario no sigue a nadie:

| Elemento | Contenido |
|---|---|
| Título | «Todavía no sigues a ningún autor» |
| Subtítulo | «Empieza a seguir a autores de tu interés para ver sus publicaciones» |
| Lista | Tarjetas de autor: avatar, nombre, «2K seguidores \| 20 publicaciones», botón «Seguir» / «Siguiendo» |
| Enlace | «Explorar más perfiles →» |

Es **la misma tarjeta y la misma acción** que el paso 3 del onboarding (`FEAT-COM-016`).
La nota de diseño lo confirma: *«(Se salta en el onboarding de registro la parte de seguir a
autores) → CTA en el "muro social" con sugerencias de autores a los que seguir»*.

Esto cierra el cabo suelto que dejó el onboarding: **si el paso 3 se omite por falta de
autores, o el usuario lo salta, la Home lo recupera**. Backend y contrato deben ser los
mismos que los de `FEAT-COM-016`; solo cambia dónde se pinta.

El muro no está vacío aunque no siga a nadie: sigue mostrando publicaciones de la propia
plataforma (la cuenta «Lectores Beta»), que es
[`FEAT-COM-038`](../features/community/FEAT-COM-038-platform-account.md). Con ella y con el
carrusel de [`FEAT-COM-017`](../features/community/FEAT-COM-017-home-work-recommendations.md),
`H-8` queda respondida: quien no sigue a nadie ve obras que puede corregir, autores que puede
seguir y lo que diga la plataforma.

## 5. Tour de bienvenida

Cuatro pasos sobre un fondo atenuado, cada uno anclado a un elemento de la interfaz.

| Paso | Ancla | Título | Texto |
|---|---|---|---|
| 1/4 | Avatar de la cabecera | Personaliza tu experiencia | «Edita tu perfil, ajusta tus preferencias y gestiona tu cuenta» |
| 2/4 | «Leer» del menú | Descubre nuevas historias | «Lee obras inéditas y deja tu feedback para ayudar a otros escritores a mejorar. Cada comentario que hagas te permitirá ganar créditos para recibir feedback en tus obras.» |
| 3/4 | «Escribir» del menú | Comparte tus historias | «Publica tus escritos para recibir feedback de otros escritores y mejorar tus obras.» |
| 4/4 | Bloque de créditos | Gana y usa tus créditos | «Los créditos te permiten recibir feedback en tus escritos. Gana créditos comentando obras de otros autores y úsalos para obtener sugerencias sobre tus textos.» |

Cada paso tiene «×» para cerrar y contador `n/4`. El botón es «Siguiente» salvo el último,
que es «Entendido».

Backend mínimo: **recordar si el usuario ya lo vio o lo cerró**, para no repetirlo en cada
entrada (`FEAT-USR-026`).

> El texto del paso 4 dice «úsalos para obtener sugerencias sobre tus textos». En el modelo
> de créditos documentado, los créditos se gastan **al recibir un comentario**, no al pedir
> sugerencias. Probablemente es una forma laxa de decir lo mismo, pero conviene alinear el
> lenguaje con el del dominio.

## Dos dimensiones de una publicación

El diseño revela que una publicación tiene **dos atributos ortogonales**, no uno:

| Dimensión | Valores | Origen |
|---|---|---|
| **Intención** (`PostType`) | `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` | `use-cases.pdf` |
| **Formato** (`PostFormat`) | `TEXT`, `TEXT_IMAGE`, `ARTICLE_LINK` | Nota de diseño |

Una publicación buscando lectores beta puede ser solo texto o llevar imagen. Mezclar ambas
dimensiones en un único enum produciría una combinatoria que no es la que describe el
producto. Afecta a `FEAT-COM-002` y al filtrado de
[`FEAT-COM-009`](../features/community/FEAT-COM-009-filter-and-search-posts.md), que **filtra
por intención**: es la que dice qué quiere quien publica, que es lo que alguien busca. El
formato dice cómo se pinta, y nadie entra al muro a buscar publicaciones con imagen.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Recomendar obras según los géneros del usuario | `FEAT-COM-017` |
| 2 | Tiempo estimado de lectura por obra | `FEAT-COM-017` |
| 3 | Contadores de fragmentos, me gusta y vistas por obra | `FEAT-COM-017`, `H-3` |
| 4 | Créditos asociados a una obra, con su significado definido | `FEAT-CRD-013` |
| 5 | Descartar una sugerencia y pedir otra | `H-4` |
| 6 | Muro paginado con publicaciones y sus contadores | `FEAT-COM-001` |
| 7 | Formato de publicación además de su intención | `FEAT-COM-002` |
| 8 | Repostear una publicación | `FEAT-COM-019` |
| 9 | Compartir fuera de la plataforma | `FEAT-COM-020` |
| 10 | Guardar, ocultar y denunciar una publicación | `FEAT-COM-021` a `023` |
| 11 | Ordenar el muro por relevancia o fecha | `FEAT-COM-024` |
| 12 | Sugerencias de autores también fuera del onboarding | `FEAT-COM-018` |
| 13 | Recordar si el tour ya se completó o se cerró | `FEAT-USR-026` |
| 14 | Saldo, notificaciones sin leer y contexto de sesión | [layout](app-layout-and-navigation.md) |

## Contradicciones detectadas

| # | Qué dice el diseño | Qué dice la documentación | Resolución |
|---|---|---|---|
| D-1 | «6 Créditos» para un relato de 8 min | La tabla de `credit-system.pdf` daría 15 | **Sin resolver.** Ver `H-1` |
| D-2 | Solo «Me gusta», sin reacciones con emoji | `use-cases.pdf` las lista como mecanismos distintos | **Sin resolver.** Ver `H-5` |
| D-3 | Notificaciones en la campana de la cabecera | La nota del propio Figma las pone en el menú lateral | Ver `L-1` |
| D-4 | «12 Créditos» en el menú lateral | Una cuenta activada nueva tiene 20; una sin activar, 0 | Dato de maqueta |

## Funcionalidades afectadas

| ID | Acción | Estado |
|---|---|---|
| FEAT-COM-017 | **Nueva** — carrusel de obras recomendadas | `DRAFT` |
| FEAT-COM-018 | **Nueva** — sugerencias de autores en el muro | `DRAFT` |
| FEAT-COM-019 | **Nueva** — repostear | `PENDING` |
| FEAT-COM-020 | **Nueva** — compartir fuera de la plataforma | `PENDING` |
| FEAT-COM-021 | **Nueva** — guardar publicación | `PENDING` |
| FEAT-COM-022 | **Nueva** — ocultar publicación | `PENDING` |
| FEAT-COM-023 | **Nueva** — denunciar publicación | `PENDING` |
| FEAT-COM-024 | **Nueva** — ordenar el muro | `PENDING` |
| FEAT-COM-025 | **Nueva** — búsqueda global. **Backlog** según el diseño | `PENDING` |
| FEAT-USR-026 | **Nueva** — tour de bienvenida | `DRAFT` |
| FEAT-CRD-013 | **Nueva** — créditos asociados a una obra | `DRAFT` |
| FEAT-CRD-014 | **Nueva** — modal informativo de créditos | `DRAFT` |
| FEAT-CRD-015 | **Nueva** — pantalla con la tabla de puntuación de créditos | `PENDING` |
| FEAT-NOT-009 | **Nueva** — centro de notificaciones | `PENDING` |
| FEAT-COM-002 | Añade el formato además de la intención | `PENDING` |
| FEAT-COM-016 | Sus sugerencias se reutilizan en el muro | `DRAFT` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **H-1** | **¿Qué significa la insignia de créditos de una obra y por qué no coincide con la tabla?** | **Bloqueante** para `FEAT-CRD-013`. Toca el motor de créditos |
| H-2 | ¿Qué es el contador «1 / 1»: fragmentos leídos sobre totales, o solo totales? | Si es progreso, hace falta seguimiento de lectura, que hoy no existe |
| H-3 | ¿Qué cuenta como «vista» de una obra y quién la registra? | Concepto nuevo, con coste de escritura en cada lectura |
| H-4 | ¿Cómo funciona «Solicitar otro relato»? ¿Se descarta la sugerencia de forma permanente? | Funcionalidad sin documentar |
| H-5 | ¿Conviven «Me gusta» y las reacciones con emoji, o el diseño las ha fusionado? | `CM-1` |
| H-6 | ¿Cuál es el subtítulo real del muro? Hoy repite el del carrusel | Texto de interfaz |
| H-7 | ¿Cómo se calcula «más relevante»? | Mismo vacío que bloquea los rankings (`CM-4`) |
| H-8 | ¿Qué compone el muro de quien no sigue a nadie? | Define si el muro es cronológico por seguidos o algorítmico |
| H-9 | ¿El carrusel excluye obras ya leídas o ya comentadas por el usuario? | Calidad de la recomendación |
| H-10 | ¿La Home es accesible con la cuenta sin activar y cómo se comunica? | `A-2` de `FEAT-USR-025` |
| **M-1** | **¿Los créditos se gastan al poner la obra en corrección o al recibir cada comentario?** | **Bloqueante.** Es `C-1`, y el modal apunta al pago por adelantado |
| M-2 | ¿Qué es exactamente una obra «en corrección»? ¿Es un estado de `Work`? | Vocabulario y ciclo de vida sin documentar |

## 6. Modal informativo de créditos

Se abre desde el icono ⓘ del bloque de créditos del menú lateral. Frame «Info Credits».

| Elemento | Contenido |
|---|---|
| Título | «Gana créditos y úsalos para mejorar tús textos.» |
| Columna izquierda | Icono de libro · **«Obtén créditos.»** · «Consigue créditos leyendo textos de otros usuarios y aportando feedback valioso.» · ilustración · botón **«Ver puntuación de créditos»** |
| Columna derecha | Icono de cartera · **«Usa tús créditos.»** · «Utiliza tus créditos para poner tus obras en corrección y recibir feedback de los demás usuarios.» · ilustración · botón **«Consulta tús movimientos.»** |
| Cierre | «×» arriba a la derecha |

Las dos ilustraciones son marcadores de posición: faltan por producir.

### Lo que revela este modal

**1. Hay una pantalla con la tabla de créditos.** El botón «Ver puntuación de créditos» lleva
a algún sitio donde se consulta cuánto vale cada acción. Eso exige **exponer las reglas de
crédito**, que hasta ahora solo existían en un PDF (`FEAT-CRD-015`).

**2. «Poner tus obras en corrección» no es lo que dice el modelo documentado.**

Es el hallazgo importante de esta pantalla. `credit-system.pdf` establece que los créditos se
descuentan **al recibir cada comentario**. El modal describe otra cosa: que se gastan **al
poner una obra en corrección**, es decir, al ofrecerla para que la comenten.

| Modelo | Cuándo se paga | Consecuencia |
|---|---|---|
| Documentado (`FEAT-CRD-006`) | Por cada comentario recibido | El autor no sabe de antemano cuánto gastará |
| Que sugiere el modal | Al poner la obra en corrección | Pago por adelantado, coste conocido y acotado |

No es un matiz de redacción: **es la decisión `C-1`**, la más importante que sigue abierta, y
este modal se inclina por el pago por adelantado, que es la opción de reserva previa que
recomendamos en `FEAT-CRD-006`.

No se ha cambiado el modelo por cuenta propia: queda registrado en `FEAT-CRD-014` (`M-1`) y
en `FEAT-CRD-006` para que se decida de forma explícita.

**3. Aparece vocabulario nuevo: «corrección».** Una obra «en corrección» parece ser una obra
publicada y abierta a recibir feedback. Si es un estado del ciclo de vida de `Work`, hay que
nombrarlo y añadirlo al glosario (`M-2`). El menú lateral tiene además un bloque interno
llamado `Correcciones` en el diseño, lo que refuerza que el término es de producto y no una
licencia del texto.

### Erratas

«tús» aparece dos veces —en el título y en «Usa tús créditos»— y una tercera en «Consulta
tús movimientos.». Debe ser «tus». El botón de movimientos lleva además un punto final que
los demás botones no tienen.
