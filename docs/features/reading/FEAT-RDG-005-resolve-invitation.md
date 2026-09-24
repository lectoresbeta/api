---
id: FEAT-RDG-005
title: Aceptar o rechazar una invitación de lector beta
context: Reading
concept: AccessInvitation
actors: [Reader]
spec_status: REVIEW
impl_status: TODO
priority: P1
sources:
  - _sources/use-cases.pdf
  - docs/bounded-contexts/reading.md
  - docs/features/reading/FEAT-RDG-004-invite-beta-reader.md
endpoints:
  - GET /me/beta-reader-invitations
  - PUT /beta-reader-invitations/{invitationId}/resolution
events: [BetaReaderAccessGranted, BetaReaderInvitationDeclined]
depends_on: [FEAT-RDG-004]
updated: 2026-09-24
---

# FEAT-RDG-005 — Resolver una invitación de lector beta

## Resumen

Quien recibe una invitación decide si entra. Aceptar **concede el acceso en el acto**;
rechazar la cierra y se lo dice al autor.

Es la otra mitad de [`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md) y la gemela exacta
de [`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md) con los papeles cambiados: allí el
autor resuelve lo que pidió el lector; aquí el lector resuelve lo que ofreció el autor.

## Una invitación se acepta, no se cobra

Conviene decirlo porque el producto trata de créditos y esto no los toca. Aceptar una
invitación no obliga a corregir nada, no adelanta nada y no compromete nada: concede el
derecho a leer. Lo que se gana corrigiendo se gana después, al entregar, y eso es
[`FEAT-FBK-003`](../feedback/FEAT-FBK-003-answer-correction-questionnaire.md).

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Reader` | Ver las invitaciones que ha recibido | Las suyas y solo las suyas |
| `Reader` | Aceptarlas o rechazarlas | Sigue `PENDING` y va dirigida a él |
| `Writer` | — | No resuelve la invitación que él mismo envió; la **retira** (`FEAT-RDG-004` `RN-9`) |

## Reglas de negocio

- `RN-1` **Solo el destinatario resuelve.** Para cualquier otro, la invitación no existe.
- `RN-2` Aceptar crea un `BetaReaderAccess` con origen `AUTHOR_INVITATION`, **en la misma
  transacción** que cierra la invitación.
- `RN-3` Una invitación ya resuelta o retirada no se vuelve a resolver.
- `RN-4` Si ya tiene acceso vivo por otro camino, aceptar cierra la invitación y **no crea un
  segundo acceso**.
- `RN-5` Rechazar **no da motivo**, por el mismo razonamiento que
  [`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md) `RN-5`: si rechazar cuesta escribir
  una justificación, la salida fácil es no contestar nunca.
- `RN-6` El autor **sí se entera** del rechazo. Ver abajo.
- `RN-7` Aceptar sobre una obra que ya no existe responde `410`.
- `RN-8` Aceptar **no comprueba la modalidad ni el estado** de la obra. Ver abajo.
- `RN-9` El acceso concedido aquí no se revoca al descartar un borrador (`PUBLIC_JOIN` es la
  única vía deshacible).
- `RN-10` Rechazar **no impide** que el autor vuelva a invitar más adelante.

## `RN-6` — el rechazo sí se cuenta, y la cancelación no

Hay una asimetría con [`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md) `RN-7`,
donde cancelar una solicitud no avisa a nadie, y es deliberada.

Quien cancela una solicitud **retira una pregunta que hizo él**: nadie estaba esperando. Quien
rechaza una invitación **contesta a alguien que espera respuesta**, y un autor que ofreció su
obra inédita a una persona concreta merece saber que es un no, aunque solo sea para invitar a
otra.

Por eso aparece `BetaReaderInvitationDeclined`, que no estaba en el catálogo de eventos. No es
el caso de los eventos que este proyecto se ha negado a publicar —temáticas, clasificación de
contenido— porque allí no había consumidor ni siquiera sobre el papel; aquí el consumidor está
especificado y es el mismo que ya escucha las otras tres transiciones.

## `RN-8` — aceptar no mira cómo está la obra

Igual que `RN-9` de [`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md), y por la misma
razón: **la modalidad gobierna quién puede entrar, no quién ya fue invitado a entrar.**

Entre la invitación y la respuesta, la obra pudo pasar a `PUBLIC` —el acceso ya no añade
permiso, solo procedencia— o cerrarse a corrección —se puede leer igual—. Ninguna de las dos
cosas convierte la oferta del autor en un error, y el autor tiene la retirada si cambió de
opinión.

