---
screen: Mi perfil — Mis relatos
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-WRK-015, FEAT-WRK-016, FEAT-WRK-004]
actors: [Writer]
updated: 2026-09-22
---

# Mi perfil — pestaña «Mis relatos»

Listado de las obras propias, con filtros por estado y ordenación. Parte de
[Mi perfil](my-profile.md).

## Controles

```text
( Todas )  ( En corrección )  ( Visibles )  ( En borrador )        ⇅ Ordenar ⌄
                                                                   ├ Más valorados
                                                                   ├ Más antiguos
                                                                   ├ Más recientes
                                                                   └ Más leídos
```

| Control | Valores |
|---|---|
| Filtro | **Todas** · **En corrección** · **Visibles** · **En borrador** |
| Orden | **Más valorados** · **Más antiguos** · **Más recientes** · **Más leídos** |

Los cuatro criterios de orden son **concretos y calculables**, a diferencia del «Más
relevantes» del muro y de los comentarios, que necesita una fórmula sin definir. Aquí no hay
ambigüedad: valoración, fecha y lecturas.

## Tarjeta de obra

| Dato | Ejemplo |
|---|---|
| Portada | Imagen |
| Título | «101 días en Japón» |
| Métricas | **📋 1 / 22** · **♡ 34** · **👁 68** |
| Sinopsis | Truncada |
| Géneros | `YoungAdult` `Ficción` |
| Tiempo de lectura | «20 min lectura» |
| **Estado** | Insignia de color según el estado |

Es la misma tarjeta del carrusel de la Home (`FEAT-COM-017`), **más la insignia de estado**,
que solo tiene sentido para el autor.

> El primer número de «1 / 22» es **siempre 1** en las cuatro obras de las capturas: 1/22,
> 1/12, 1/3, 1/1. En la lista de obras propias un indicador de progreso de lectura no
> tendría sentido, así que o es un dato de maqueta o significa otra cosa. Ver `H-2`.

## El hallazgo: una obra tiene estado

Esta pantalla cierra una duda que venía arrastrándose desde el modal de créditos —«poner tus
obras en corrección»— y desde el perfil —la pestaña «Mis correcciones»—.

**Una obra está en uno de tres estados, y solo en uno:**

| Estado | Insignia | Qué significa |
|---|---|---|
| **En borrador** | Gris, «Borradores» | Solo la ve su autor. Aún no existe para nadie más |
| **Visible** | Verde menta, «Visible» | Publicada y legible, pero **no abierta a recibir feedback** |
| **En corrección** | Naranja, «En corrección» | Abierta a que los lectores beta la comenten |

Que los contadores cuadren lo confirma: el perfil dice «4 Relatos» y las tres pestañas
suman 1 + 2 + 1 = 4.

### Por qué importa que «Visible» y «En corrección» sean distintos

Son dos preguntas diferentes:

- **¿Se puede leer?** → `Visible` y `En corrección` sí; `En borrador` no.
- **¿Se puede comentar?** → solo `En corrección`.

Una obra puede estar publicada y cerrada a la crítica. Eso encaja con el modelo de créditos:
recibir feedback cuesta, así que el autor decide **cuándo** abre esa puerta, y hasta entonces
su obra puede leerse sin consumirle nada.

### Cómo encaja con lo ya documentado

La documentación tenía dos atributos separados que se solapan con esto:

| Ya documentado | Relación con el estado |
|---|---|
| `Visibility`: `VISIBLE` / `HIDDEN` | **Se subsume.** `HIDDEN` ≈ borrador, `VISIBLE` ≈ visible |
| `BetaReaderAccessMode`: `PUBLIC` / `ON_REQUEST` / `PRIVATE` | **Sigue siendo necesario, pero solo aplica en corrección.** Responde «quién puede comentar», no «si se puede comentar» |

**Propuesta:** sustituir `Visibility` por un `WorkStatus` de tres valores, y conservar
`BetaReaderAccessMode` como atributo que solo tiene efecto mientras la obra está en
corrección. Ver `FEAT-WRK-016`.

### Y sobre todo: esto es lo que decide `R-1`

`decision:0004` fijó que los créditos se reservan al conceder acceso a un lector beta, y dejó
abierto si la reserva es **por lector o por obra** (`R-1`).

**La existencia del estado «En corrección» inclina la balanza hacia por obra.** Poner una
obra en corrección es una acción deliberada del autor, en un momento concreto: es el sitio
natural para comprometer créditos, y explica la frase del modal. Con la reserva por lector,
entrar en corrección no costaría nada y el compromiso llegaría más tarde y de forma difusa.

No se cambia `decision:0004` por cuenta propia, pero conviene resolver `R-1` con esta
pantalla delante.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Listar las obras propias, paginadas | `FEAT-WRK-015` |
| 2 | Filtrar por estado | `FEAT-WRK-015` |
| 3 | Ordenar por valoración, fecha y lecturas | `FEAT-WRK-015` |
| 4 | Estado de la obra y sus transiciones | `FEAT-WRK-016` |
| 5 | Contadores de fragmentos, valoraciones y lecturas por obra | `FEAT-WRK-015`, `H-3` |
| 6 | Tiempo estimado de lectura | `FEAT-COM-017` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **W-9** | ¿Se confirma que `WorkStatus` sustituye a `Visibility`? | Cambia el modelo de `Work` |
| **R-1** | ¿La reserva de créditos es por obra, al entrar en corrección? | Esta pantalla lo sugiere. Cerrar antes de implementar |
| W-10 | ¿Qué transiciones están permitidas? ¿Se puede volver de «en corrección» a «visible»? ¿Y a borrador? | Define la máquina de estados |
| W-11 | ¿Una obra en corrección se puede editar? | Cambiar el texto mientras alguien lo comenta |
| H-2 | ¿Qué es «1 / 22»? El primer número es siempre 1 | Si es progreso, hace falta seguimiento de lectura |
| H-3 | ¿Qué cuenta como «lectura» para el contador de ojos? | Concepto sin definir |
| W-12 | ¿«Más valorados» usa la valoración de obra (`WorkRating`) o los «me gusta»? | La tarjeta muestra un corazón, no estrellas |
| W-13 | ¿El filtro y el orden se conservan al volver a la pestaña? | Detalle de interfaz |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | En la tercera captura **falta la pestaña «Mis amigos»** | Inconsistencia de maqueta |
| A-2 | La insignia dice «Borradores» en plural sobre una sola obra | Debería ser «Borrador» |
| A-3 | El orden de los contadores cambia respecto a capturas anteriores: aquí «Seguidores / Relatos» y antes «Seguidos / Relatos» | Unificar |
| A-4 | «Mis amigos» ya va en minúscula, corrigiendo `P-8` | Corregido respecto a capturas anteriores |
| A-5 | Las tres obras «visibles» y el borrador comparten la misma sinopsis | Texto de maqueta |
| A-6 | La insignia «Nivel 5» sigue presente | Sistema descartado. **No se implementa** |
