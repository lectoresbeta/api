---
id: FEAT-USR-034
title: Cambiar el nombre de usuario y alias temporal
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - decision:0005
  - conversation:2026-09-22
endpoints: [PUT /me/username]
events: [UsernameChanged]
depends_on: [FEAT-USR-033]
updated: 2026-09-24
---

# FEAT-USR-034 — Cambiar el nombre de usuario y alias temporal

## Resumen

El usuario puede cambiar su nombre de usuario **una vez cada 30 días**. El nombre anterior no
queda libre: se convierte en un **alias** de la cuenta durante 30 días, de modo que los
enlaces de perfil ya compartidos siguen funcionando.

Ese alias no solo mantiene vivos los enlaces: **impide que otra persona ocupe el nombre** y
herede el tráfico dirigido a alguien distinto. Esa es su razón de ser principal.

> **Dónde se cambia** (`S-3`, resuelta): en **Configuración › Perfil**, junto al nombre, la
> biografía y los géneros ([`settings.md`](../../ui/settings.md)). Comparte el «Guardar» de
> la pestaña, pero **no comparte sus reglas**: este cambio bloquea al usuario 30 días, así que
> mantiene su propio endpoint y sus propias validaciones, y un fallo aquí no debe impedir que
> se guarde el resto de la pestaña.

## Cómo funciona

```text
Día 0    username: pabloblanco1
         lectoresbeta.com/profile/pabloblanco1  ──▶ perfil de Pablo

Día 10   cambia a «pblanco»
         username: pblanco
         alias:    pabloblanco1  (caduca el día 40)

         lectoresbeta.com/profile/pblanco       ──▶ perfil de Pablo
         lectoresbeta.com/profile/pabloblanco1  ──▶ perfil de Pablo (vía alias)
         «pabloblanco1» no lo puede registrar nadie

Día 40   el alias caduca
         lectoresbeta.com/profile/pabloblanco1  ──▶ 404
         «pabloblanco1» queda disponible
         Pablo puede volver a cambiar de nombre
```

## Recuperar el nombre anterior

Cambiar de nombre y arrepentirse es el caso más previsible de esta funcionalidad. Mientras el
alias siga vigente, el nombre anterior **sigue siendo suyo**, así que puede recuperarlo sin
esperar los 30 días.

```text
Día 0    username: pabloblanco1
Día 10   cambia a «pblanco»    → alias: pabloblanco1 (caduca día 40)
Día 15   recupera «pabloblanco1»
         username: pabloblanco1
         alias:    pblanco      (caduca día 45)
         el alias «pabloblanco1» desaparece: vuelve a ser su nombre
```

Recuperar es una **permuta**: el alias recuperado deja de serlo y el nombre que se abandona
ocupa su lugar. El usuario sigue teniendo un solo alias vigente.

### Qué consume la recuperación

| | Cambio normal | Recuperar un alias propio |
|---|---|---|
| ¿Exige que hayan pasado 30 días? | Sí | **No** |
| ¿Reinicia el plazo de 30 días? | Sí | **Sí** |

La recuperación **esquiva** el límite pero **lo renueva**. Es deliberado: si además
restaurase el plazo anterior, un usuario podría alternar entre dos nombres indefinidamente
—cambiar, recuperar, cambiar, recuperar— y cada vuelta rompería los enlaces que el límite
existe para proteger.

Con esta regla el arrepentimiento está cubierto y el vaivén no.

## Reglas de negocio

- `RN-1` Solo se permite **un cambio cada 30 días**, contados desde el último cambio.
- `RN-1b` **Excepción:** recuperar un alias propio vigente se permite aunque no hayan pasado
  los 30 días. La recuperación renueva el plazo, de modo que después habrá que esperar otros
  30 días para cualquier cambio, **incluida otra recuperación**: la excepción no se encadena
  (ver *La excepción no se encadena*, más abajo).
- `RN-2` El nombre nuevo debe estar libre según `FEAT-USR-033` `RN-1`: ni en uso ni ocupado
  por un alias vigente.
- `RN-3` Debe cumplir el mismo formato y las mismas restricciones que el asignado
  automáticamente.
- `RN-4` Al cambiarlo, el nombre anterior se guarda como alias de esa cuenta, con caducidad a
  **30 días**.
- `RN-5` Un alias vigente **ocupa el nombre**: no está disponible para nadie.
- `RN-6` Un alias caducado no resuelve y no ocupa, aunque su fila siga existiendo.
- `RN-7` Un usuario puede tener varias filas de alias. Con los plazos actuales normalmente
  tendrá una como mucho, pero **el modelo no asume ese máximo**: si alguno de los dos plazos
  cambia, dejaría de cumplirse.
- `RN-8` Cambiar el nombre **no cambia la identidad**: el `UserId` es el mismo y ninguna
  referencia interna usa el nombre.