El caso que sí incomoda es el borrador: se acepta y no se puede leer hasta que la obra se
publique ([`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md) `RN-12`). La invitación espera,
y es `R-13`.

## Flujo principal

1. El lector ve sus invitaciones pendientes.
2. Responde `ACCEPTED` o `DECLINED`.
3. `Reading` comprueba que va dirigida a él y que sigue `PENDING`.
4. **Si acepta:** cierra la invitación, crea el `BetaReaderAccess` y publica
   `BetaReaderAccessGranted`. A partir de ahí lee la obra sea cual sea la modalidad.
5. **Si rechaza:** cierra la invitación y publica `BetaReaderInvitationDeclined`. El autor se
   entera.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La invitación no existe o es de otra persona | La misma respuesta | `404 INVITATION_NOT_FOUND` |
| Ya estaba aceptada, rechazada o retirada | No se deshace nada | `409 INVITATION_ALREADY_RESOLVED` |
| Una decisión que no es `ACCEPTED` ni `DECLINED` | No se ignora | `422 UNKNOWN_DECISION` |
| Ya tenía acceso vivo | Se cierra la invitación, sin segundo acceso (`RN-4`) | `200` |
| La obra ya no existe | `410` y no `404`: le invitaron a algo que existió | `410 WORK_GONE` |
| Cuenta sin activar | Regla general de escritura | `403 ACCOUNT_NOT_ACTIVATED` |

Como en su gemela, el `409` es la respuesta a la persona y el agregado sigue siendo tolerante
con un mensaje reentregado.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis invitaciones | `GET /api/v1/me/beta-reader-invitations` | `listMyBetaReaderInvitations` |
| Resolver una | `PUT /api/v1/beta-reader-invitations/{invitationId}/resolution` | `resolveBetaReaderInvitation` |

Paginación por cursor y filtro por estado, con `PENDING` por defecto. Cada elemento lleva quién
invita, cuándo, **su mensaje** y lo justo de la obra para decidir: título, sinopsis y
clasificación de contenido — **antes de aceptar**, que es cuando esa información sirve de algo
([`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md) `RN-8`).

Eso último importa más de lo que parece: aceptar una invitación es entrar en una obra inédita
que no se ha podido ojear. Es el único sitio del producto donde alguien decide leer algo sin
haberlo visto en el catálogo, y la clasificación es lo único que le avisa.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `BetaReaderAccessGranted` | Se acepta | `Notification` | `accessId`, `workId`, `authorId`, `readerId`, `grantedVia`, `grantedAt` |
| `BetaReaderInvitationDeclined` | Se rechaza | `Notification` (avisa al autor) | `invitationId`, `workId`, `authorId`, `readerId` |

`grantedVia` vale aquí `AUTHOR_INVITATION`. El segundo es **nuevo en el catálogo**, y `RN-6`
explica por qué hace falta.

**Consume**

Nada propio: `WorkDeleted` y `UserDeleted` los consume
[`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md) para las dos fichas.

## Modelo de datos afectado

Ninguna tabla nueva. La segunda escritura del contexto que toca dos agregados a la vez, con
el mismo índice único parcial sobre los accesos vivos sosteniendo `RN-4`.

## Criterios de aceptación

- [ ] El invitado acepta y queda como lector beta de la obra.
- [ ] Tras aceptar, lee una obra `PRIVATE` que antes no podía abrir.
- [ ] El invitado rechaza y el autor se entera; no queda acceso ninguno.
- [ ] Resolver dos veces la misma invitación responde `409`.
- [ ] Resolver la invitación de otra persona responde `404`.
- [ ] Una invitación retirada por el autor ya no se puede aceptar.
- [ ] Aceptar cuando ya se tenía acceso no crea un segundo acceso.
- [ ] Aceptar sobre una obra borrada responde `410`.
- [ ] La lista de invitaciones enseña la clasificación de contenido antes de aceptar.
- [ ] Rechazar no impide una invitación posterior a la misma obra.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-17 | ¿Aceptar una invitación debería llevar directamente a la obra? | Solo de pantalla, pero es lo que la persona espera |
| R-18 | ¿Se pueden rechazar todas las invitaciones de un autor de una vez, o bloquearle? | Toca [`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md): invitar sin límite es un canal de mensajería |
| R-13 | ¿Cómo se explica la espera al invitado de un borrador? | Compartida con `FEAT-RDG-004` |

## Estado

**Especificación:** `REVIEW` (2026-09-24). Ficha completa. Lo que necesita validación de
producto es `RN-6` —avisar al autor del rechazo, con el evento nuevo que eso añade— y que la
lista de invitaciones enseñe sinopsis y clasificación de una obra que el invitado todavía no
puede leer.

**Implementación:** `TODO`. Se implementa junto a
[`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md).
