---
id: FEAT-COM-032
title: Menciones a usuarios
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (compositor de respuesta con mención precargada)
  - docs/ui/post-interactions.md
endpoints: []
events: [UserMentioned]
depends_on: [FEAT-COM-006, FEAT-USR-034]
updated: 2026-09-24
---

# FEAT-COM-032 — Menciones a usuarios

## Resumen

Al responder a un comentario, el compositor aparece con una **mención al autor** ya escrita y
destacada: «**Juanjo Estévez** qué bien! Muchas gracias…».

Parece un detalle de interfaz. No lo es: cómo se guarde una mención determina si seguirá
siendo correcta dentro de seis meses.

## La mención se guarda como referencia, nunca como texto

Es la regla central de esta ficha.

| Si se guarda… | Qué pasa cuando el mencionado cambia de nombre |
|---|---|
| El texto «Juanjo Estévez» | La mención sigue mostrando el nombre antiguo para siempre |
| El texto «@juanjoestevez» | **Peor**: pasados 30 días ese nombre de usuario se recicla y la mención puede señalar a otra persona |
| El `UserId` | La mención se resuelve siempre al usuario correcto y muestra su nombre actual |

La segunda fila no es hipotética: los nombres de usuario se liberan 30 días después de un
cambio o de un borrado de cuenta
([`decision:0005`](../../decisions/0005-username-with-temporary-aliases.md)). Una mención
guardada como texto acabaría atribuyendo palabras a quien no las dijo.

**Se guarda el `UserId`.** El nombre se resuelve al mostrar.

## Cómo se representa

- En la interfaz, la mención muestra el **nombre** del usuario («Juanjo Estévez»), no su
  `@usuario`.
- En almacenamiento, el texto conserva una **marca** con la posición de la mención y el
  `UserId` al que apunta.
- Al servir el comentario, la API devuelve el texto y la lista de menciones con su `userId`,
  su nombre actual y su posición. **El cliente no interpreta el texto buscando arrobas.**

Devolver las menciones aparte evita que cliente y servidor tengan que coincidir en cómo se
parsea una cadena, que es una fuente clásica de discrepancias.

## Reglas de negocio

- `RN-1` Una mención referencia a un usuario por `UserId`.
- `RN-2` Se muestra con el **nombre actual** del mencionado. Si lo cambia, todas sus
  menciones pasadas se actualizan solas.
- `RN-3` Mencionar a alguien **no le da acceso a nada**. Si la publicación tiene una
  audiencia que le excluye, no la verá aunque esté mencionado.
- `RN-4` El mencionado recibe un aviso, **salvo que no pueda ver el contenido** donde se le
  menciona (`RN-3`).
- `RN-5` No se avisa a quien se menciona a sí mismo.
- `RN-6` Una mención a un usuario eliminado se muestra de forma neutra, sin enlace.
- `RN-7` Las menciones no se pueden falsificar: el `UserId` lo resuelve el servidor a partir
  de lo que el usuario seleccionó, no de lo que escribió.

`RN-3` y `RN-4` son la pareja que evita el uso de las menciones como vía de fuga. Sin ellas,
mencionar a alguien en una publicación restringida le enviaría un aviso con contenido que no
debería conocer.

`RN-7` cierra la otra puerta: si el servidor confiara en el nombre escrito, cualquiera podría
fabricar una mención que pareciera apuntar a otra persona.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UserMentioned` | Se publica un comentario o respuesta con menciones | `Notification` | `mentionedUserId`, `byUserId`, `postId`, `commentId` |

`Notification` comprueba la audiencia antes de avisar (`RN-4`).

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `comment_mention` | `comment_id`, `mentioned_user_id`, posición en el texto |

Índice sobre `comment_mention(mentioned_user_id)` para poder responder a «dónde me han
mencionado».

## Criterios de aceptación

- [ ] Una mención se guarda con el `UserId`, no con el nombre ni con el `@usuario`.
- [ ] Al cambiar el mencionado su nombre, sus menciones pasadas muestran el nuevo.
- [ ] Al cambiar el mencionado su nombre de usuario, ninguna mención pasada cambia de destino.
- [ ] La API devuelve las menciones como lista aparte, no incrustadas en el texto.
- [ ] Mencionar a alguien no le da acceso a una publicación cuya audiencia le excluye.
- [ ] No se avisa a un mencionado que no puede ver el contenido.
- [ ] No se avisa a quien se menciona a sí mismo.
- [ ] Una mención a un usuario eliminado se muestra sin enlace y no falla.
- [ ] No se puede fabricar una mención escribiendo el nombre de otro usuario a mano.

El segundo y tercer criterio son los que demuestran que la mención es una referencia y no una
cadena.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| I-5 | ¿Se puede mencionar escribiendo «@», o solo existe la mención automática al responder? | Si es lo primero, hace falta un buscador de usuarios en el compositor |
| I-10 | ¿Se puede mencionar en el texto de una publicación, no solo en comentarios? | El compositor de publicación no lo muestra |
| I-11 | ¿Hay límite de menciones por comentario? | Evitar avisos masivos |
| I-12 | ¿Se puede desactivar la recepción de avisos por mención? | Encaja con `FEAT-USR-012` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
