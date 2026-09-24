---
id: FEAT-RDG-010
title: Revocar el acceso de un lector beta
context: Reading
concept: BetaReaderAccess
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - conversation:2026-09-24
  - docs/features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md
  - docs/features/reading/FEAT-RDG-003-resolve-access-request.md
endpoints:
  - GET /works/{workId}/beta-readers
  - DELETE /works/{workId}/beta-readers/{readerId}
events: [BetaReaderAccessRevoked]
depends_on: [FEAT-RDG-001, FEAT-RDG-003]
updated: 2026-09-24
---

# FEAT-RDG-010 — Revocar el acceso de un lector beta

## Resumen

El autor retira a alguien el acceso a una obra suya, y ve **a quién se lo ha dado**.

`R-1` lleva abierta desde el principio —«¿el autor puede revocar un acceso ya concedido?»— y
tres funcionalidades la han ido haciendo más urgente: hoy se entra a una obra por tres
caminos, dos de ellos abiertos por el propio autor, y **no hay forma de salir**. Esta ficha la
cierra con un sí.

> **Por qué importa más de lo que parece.** Esta plataforma custodia **obra sin publicar**. Un
> acceso de lector beta es permiso para leerla, y hasta ahora el único modo de retirarlo era
> bloquear a la persona, que es una respuesta social a un problema que muchas veces no lo es:
> alguien que ya no va a corregir, un acceso concedido por error, una obra que se quiere
> cerrar antes de publicarla.

## El hueco que cierra

`FEAT-RDG-001` lo dice de frente: **abandonar sin descartar no revoca nada**. Quien abre un
capítulo de una obra pública, empieza a corregir y cierra la pestaña conserva el acceso para
siempre, porque cerrar una pestaña no es un hecho que nadie publique.

La alternativa que aquella ficha descartó —caducar accesos por inactividad, con su reloj y su
estado nuevo— sigue descartada. Lo que faltaba era esto: que el autor pueda resolverlo él.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Ver quién tiene acceso a **su** obra | Sesión, y ser su autor |
| `Writer` | Retirar ese acceso | Sesión, cuenta activada y ser su autor |

La autoría se comprueba contra `Work` por su contrato publicado, no contra el propio acceso:
así una obra que cambiara de manos no dejaría bandejas abiertas a quien ya no es su autor.

Una obra que no es suya responde `404`, no `403`: confirmar que existe sería contar que hay
una obra ahí.

## Reglas de negocio

- `RN-1` Solo el autor de la obra revoca accesos a esa obra.
- `RN-2` Revocar es **idempotente**: si esa persona no tiene acceso vivo, la operación no
  falla. El estado que se pedía ya se cumple.
