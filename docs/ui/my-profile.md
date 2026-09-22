---
screen: Mi perfil — estados vacíos
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-028, FEAT-USR-029, FEAT-USR-030, FEAT-USR-031, FEAT-USR-014, FEAT-USR-015, FEAT-COM-026, FEAT-FBK-010, FEAT-WRK-004]
actors: [User]
updated: 2026-09-22
---

# Mi perfil — estados vacíos

Cinco capturas del perfil propio recién creado, con todas sus secciones vacías. El layout
que lo envuelve está en [app-layout-and-navigation.md](app-layout-and-navigation.md).

Es la vista **propia y editable** del perfil. La vista pública que ven los demás
(`FEAT-USR-014`) no está entre estas capturas y previsiblemente difiere: sin lápices de
edición, sin cajas de «Añadir».

## Estructura

```text
┌──────────────────────────────────────────────────────┐
│  PORTADA                                          ✎  │
│                                            ┌─────────┤
│  ╭──────╮                                  │ 0 Level │
└──┤avatar├──────────────────────────────────┴─────────┘
   ╰───✎──╯   Mi muro · Mis relatos · Mis correcciones ·
              Mis Amigos · Más info
───────────────────────────────────────────────────────
 Beatriz Alonso     ↗ │
 @bealonso            │   [contenido de la pestaña]
 ┌─────────────────┐  │
 │Añade descripcion│  │
 └─────────────────┘  │
 0 Seguidos  0 Relatos│
 0 Seguidores 0 Correcciones
```

## Cabecera

| Elemento | Detalle |
|---|---|
| Portada | Imagen de fondo con lápiz de edición arriba a la derecha. **Concepto nuevo** |
| Avatar | Círculo superpuesto, con su propio lápiz de edición |
| Insignia | ~~«0 Level»~~ — **error del diseño**, no hay sistema de niveles. No se implementa |
| Nombre | «Beatriz Alonso» — el nombre público (`FEAT-USR-022`) |
| Identificador | **«@bealonso»** — el nombre de usuario (`FEAT-USR-033`) |
| Compartir | Icono de compartir junto al nombre |
| Descripción | Campo editable en línea con marcador «Añade descripcion» |

> ### El `@identificador` es el nombre de usuario
>
> **Resuelto** en [`decision:0005`](../decisions/0005-username-with-temporary-aliases.md),
> que revierte la decisión previa de no usar nombre de usuario.
>
> `@bealonso` es el `Username`: único, asignado automáticamente desde el email al
> registrarse, editable **una vez cada 30 días** y parte de la URL del perfil
> (`lectoresbeta.com/profile/bealonso`).
>
> Al cambiarlo, el nombre anterior queda como **alias durante 30 días**: los enlaces
> compartidos siguen funcionando y nadie puede ocupar ese nombre mientras tanto. Ver
> `FEAT-USR-033` a `FEAT-USR-036`.
>
> Queda una discrepancia menor de maqueta: el onboarding saluda con «beatrizalonso» y aquí
> el identificador es «bealonso». Con la regla actual, ambos deberían coincidir salvo que el
> usuario lo haya cambiado.

## Pestañas

| Pestaña | Contenido | Funcionalidad |
|---|---|---|
| **Mi muro** | Publicaciones propias | `FEAT-COM-026` |
| **Mis relatos** | Obras propias | `FEAT-WRK-004` |
| **Mis correcciones** | Feedback que el usuario ha dado | `FEAT-FBK-010` |
| **Mis Amigos** | Seguidos y seguidores | `FEAT-COM-027` |
| **Más info** | Obras publicadas y premios | `FEAT-USR-029`, `FEAT-USR-030` |

> «Mis Amigos» usa mayúscula donde las demás no («Mis relatos», «Mis correcciones»).
> Además **«amigos» no describe lo que contiene**: seguir es una relación asimétrica, no una
> amistad. Ver `P-8`.

## Contadores

Cuatro cifras bajo la descripción, todas a cero en las capturas:

| Contador | Qué cuenta | Contexto propietario |
|---|---|---|
| Seguidos | Autores a los que sigue | `Community` |
| Seguidores | Quienes le siguen | `Community` |
| **Relatos** | Obras propias | `Work` |
| **Correcciones** | Feedback que ha dado | `Feedback` |

Son agregados de **tres contextos distintos**. La composición sigue el mismo patrón que
`GET /me/context` (`FEAT-USR-027`): contratos de consulta explícitos ensamblados en
`Infrastructure`, nunca consultas cruzadas a las tablas de otro contexto.

## Estados vacíos

### 1. Mi muro