- `RN-9` Cambiar el nombre exige la cuenta activada (`FEAT-USR-025`).
- `RN-10` Se publica `UsernameChanged` para que los read models que muestran el nombre se
  actualicen. La recuperación publica el mismo evento: para los consumidores es un cambio más.
- `RN-11` Al recuperar un alias propio, **su fila de alias se elimina**: vuelve a ser el
  nombre de la cuenta y no puede ser las dos cosas a la vez.
- `RN-12` La recuperación solo vale sobre **alias propios y vigentes**. Un alias de otra
  persona, o uno caducado, se tratan como cualquier otro nombre.
- `RN-13` Al eliminar una cuenta, su nombre de usuario **no queda libre**: se convierte en
  alias durante 30 días, igual que en un cambio de nombre. Ver la sección siguiente.

`RN-8` es lo que hace que todo esto sea seguro: el nombre de usuario es **presentación y
enrutado**, nunca identidad. Cualquier tabla que lo use como clave foránea rompería con el
primer cambio.

## Flujo principal

1. El usuario propone un nombre nuevo.
2. Se comprueba que hayan pasado 30 días desde el último cambio.
3. Se valida el formato y que no sea un nombre reservado.
4. Se comprueba que esté libre: ni usuario ni alias vigente.
5. En una única transacción:
   - el nombre actual pasa a alias con caducidad a 30 días;
   - si el nombre nuevo era un alias propio, **su fila se elimina**;
   - el nuevo se asigna a la cuenta;
   - se renueva el plazo de 30 días.
6. Se publica `UsernameChanged`.

El paso 5 debe ser atómico. Si el alias se crease sin completar el cambio, el usuario
bloquearía su propio nombre; y al revés, el cambio sin alias rompería los enlaces que la
funcionalidad existe para proteger.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Cambio antes de 30 días | Se rechaza, indicando cuándo podrá hacerlo | `429` con `code: USERNAME_CHANGE_TOO_SOON` y la fecha |
| **Recuperar un alias propio antes de 30 días** | **Se permite** (`RN-1b`) | `200` |
| Recuperar un alias propio **caducado** | Se trata como un nombre cualquiera: sujeto al límite y a que siga libre | Según el caso |
| Nombre ya en uso | Se rechaza | `409` con `code: USERNAME_TAKEN` |
| Nombre ocupado por un alias vigente | Se rechaza **con el mismo código** que el anterior | `409` con `code: USERNAME_TAKEN` |
| Nombre reservado | Se rechaza | `422` con `code: USERNAME_RESERVED` |
| Formato inválido | Se rechaza | `422` con `code: VALIDATION_FAILED` |
| Mismo nombre que ya tiene | No es un cambio: se acepta sin efecto y sin consumir el cupo | `200` |
| Intentar recuperar el alias **de otra persona** | Se rechaza como nombre ocupado | `409` con `code: USERNAME_TAKEN` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |

El caso del alias devuelve el mismo código que el de un nombre en uso **a propósito**:
distinguirlos revelaría que alguien tuvo ese nombre y lo cambió hace menos de un mes.

## Al eliminar la cuenta

Eliminar una cuenta **no libera su nombre de inmediato**. Si lo hiciera, cualquiera podría
registrarlo al día siguiente y heredar todos los enlaces que apuntaban a esa persona, que es
el mismo riesgo de suplantación que el mes de alias evita en un cambio de nombre.

| Qué pasa | Cuándo |
|---|---|
| El nombre de usuario de la cuenta pasa a ser alias | Al eliminarse la cuenta |
| Ese alias caduca | 30 días después |
| Los alias que ya tuviera | Conservan **su propia** caducidad, que nunca está a más de 30 días |
| El nombre queda disponible | Al caducar el alias correspondiente |

### Estos alias bloquean pero no resuelven

Es la diferencia con los de un cambio de nombre:

| | Alias de una cuenta viva | Alias de una cuenta eliminada |
|---|---|---|
| Ocupa el nombre | Sí | Sí |
| Resuelve al perfil | Sí | **No**: la cuenta ya no existe, devuelve `404` |
| Se puede recuperar | Sí, por su titular | No hay titular |
| Caduca a los 30 días | Sí | Sí |
| Lo borra la purga diaria | Sí | Sí |

Un enlace antiguo a una cuenta eliminada da `404` durante ese mes, y solo después puede
llevar a otra persona. El mes no evita el `404` —eso es inevitable— sino que evita el
**cambio silencioso de titular**.

### Cómo se modela sin depender del borrado

Qué se conserva al eliminar una cuenta sigue sin decidirse (`V-4`, `U-3`): puede ser un
borrado real, un borrado lógico o una anonimización. El alias **no debe depender de esa
decisión**.

Por eso `username_alias` lleva:

