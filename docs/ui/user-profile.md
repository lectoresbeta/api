---
screen: Perfil de otro usuario
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-014, FEAT-USR-032, FEAT-COM-010, FEAT-COM-033, FEAT-COM-034, FEAT-COM-035]
actors: [User]
updated: 2026-09-22
---

# Perfil de otro usuario

Vista pública de un perfil ajeno. Es la contraparte de [Mi perfil](my-profile.md), y las
diferencias entre ambas son lo interesante.

## Qué cambia respecto al perfil propio

| | Mi perfil | Perfil ajeno |
|---|---|---|
| Pestañas | Mi muro · Mis relatos · **Mis correcciones** · Mis amigos · Más info | Muro · Relatos · Amigos · Más info |
| Relación | — | Insignia **«Te sigue»** junto al `@usuario` |
| Acciones | Editar todo | **Seguir** · **Enviar mensaje** · **«···»** |
| Estados vacíos | En primera persona, con CTA | **En tercera persona, sin CTA** |
| Alta de contenido | Botones «Añadir obra», «Publica tu primer post» | Ninguno |

### «Correcciones» no tiene pestaña pública

Es el hallazgo con más consecuencias. La pestaña **«Mis correcciones» desaparece** en el
perfil ajeno, pero **el contador «0 Correcciones» sigue ahí**, en la columna izquierda.

Es decir: cuántas correcciones ha hecho alguien es público; **cuáles**, no.

Tiene sentido y conviene que sea deliberado: el feedback es una conversación entre el
corrector y el autor de la obra, sobre un texto inédito. Exponer la lista revelaría qué está
leyendo cada cual y, con ella, qué obras hay en corrección.

El contador como reputación y la lista como privada es una distinción razonable, pero
**hay que confirmarla** (`U-17`): tal y como está el diseño podría ser un olvido.

## Cabecera

| Elemento | Detalle |
|---|---|
| Portada y avatar | Los del usuario visitado |
| Nombre | «Juanjo Estévez» |
| `@usuario` | «@juanjoestevez» |
| **Relación** | Insignia **«Te sigue»**, si ese usuario te sigue a ti |
| Descripción | Truncada con «Ver más» |
| Contadores | Seguidos · Relatos · Seguidores · Correcciones |

La insignia «Te sigue» exige saber, en cada visita, si el visitado sigue al visitante. Es un
dato de relación, no del perfil.

## Acciones

El estado de seguimiento cambia **cuál es la acción principal**:

| Estado | Botón principal | Botón secundario | Menú |
|---|---|---|---|
| No le sigo | **Seguir** (con icono de añadir persona) | Enviar mensaje | «···» |
| Le sigo | **Enviar mensaje** (con icono de envío) | **✓ Siguiendo** | «···» |

Cuando aún no le sigues, lo que se te propone es seguirle; una vez le sigues, lo que tiene
sentido es escribirle. El intercambio de jerarquía es deliberado.

## Menú «···»

| Opción | Funcionalidad |
|---|---|
| Compartir perfil | `FEAT-USR-032` |
| Dejar de seguir | `FEAT-COM-010` |
| **Silenciar** | `FEAT-COM-033` — **nueva** |
| **Bloquear** | `FEAT-COM-034` — **nueva** |
| **Denunciar** | `FEAT-COM-035` — **nueva** |

Las tres últimas no estaban contempladas en ningún sitio.

### Silenciar y bloquear no son lo mismo

Conviene separarlas desde el principio, porque es fácil implementarlas como una sola:

| | Silenciar | Bloquear |
|---|---|---|
| Quién lo nota | Nadie: es unilateral y discreto | Ambas partes |
| Su contenido en mi muro | Desaparece | Desaparece |
| Mi contenido para él | Lo sigue viendo | **Deja de verlo** |
| Mensajes directos | Siguen funcionando | **Se cortan** |
| Seguimiento | Se mantiene | **Se deshace en ambos sentidos** |
| Para qué sirve | Bajar el ruido | Cortar el contacto |

Silenciar es una preferencia de visualización. Bloquear es una **regla de acceso**, y por eso
atraviesa varios bounded contexts. Ver `FEAT-COM-034`.

### Denunciar requiere moderación, que no existe

Es la tercera vez que aparece una denuncia —comentarios (`FEAT-FBK-009`), publicaciones
(`FEAT-COM-023`) y ahora usuarios— y sigue sin haber ningún actor que las atienda (`V-1`).

Recoger denuncias que nadie lee es peor que no ofrecerlas: crea una expectativa que la
plataforma no cumple.

## Estados vacíos

| Pestaña | Mensaje |
|---|---|
| Muro | «Juanjo todavía no ha publicado ningún post» |
| Amigos → Seguidos | «Juanjo todavía no sigue a otros autores» |
| Más info → Obras publicadas | «Juanjo todavía no ha añadido obras publicadas» |

Dos cosas los distinguen de los del perfil propio:

- Van en **tercera persona** y **no llevan CTA**: no hay nada que el visitante pueda hacer al
  respecto.
- Usan el **nombre de pila** —«Juanjo»—, no el nombre completo. Eso obliga a partir el
  `Name`, que es texto libre y no siempre tendrá una primera palabra útil. Ver `U-18`.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Perfil público con sus contadores | `FEAT-USR-014` |
| 2 | Saber si el visitado sigue al visitante, y al revés | `FEAT-USR-014` |
| 3 | Muro, relatos, amigos y obras publicadas de otro usuario, paginados | `FEAT-USR-014` |
| 4 | **No exponer la lista de correcciones** de otro usuario | `U-17` |
| 5 | Seguir y dejar de seguir | `FEAT-COM-010` |
| 6 | Compartir el perfil | `FEAT-USR-032` |
| 7 | Silenciar y dejar de silenciar | `FEAT-COM-033` |
| 8 | Bloquear y desbloquear, con sus efectos | `FEAT-COM-034` |
| 9 | Denunciar a un usuario | `FEAT-COM-035` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **U-17** | ¿Es deliberado que el **contador** de correcciones sea público y la **lista** no? | Si no lo es, hay una pestaña que falta; si lo es, hay que documentarlo como regla |
| U-18 | ¿De dónde sale «Juanjo» en los estados vacíos? | Partir el `Name` es frágil: es texto libre |
| U-19 | ¿Qué relatos de otro autor se ven: solo los visibles y en corrección? | Un borrador ajeno **nunca** debe aparecer |
| U-20 | ¿La lista de seguidores de alguien es pública? | El diseño la muestra sin restricción |
| U-21 | ¿Se puede enviar un mensaje a quien no lo tiene habilitado? El botón aparece siempre | `FEAT-USR-010` |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| **A-1** | **Los lápices de edición de portada y avatar aparecen en el perfil ajeno** | Error de maqueta evidente: no se puede editar el perfil de otro. El backend debe rechazarlo igualmente |
| A-2 | Los contadores están a 0 mientras el muro muestra publicaciones | Datos de maqueta |
| A-3 | La insignia «8 Level» sigue presente | Sistema descartado. **No se implementa** |
| A-4 | Layout ancho, no el estrecho de otras capturas | Refuerza `I-6` |