| Elemento | Contenido |
|---|---|
| Caja de publicación | Avatar, «¿Qué estás pensando?», botón **«Enviar»** |
| Icono | Bocadillo con corazón |
| Título | «Todavía no has publicado ningún post» |
| Texto | «Aquí estarán todos los posts que publiques.» |
| Botón | «Publica tu primer post» |

> En la Home el botón de la misma caja se llamaba «Publicar». Aquí es «Enviar». Ver `P-7`.

### 2. Mis Amigos → Seguidos

| Elemento | Contenido |
|---|---|
| Sub-pestañas | «Seguidos» (activa) · «Seguidores» |
| Título | «Todavía no sigues a autores» |
| Texto | «Aquí se verán los perfiles de los autores a los que sigas y que te siguen.» |
| Botón | «Explorar perfiles» |

> El texto describe **ambas** sub-pestañas, aunque solo esté mostrando una. Ver `P-7`.

### 3. Mis Amigos → Seguidores

| Elemento | Contenido |
|---|---|
| Título | «Todavía no tienes seguidores» |
| Texto | «Empieza a interactuar en la plataforma para conseguir seguidores. Puedes subir algún relato, corregir alguna obra o seguir tú a otros autores.» |
| Botón | «Explorar perfiles» |

Este texto es el más informativo de los cinco: enumera las tres acciones que generan
visibilidad —publicar obra, corregir, seguir— y confirma que **«corregir una obra» es la
forma en que el producto nombra dar feedback**.

### 4. Más info → Obras publicadas

| Elemento | Contenido |
|---|---|
| Sub-pestañas | «Obras publicadas» (activa) · «Premios y reconocimientos» |
| Icono | Marcador |
| Título | «Todavía no has añadido obras publicadas» |
| Texto | «Añade tus obras publicadas para promocionarlas» |
| Botón | «Añadir obra» |

### 5. Más info → Obras publicadas, tarjeta de alta

La segunda captura muestra la tarjeta que aparece al añadir:

| Campo | Ejemplo |
|---|---|
| Portada | Marcador con «+» |
| Título | «Título» |
| Editorial | «Editorial» |
| Año | «Año» |
| Acción | Botón **«Comprar»** |

> ### Una «obra publicada» no es una `Work`
>
> Es el hallazgo estructural de esta pantalla. Tiene **editorial, año y enlace de compra**:
> es un libro ya editado fuera de la plataforma, que el autor añade a su perfil como mérito.
>
> | | `Work` (relato) | Obra publicada |
> |---|---|---|
> | Qué es | Manuscrito inédito | Libro editado y a la venta |
> | Vive en | Lectores Beta | El mundo exterior |
> | Para qué | Recibir feedback de lectores beta | Acreditar trayectoria del autor |
> | Tiene contenido | Sí, y es el activo a proteger | No: solo metadatos y un enlace |
> | Cuesta créditos | Sí | No |
>
> Son dos conceptos distintos que comparten la palabra «obra». Modelarlos juntos sería un
> error caro: uno custodia texto inédito y el otro es una ficha bibliográfica pública.
>
> Se nombra `PublishedBook` para evitar la colisión con `Work`. Ver `P-2`.

## «Corrección» queda confirmado como concepto

Aparece tres veces en esta pantalla: la pestaña «Mis correcciones», el contador
«0 Correcciones» y el texto «corregir alguna obra». Sumado al modal de créditos —«poner tus
obras en corrección»— cierra la duda que quedaba abierta en `M-2`.

**Interpretación propuesta:**

| Término de producto | Significado | Identificador |
|---|---|---|
| Corrección | El feedback que un lector beta deja sobre una obra, visto desde quien lo hace | `Feedback` |
| Obra en corrección | Obra abierta a recibir feedback | Estado de `Work`, ver `P-3` |

Es decir: **«corrección» y `Feedback` son el mismo concepto**, nombrado desde el lado del
corrector. El glosario ya fija `Feedback` como identificador; lo que faltaba era registrar
que el producto lo llama «corrección» de cara al usuario.

Lo que sigue sin resolver es si «obra en corrección» es un **estado** del ciclo de vida de
`Work` (`P-3`).

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Imagen de portada del perfil, editable | `FEAT-USR-028` |
| 2 | Avatar editable | `FEAT-USR-028` |
| 3 | Descripción editable en línea | `FEAT-USR-028` |
| 4 | Enlace público de perfil para compartir, basado en el nombre de usuario | `FEAT-USR-032`, `FEAT-USR-035` |
| 6 | Contadores de seguidos, seguidores, relatos y correcciones | `FEAT-USR-028` |
| 7 | Publicaciones propias paginadas | `FEAT-COM-026` |
| 8 | Obras propias paginadas | `FEAT-WRK-004` |
| 9 | Feedback dado por el usuario, paginado | `FEAT-FBK-010` |
| 10 | Seguidos y seguidores paginados | `FEAT-COM-027` |
| 11 | Obras publicadas: alta, edición, borrado y orden | `FEAT-USR-029` |
| 12 | Premios y reconocimientos | `FEAT-USR-030` |