- `user_id` **anulable**, porque puede no quedar cuenta a la que apuntar;
- `reason` con los valores `USERNAME_CHANGED` y `ACCOUNT_DELETED`.

Un alias con `reason = ACCOUNT_DELETED` bloquea el nombre y nunca resuelve, exista o no la
fila del usuario.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar nombre de usuario | `PUT /me/username` | `changeUsername` |

La respuesta lleva siempre `changeableOn`, también cuando no ha habido cambio.

Y `GET /me/profile` —la lectura que abre «Configuración › Perfil»— devuelve
`usernameChangeableOn`, para que el campo pueda salir ya desactivado sin tener que fallar
primero. `GET /me/context` (`FEAT-USR-027`) lo añadirá cuando exista lo que hoy no existe.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UsernameChanged` | Se cambia el nombre | `Community` (read models) | `userId`, `previousUsername`, `newUsername`, `aliasExpiresAt` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `user` | `username`, `username_changed_at`, `username_reclaimed_at` |
| `username_alias` | `username`, `user_id` (anulable), `reason`, `created_at`, `expires_at` |

Al eliminar una cuenta se **crea** un alias con su nombre y `reason = ACCOUNT_DELETED`. Es un
efecto de `UserDeleted`. El comando de purga (`FEAT-USR-036`) los retira igual que a los
demás cuando caducan: para él no hay diferencia.

Índices: único sobre `username_alias(username)`, e índice sobre `expires_at` para la
resolución y para el comando de purga.

`username_reclaimed_at` guarda cuándo se recuperó por última vez un nombre propio, y es nula
cuando el último cambio fue uno normal. Es lo que impide encadenar la excepción de `RN-1b`:
sin ella el vaivén queda abierto, porque cada recuperación deja un alias que convierte la
vuelta siguiente en otra recuperación.

## Criterios de aceptación

- [x] Cambiar el nombre asigna el nuevo y crea el alias con el anterior.
- [x] El alias caduca 30 días después del cambio.
- [x] Durante ese mes, el nombre anterior resuelve al mismo perfil.
- [x] Durante ese mes, nadie más puede registrar ese nombre.
- [x] Pasado el mes, el nombre anterior deja de resolver.
- [x] Pasado el mes, el nombre queda disponible aunque la fila del alias no se haya borrado.
- [x] Un segundo cambio antes de 30 días se rechaza con `429` e indica la fecha.
- [x] Un nombre ocupado por alias devuelve el mismo error que uno en uso.
- [x] El cambio y la creación del alias son atómicos. *Una sola transacción; no hay prueba que interrumpa su mitad, que exigiría un fallo provocado dentro de ella.*
- [x] Tras el cambio, el `UserId` es el mismo y todas las referencias internas siguen válidas.
- [x] Se publica `UsernameChanged` con el nombre anterior, el nuevo y la caducidad.
- [x] Recuperar un alias propio vigente funciona aunque no hayan pasado 30 días.
- [x] Al recuperarlo, su fila de alias desaparece y el nombre abandonado pasa a ser alias.
- [x] Tras recuperar, el usuario sigue teniendo un solo alias vigente.
- [x] Tras recuperar, hay que esperar otros 30 días para volver a cambiar.
- [x] Alternar entre dos nombres de forma repetida queda bloqueado por el criterio anterior. *No salía gratis: ver «La excepción no se encadena».*
- [x] Intentar recuperar el alias de otra persona devuelve `409`.
- [x] Un alias propio ya caducado no da derecho a saltarse el límite.
- [ ] Al eliminar la cuenta, su nombre de usuario pasa a ser alias y **no queda disponible**. *`FEAT-USR-013` está `BLOCKED`: no hay borrado de cuenta que pueda crear ese alias.*
- [ ] Ese alias caduca 30 días después y solo entonces el nombre vuelve a estar libre. *Lo mismo.*
- [x] Durante ese mes, resolver ese nombre devuelve `404`, no el perfil de la cuenta borrada. *El modelo lo sostiene y se prueba en `FEAT-USR-035`; lo que falta es el borrado que crea el alias, no la regla.*
- [x] Durante ese mes, nadie puede registrar ese nombre. *Igual: un alias con `reason = ACCOUNT_DELETED` bloquea como cualquier otro, tanto al asignar un nombre nuevo como al cambiarlo.*
- [ ] Los alias que la cuenta ya tuviera conservan su propia caducidad. *Depende del borrado (`N-19`).*
- [x] El alias de una cuenta eliminada funciona aunque no quede fila de usuario. *`user_id` es anulable y nada lo sigue.*
- [ ] El comando de purga retira estos alias igual que los demás. *`FEAT-USR-036` no existe todavía. Nada depende de él: la caducidad se comprueba al mirar, no al borrar.*

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-6 | ¿Puede el usuario recuperar su propio alias sin esperar los 30 días? | **Resuelta:** sí, mientras el alias siga vigente. Ver `RN-1b` |
| N-7 | ¿«Una vez al mes» son 30 días corridos o mes natural? | **Resuelta:** 30 días corridos, y la misma cuenta para el alias. Dos plazos distintos dejarían huecos en los que un nombre no está ni reservado ni disponible |
| N-8 | ¿Se avisa al usuario de que su nombre antiguo caducará? | Podría querer recuperarlo |
| N-9 | ¿Qué ocurre con el nombre al eliminar la cuenta? | **Resuelta:** se conserva bloqueado 30 días como alias (`RN-13`) |
| N-17 | ¿Los enlaces antiguos pueden acabar apuntando a otra persona? | **Resuelta:** no durante el mes siguiente al borrado. Pasado ese plazo, sí |
| N-19 | ¿Los alias que ya tuviera una cuenta eliminada deberían extenderse a 30 días desde el borrado? | Hoy conservan su caducidad original, así que pueden liberarse antes que el nombre principal |
| N-10 | ¿Hay histórico de cambios más allá del alias vigente? | **No.** La purga borra la fila de verdad (`FEAT-USR-036` `RN-4c`), así que pasados 30 días no queda constancia de qué nombre tuvo antes una cuenta. Si alguna vez hiciera falta para moderación, habría que añadirlo aparte |

`N-19` es menor pero real: si alguien cambió de nombre hace 25 días y borra su cuenta hoy, su
alias antiguo queda libre en 5 días mientras el principal tarda 30. Uniformarlo sería más
predecible, a costa de bloquear nombres algo más de tiempo.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-24). `PUT /me/username` entero: cambio, reserva del
nombre anterior, recuperación, plazo y evento. Una columna nueva —`username_reclaimed_at`— y
su migración; el resto del modelo ya estaba.

Falta **la mitad que depende del borrado de cuenta** (`RN-13`): `FEAT-USR-013` está `BLOCKED`,
así que nada crea todavía un alias con `reason = ACCOUNT_DELETED`. El modelo lo admite desde
el principio y `FEAT-USR-035` ya prueba que un alias así bloquea y no resuelve, de modo que
cuando exista el borrado no habrá que tocar esto: solo crear la fila.

### La excepción no se encadena

Es lo que descubrió la prueba, y el hueco era real. `RN-1b` dice que recuperar un alias propio
esquiva el plazo, y eso, implementado literalmente, **no bloquea el vaivén que la propia
ficha dice bloquear**: cada recuperación deja como alias el nombre que se abandona, así que
la vuelta siguiente vuelve a ser una recuperación, esquiva el plazo otra vez, y así
indefinidamente.

La regla que faltaba: **el plazo que la recuperación renueva vale para cualquier cambio,
incluida otra recuperación.** Deshacer un cambio es el caso previsible que la excepción
existe para cubrir; deshacer un «deshacer» ya es alternar.

Para saberlo hace falta recordar si el último cambio fue una recuperación, y de ahí
`username_reclaimed_at`: se escribe al recuperar, se borra al cambiar de forma normal.
Conviene que sea un dato y no una deducción — la fecha del alias y la del cambio coinciden en
los dos casos, así que no hay forma de distinguirlos mirando lo que ya había.

### Recuperar es un método aparte, no un parámetro

`reclaimUsername()` no es `changeUsername($nombre, $ahora, saltarPlazo: true)`. Un parámetro
que desactiva una comprobación acaba pasándose desde donde no debe, y aquí lo que desactiva
es el único límite que impide retener nombres ajenos.

### Un alias y un nombre en uso responden lo mismo

`409 USERNAME_TAKEN` en los dos casos, a propósito. Distinguirlos —«ese nombre está
reservado»— contaría que alguien lo tuvo y lo dejó hace menos de un mes, que es información
sobre una persona concreta y no sobre la disponibilidad de una palabra.

Un nombre **reservado** sí se distingue, con `422 USERNAME_RESERVED`: ahí no hay nadie a
quien proteger, y quien lo pide merece saber que no es cuestión de esperar.

### Pedir el nombre que ya se tiene no gasta el cupo

Se acepta sin efecto y sin crear ningún alias. La pantalla comparte el «Guardar» con el resto
de la pestaña, así que guardar dos veces sin tocar este campo es lo normal, y no puede costar
treinta días.

### `429` con la fecha, y la fecha también antes de fallar

`USERNAME_CHANGE_TOO_SOON` lleva `availableOn` como miembro de extensión de RFC 9457, que es
lo que justifica el `429` frente a un conflicto: un cliente que no pueda saber la fecha dejará
el formulario activo y permitirá reintentar mañana, y pasado.

Mejor aún es no fallar: `GET /me/profile` devuelve `usernameChangeableOn`, de modo que el
campo puede salir desactivado desde que se abre la pantalla.