- `RN-3` El acceso se retira **desde ese instante**. No hay plazo ni aviso previo.
- `RN-4` Una corrección en curso de esa persona **deja de poder entregarse**, igual que con un
  bloqueo ([`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md) `RN-B2`). Su borrador se
  conserva, porque es texto suyo, y **no genera cargo ni abono**.
- `RN-5` Las correcciones **ya entregadas no se tocan**: el autor las pagó y el lector las
  ganó. Revocar corta el futuro, no reescribe el pasado.
- `RN-6` También se puede revocar un acceso **ganado** —el de quien ya entregó una corrección
  de esa obra—. Ver abajo.
- `RN-7` Revocar **no impide volver a entrar**. En una obra `PUBLIC`, empezar a corregir
  vuelve a conceder el acceso (`FEAT-RDG-001`). Ver abajo, porque es la parte que se
  malinterpreta.
- `RN-8` **Se avisa a la persona** desde
  [`FEAT-NOT-001`](../notification/FEAT-NOT-001-in-app-notifications.md), que resolvió `R-13`.
  El aviso sale de `RN-10` y no dice por qué: el hecho no distingue los tres caminos, y
  contar que ha habido un bloqueo sería anunciarlo.
- `RN-9` Revocar exige la cuenta activada; consultar la lista, no.
- `RN-10` Se publica `BetaReaderAccessRevoked`, el mismo hecho que ya publican los otros dos
  caminos de revocación.
- `RN-11` Revocar **no cierra** las solicitudes ni las invitaciones pendientes de esa persona:
  son puertas distintas, y el autor las resuelve donde están.

## `RN-6`: revocar un acceso ganado

Quien entrega una corrección de una obra **se gana** el acceso a ella (`FEAT-RDG-001` `RN-5`),
y eso es lo que impide que descartar un borrador se lo quite. Podría parecer que un acceso
ganado es intocable.

No lo es, y la razón es la misma que sostiene toda esta funcionalidad: **es obra sin publicar
y su autor manda**. Lo que el lector ganó —que se le pagara su trabajo y que su corrección
cuente— no se le quita (`RN-5`); lo que se retira es la lectura futura de un texto ajeno.

Es una asimetría incómoda y conviene tenerla a la vista: el lector cumplió, cobró, y aun así
puede quedarse fuera. La alternativa —accesos irrevocables— convertiría una corrección en una
llave permanente sobre la obra de otra persona, y eso es peor.

## `RN-7`: revocar no es expulsar

Esto es lo que hay que entender antes de usarlo:

| Modalidad de la obra | Qué consigue revocar |
|---|---|
| `PRIVATE` | Retira el acceso, y sin él no hay forma de volver a entrar |
| `ON_REQUEST` | Lo retira. Esa persona puede **volver a solicitarlo**, y el autor decidir otra vez |
| `PUBLIC` | Lo retira, y **volverá a concederse en cuanto empiece a corregir de nuevo** |

En una obra pública, revocar sirve para cortar lo que está pasando ahora —una corrección en
curso que el autor no quiere— y no para dejar a nadie fuera. Para eso hay dos herramientas
distintas: **cerrar la modalidad de la obra**, que afecta a todos, o **bloquear a la persona**
(`FEAT-COM-034`), que afecta solo a ella y en todas partes.

Decirlo aquí es la única forma de que quien lo implemente o lo use no espere otra cosa.

## Flujo principal

1. El autor abre la lista de lectores beta de su obra.
2. Ve quién tiene acceso, **por qué camino entró** y desde cuándo.
3. Retira el que quiera.
4. Esa persona deja de poder abrir la obra, y de poder entregar lo que estuviera escribiendo.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Esa persona no tenía acceso vivo | No pasa nada (`RN-2`) | `204` |
| La obra no es suya, o no existe | Se responde como si no existiera | `404` con `code: WORK_NOT_FOUND` |
| El autor se revoca a sí mismo | No tiene acceso que revocar: es su obra | `204`, sin efecto |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |
| Cursor manipulado al listar | Se rechaza | `422` con `code: INVALID_CURSOR` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Quién puede leer mi obra | `GET /works/{workId}/beta-readers` | `listWorkBetaReaders` |
| Retirar un acceso | `DELETE /works/{workId}/beta-readers/{readerId}` | `revokeBetaReaderAccess` |

`DELETE` sobre la persona dentro de la obra, y no sobre un identificador de acceso: lo que el
autor quiere decir es «esta persona, fuera de esta obra», y no tiene por qué saber que existe
una fila llamada acceso. Además hace la operación idempotente sin esfuerzo.

**El listado hace falta tanto como la revocación.** Sin él, el autor no puede saber a quién le
ha dado acceso —los tres caminos dejan gente dentro por sitios distintos— y no podría elegir a
quién retirarlo.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `BetaReaderAccessRevoked` | Se retira un acceso | `Notification` (cuando exista) | `accessId`, `workId`, `readerId`, `revokedAt` |

Es **el mismo hecho** que publican descartar un borrador y bloquear a alguien. Quien lo
consume no tiene por qué saber cuál de los tres caminos lo provocó: lo que le importa es que
esa persona ya no puede leer esa obra.

## Modelo de datos afectado

Ninguno nuevo. `beta_reader_access` ya tiene `revoked_at`, y revocar es escribirlo — que es
exactamente lo que hacen los otros dos caminos.

## Criterios de aceptación

- [x] El autor ve quién tiene acceso a su obra, con su tarjeta de perfil, por dónde entró y desde cuándo.
- [x] Un tercero no puede ver esa lista.
- [x] Revocar retira el acceso de inmediato.
- [x] Revocar a quien no tiene acceso no falla.
- [x] Un tercero no puede revocar accesos de una obra ajena.
- [x] Tras revocar, esa persona no puede abrir el panel de corrección de una obra cerrada.
- [x] Tras revocar, no puede entregar la corrección que tuviera empezada.
- [x] Lo que ya había entregado se conserva y sigue contando.
- [x] Un acceso ganado también se puede revocar.
- [x] En una obra `PUBLIC`, volver a empezar una corrección concede el acceso otra vez.
- [x] Se publica `BetaReaderAccessRevoked`.
- [x] Con la cuenta sin activar, revocar devuelve `403`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-1 | ¿El autor puede revocar un acceso ya concedido? | **Resuelta aquí: sí** |
| ~~R-13~~ | ¿Se avisa a quien pierde el acceso, y con qué texto? | **Resuelta en [`FEAT-NOT-001`](../notification/FEAT-NOT-001-in-app-notifications.md): sí, y sin motivo.** La confrontación que se temía venía de explicar por qué; el aviso solo dice que esa obra ya no se puede leer, que es lo que hace falta para no seguir escribiendo en balde. El texto lo compone el cliente a partir del tipo y del payload |
| R-14 | ¿Debería poder revocarse en bloque —«retirar a todos»— al cerrar una obra? | Con muchos lectores, de uno en uno es tedioso. Es una operación por lotes, no un modelo distinto |
| R-15 | ¿Conviene que la lista diga si esa persona ha entregado alguna corrección? | Es de `Feedback`, y hoy `Reading` solo sabe si el acceso está «ganado», que es casi lo mismo |
| R-22 | Una reentrega tardía de `CorrectionStarted` **deshace una revocación**. ¿Se ata al hecho que lo concedió, o se acepta? | Encontrado al conectar `Notification` (2026-09-24). `GrantAccessOnCorrectionStarted` es idempotente mientras el acceso siga vivo, que es lo que comprueba; si entre la primera entrega y una reentrega el autor revocó el acceso —o bloqueó a esa persona—, no encuentra nada vivo y **lo concede otra vez**. Una persona expulsada recupera el acceso porque la cola repitió un mensaje, en silencio. En una obra `PUBLIC` importa poco (`RN-7` ya la deja volver a entrar); en una cerrada es una revocación deshecha. El arreglo natural es guardar en el acceso **qué hecho lo abrió** y preguntarle a eso en vez de al estado, pero es una columna, una migración y tocar `Reading`, así que no entra en la ficha que lo encontró |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Cierra `R-1`, abierta desde `FEAT-RDG-001`. Las
decisiones discutibles van dichas de frente: se puede revocar un acceso ganado (`RN-6`) y
revocar no expulsa de una obra pública (`RN-7`).

**Implementación:** `DONE` (2026-09-24). `GET /api/v1/works/{workId}/beta-readers` y
`DELETE /api/v1/works/{workId}/beta-readers/{readerId}`. **Sin migración**: `revoked_at` ya
estaba, y revocar es escribirlo — exactamente lo que hacen los otros dos caminos.

### El tercer camino a la misma puerta

Un acceso se retira ya por tres sitios: **descartar un borrador**
([`FEAT-RDG-001`](FEAT-RDG-001-become-beta-reader-by-correcting.md) `RN-5`), **un bloqueo**
([`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md) `RN-B1`) y ahora la decisión del
autor. Los tres escriben la misma columna y publican **el mismo hecho**, así que quien lo
consume no tiene por qué saber cuál fue: lo que le importa es que esa persona ya no puede leer
esa obra.