## Contradicciones detectadas

| # | Qué dice el diseño | Qué dice la documentación | Resolución |
|---|---|---|---|
| C-1 | Muestra `@bealonso` | «No usaremos el campo nombre de usuario» | **Resuelta:** el nombre de usuario existe (`decision:0005`) |
| C-2 | Insignia «0 Level» | No existe ningún sistema de niveles | **Resuelta:** es un error del diseño. Se retira |
| C-3 | Perfil con portada, avatar y descripción | `FEAT-USR-015` describe una «página de autor» con bio, foto y referencias | ¿Son la misma pantalla? `P-5` |
| C-4 | Sin personalización visual | `FEAT-USR-016` permite elegir fuentes, colores y fondos | Puede estar fuera de estas capturas, o haberse descartado. `P-6` |
| C-5 | Botón «Enviar» | En la Home la misma caja decía «Publicar» | Unificar. `P-7` |

## Funcionalidades afectadas

| ID | Acción | Estado |
|---|---|---|
| FEAT-USR-028 | **Nueva** — cabecera y datos del perfil propio | `DRAFT` |
| FEAT-USR-029 | **Nueva** — obras publicadas (bibliografía externa) | `DRAFT` |
| FEAT-USR-030 | **Nueva** — premios y reconocimientos | `PENDING` |
| FEAT-USR-031 | ~~Nivel del usuario~~ — error del diseño | `DEPRECATED` |
| FEAT-USR-033 a 036 | **Nuevas** — nombre de usuario, cambio con alias, resolución y purga | `DRAFT` |
| FEAT-USR-032 | **Nueva** — compartir el perfil | `PENDING` |
| FEAT-COM-026 | **Nueva** — muro propio | `PENDING` |
| FEAT-COM-027 | **Nueva** — seguidos y seguidores | `PENDING` |
| FEAT-FBK-010 | **Nueva** — mis correcciones | `PENDING` |
| FEAT-USR-014 | Ver perfil público: esta es su variante propia y editable | `PENDING` |
| FEAT-USR-015 | Página de autor: puede ser esta misma pantalla | `PENDING`, ver `P-5` |
| FEAT-WRK-004 | «Mis relatos» es una vista de las obras propias | `PENDING` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-1 | ¿Existe `@identificador`? | **Resuelta:** sí, es el `Username`. Ver `decision:0005` |
| **P-2** | ¿Se confirma que una «obra publicada» es un concepto aparte de `Work`? | Modelo de datos. Mezclarlos sería un error caro |
| P-3 | ¿«Obra en corrección» es un estado del ciclo de vida de `Work`? | `M-2`, `W-5` |
| P-4 | ¿Qué es «0 Level»? | **Resuelta:** error del diseño. Se retira de la pantalla |
| P-5 | ¿«Mi perfil» y la «página de autor» de `FEAT-USR-015` son la misma pantalla? | Si no, hay dos perfiles que mantener |
| P-6 | ¿Sigue en alcance la personalización visual de `FEAT-USR-016`? | No aparece en estas capturas |
| P-7 | Textos por unificar: «Enviar» frente a «Publicar»; el vacío de «Seguidos» describe también a los seguidores | Coherencia |
| P-8 | ¿«Mis Amigos» es el nombre correcto para seguidos y seguidores? | Seguir es asimétrico; «amigos» sugiere reciprocidad |
| P-9 | ¿Las obras publicadas se validan de algún modo, o el autor declara lo que quiera? | Un perfil con méritos inventados afecta a la confianza |
| P-10 | ¿El enlace «Comprar» apunta fuera de la plataforma? ¿Hay afiliación? | Enlaces salientes y sus implicaciones |
| P-11 | ¿Qué ve el visitante en la vista pública de este perfil? | No hay capturas de la vista no editable |

## Erratas del diseño

- «Añade descripcion» → «descripción».
- «Mis Amigos» con mayúscula, frente a «Mis relatos» y «Mis correcciones».
- «0 Level» se retira: no hay sistema de niveles.
- El onboarding saluda con «beatrizalonso» y el perfil muestra «@bealonso». Con la regla de
  asignación actual deberían coincidir.