Que el tercero costara tan poco es la prueba de que los dos primeros estaban bien puestos.

### `RN-4` no necesitó código

«Una corrección en curso deja de poder entregarse» sale del acceso revocado: `Feedback`
comprueba el acceso al abrir el panel y al entregar, así que sin acceso no hay entrega. Es la
misma cadena que ya hacía funcionar el bloqueo, y la razón de que ninguna de las dos
funcionalidades tenga que saber qué es una corrección.

### La lista tampoco se filtra por privacidad

Segundo —y último— uso legítimo de `ProfileCards`: quien tiene acceso a una obra inédita
aparece **aunque haya cerrado su perfil después**. Lo contrario convertiría cerrar el perfil en
una forma de volverse invisible para el autor cuya obra se está leyendo, que es exactamente al
revés de lo que ese ajuste promete.

### Las dos cosas que alguien esperará distintas

- **Revocar no expulsa de una obra pública** (`RN-7`). Esa persona vuelve a entrar en cuanto
  empiece otra corrección, porque así funciona `PUBLIC`. Hay una prueba que lo enseña, y está
  escrita para que se lea como una advertencia y no como un fallo.
- **Un acceso ganado también se revoca** (`RN-6`). Lo que el lector ganó —cobrar, y que su
  corrección cuente— no se le quita; lo que se retira es la lectura futura de un texto ajeno.

### Lo que faltaba

Avisar a quien pierde el acceso (`R-13`). Lo cumple
[`FEAT-NOT-001`](../notification/FEAT-NOT-001-in-app-notifications.md) desde 2026-09-24: el
mismo `BetaReaderAccessRevoked` de `RN-10` se convierte en un aviso
`BETA_READER_ACCESS_REVOKED`, por los tres caminos de revocación y sin distinguirlos.

Queda `R-14` —revocar en bloque— y `R-15`.
