---
id: FEAT-WRK-010
title: Crear un enlace público para leer y corregir sin sesión
context: Work
concept: PublicLink
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-23 (enlace público de corrección)
  - conversation:2026-09-25 (redacción de la ficha, pendiente desde FEAT-FBK-008)
endpoints:
  - POST /works/{workId}/public-links
  - GET /works/{workId}/public-links
  - DELETE /public-links/{publicLinkId}
  - GET /public/{token}
  - GET /public/{token}/chapters/{chapterId}
events: []
depends_on: [FEAT-WRK-014, FEAT-WRK-016]
updated: 2026-09-25
---

# FEAT-WRK-010 — Enlace público para leer y corregir sin sesión

## Resumen

El autor genera una **URL** de su obra y la reparte fuera de la plataforma. Quien la recibe
lee el texto y responde el cuestionario **sin registrarse**.

Esta ficha cubre la mitad del autor: **crear el enlace, configurarlo, verlo y revocarlo**, y
servir lo que hay detrás. Lo que ocurre cuando alguien corrige por él es
[`FEAT-FBK-008`](../feedback/FEAT-FBK-008-public-link-correction.md).

## Por qué existe

Por lo que explica `FEAT-FBK-008` y no se repite aquí: es la **válvula de seguridad de la
economía de créditos**. Un autor sin saldo no recibe correcciones, y para conseguir saldo
necesita textos que corregir. El enlace público rompe ese círculo por fuera, sin tocar la
masa de créditos y sin depender de nadie más que de la agenda del autor.

Lo que sí toca decir aquí es lo que el autor está haciendo al generarlo: **cambia privacidad
por feedback**. La plataforma existe para custodiar obra inédita, así que la decisión es suya
—y los controles para deshacerla tienen que ser suyos también.

## El token

- `RN-1` El token es **opaco, largo y aleatorio**. Es la única credencial que protege obra
  inédita, así que no se deriva de nada: ni del identificador de la obra, ni del autor, ni de
  una fecha.
- `RN-2` Se guarda **cifrado**, nunca en claro, y **se enseña una sola vez**: en la respuesta
  que lo crea. Después no hay forma de recuperarlo.
- `RN-3` No se escribe en ningún registro ni en ninguna traza.

`RN-2` tiene un coste evidente y conviene reconocerlo: **el autor que pierde la URL no puede
volver a copiarla**, y su única salida es crear otro enlace y repartirlo de nuevo. La
alternativa —guardarlo en claro para poder enseñarlo siempre— es más cómoda y deja una
credencial legible en la base de datos y en cada copia de seguridad.

Se elige lo incómodo porque **crear otro enlace cuesta un clic** y una credencial en claro no
se puede deshacer una vez escrita. Y porque el caso real —repartirlo a tu grupo de escritura—
se resuelve copiando la URL en el momento, que es cuando se está mirando.

## Qué configura el autor

- `RN-4` Un **tope de correcciones**, con **10 por defecto**
  ([`FEAT-FBK-008`](../feedback/FEAT-FBK-008-public-link-correction.md) `RN-4`, `C-36`). Entre
  1 y 100.
- `RN-5` Una **caducidad opcional**. Sin ella, el enlace dura hasta que se revoque.
- `RN-6` Una **etiqueta opcional** para distinguirlos: «taller de los martes», «mi hermana».
  No la ve quien abre el enlace; es una nota del autor para sí mismo.

El tope y la caducidad son dos formas de la misma precaución, y hacen falta las dos: el tope
limita el daño si la URL circula más de la cuenta, la caducidad limita el tiempo durante el
cual puede circular.

## Reglas de negocio

- `RN-7` Solo **el autor de la obra** crea, ve y revoca sus enlaces.
- `RN-8` Una obra puede tener **varios enlaces a la vez**, cada uno con su tope y su
  caducidad. Repartir uno por grupo es lo que permite revocar solo el que se fue de las manos.
- `RN-9` La **revocación es inmediata e irreversible**. Un enlace revocado no se reactiva: se
  crea otro.
- `RN-10` Un enlace revocado o caducado **no sirve nada**, ni siquiera la portada de la obra.
  Responde `410`, no `404`: quien lo tiene sabe que existió, y fingir lo contrario no protege
  nada.
- `RN-11` Las páginas servidas por el enlace llevan **`noindex`** (`FEAT-FBK-008` `RN-5`).
- `RN-12` El enlace sirve **la obra entera**: sus capítulos visibles y el cuestionario. No se
  reparte un enlace por capítulo, porque lo que se reparte es un texto para leer.
- `RN-13` Solo se sirven los capítulos **visibles** de la obra
  ([`FEAT-WRK-008`](FEAT-WRK-008-work-and-chapter-visibility.md)). Un capítulo que el autor ha
  ocultado lo está también aquí: el enlace abre una puerta, no la levanta.
- `RN-14` El enlace **no depende del estado de la obra**. Funciona sobre un borrador, que es
  justamente el caso: se reparte para conseguir feedback antes de publicar.
- `RN-15` Una obra **bloqueada por moderación** no se sirve por enlace público
  ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md)). El bloqueo es de la obra, no
  de una de sus puertas.
- `RN-16` Hay **limitación de frecuencia** por origen al abrir un enlace. Sin ella, la URL
  opaca se puede tantear.

`RN-13` es la que más fácil sería olvidar, y la que convertiría esta funcionalidad en una
fuga: el autor que oculta un capítulo no espera que siga saliendo por otra puerta suya.

`RN-15` sigue el mismo razonamiento. Si el bloqueo solo cerrara el catálogo, bastaría con
tener la URL para saltárselo.

## Flujo principal

1. El autor abre su obra y genera un enlace.
2. Elige tope, caducidad y etiqueta, o acepta lo que viene por defecto.
3. La respuesta trae **la URL completa, una sola vez**.
4. La reparte fuera de la plataforma.
5. Cuando quiere, la revoca.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La obra no es suya | Se rechaza | `404` |
| Tope fuera de rango | Se rechaza | `422` |
| Caducidad en el pasado | Se rechaza | `422` |
| Revocar un enlace ya revocado | No hace nada, no falla | `204` |
| Abrir un enlace revocado o caducado | No se sirve nada | `410` |
| Abrir un enlace de una obra bloqueada | No se sirve nada | `410` |
| Abrir un enlace **con sesión iniciada** | Va al flujo normal | `409` con la obra |
| Demasiadas aperturas desde el mismo origen | Limitación de frecuencia | `429` |

La fila de la sesión iniciada es `FEAT-FBK-008` `RN-1` y merece explicarse: sin ella, el autor
podría pegar el enlace en su muro y conseguir que usuarios registrados le corrigiesen gratis
—él se ahorraría los créditos y ellos perderían los suyos—. La respuesta lleva el
identificador de la obra para que el cliente pueda llevarle al flujo normal, que es lo que le
conviene: ahí sí cobra.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Crear un enlace | `POST /works/{workId}/public-links` | `createPublicLink` |
| Ver mis enlaces de una obra | `GET /works/{workId}/public-links` | `listPublicLinks` |
| Revocar | `DELETE /public-links/{publicLinkId}` | `revokePublicLink` |
| Abrir el enlace | `GET /public/{token}` | `getPublicCorrectionPage` |

Las tres primeras exigen sesión y son del autor. La cuarta **no exige sesión**, y es la única
de la obra que no la exige.

El listado **no devuelve el token** (`RN-2`): devuelve el identificador del enlace, su
etiqueta, su tope, lo que le queda y su estado. Lo que se revoca es el enlace, no la URL.

## Contrato publicado

`Feedback` necesita saber qué abre un token antes de aceptar una corrección por él, así que
`Work` publica:

```text
src/Work/PublicLink/Application/Contract/PublicLinkAccess.php
```

Devuelve, para un token, **qué obra abre, si sigue siendo utilizable y cuál es su tope**. Nada
más. Pregunta, no manda: no crea correcciones, no consume cupo, no revoca nada
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)).

**Quién cuenta las correcciones gastadas es `Feedback`**, que es quien las tiene. Este contexto
publica el tope; contar aquí exigiría una segunda copia del número que se quedaría vieja, o
una llamada de vuelta que un contrato no puede hacer.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `work_ctx.public_link` | `work_id`, `token_hash`, `label`, `max_corrections`, `created_at`, `expires_at`, `revoked_at` |

Índice único sobre `token_hash`, que es por donde se busca siempre. Índice sobre `work_id`
para el listado del autor.

## Criterios de aceptación

- [x] El autor genera un enlace y recibe la URL completa una sola vez.
- [x] El token no se puede recuperar después, ni en el listado ni en ninguna otra respuesta.
- [x] Quien no es el autor no puede crear, ver ni revocar enlaces de esa obra.
- [x] Un enlace sirve la obra y sus capítulos visibles sin ninguna sesión.
- [x] Un capítulo oculto no se sirve por el enlace.
- [x] Revocar deja el enlace inservible de inmediato.
- [ ] Un enlace caducado responde `410`. **El código lo contempla y la prueba
  funcional no puede escribirlo**: haría falta un reloj que avance dentro de
  una petición HTTP, y el arnés funcional usa el real. La caducidad se
  comprueba en el mismo sitio que la revocación —`PublicLink::isUsableAt()`—,
  que sí está probada de punta a punta.
- [x] Una obra bloqueada por moderación no se sirve por enlace.
- [x] Un usuario con sesión iniciada no obtiene el formulario anónimo.
- [x] Hay limitación de frecuencia al abrir enlaces.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-30 | ¿Se avisa al autor cuando su enlace agota el tope? | Hoy se entera al mirar; un aviso encajaría en `FEAT-NOT-001` |
| W-31 | ¿Conviene un tope de enlaces vivos por obra? | `RN-8` permite varios y no acota cuántos |
| W-32 | ¿Debería el enlace poder abrir **un solo capítulo**? | `RN-12` dice que no; el caso «solo quiero opinión del primero» existe |

Resueltas: el token se guarda cifrado y se enseña una vez (`RN-2`), el tope por defecto es
**10** (`RN-4`, heredado de `C-36`), y el enlace es **por obra** y no por capítulo (`RN-12`).

## Estado

**Especificación:** `APPROVED` (2026-09-25). Redactada al abordar
[`FEAT-FBK-008`](../feedback/FEAT-FBK-008-public-link-correction.md), que la declaraba como
dependencia y no la tenía. Las preguntas abiertas que quedan no afectan al modelo, al contrato
ni a ninguna regla de negocio.

**Implementación:** `DONE` (2026-09-25), junto a
[`FEAT-FBK-008`](../feedback/FEAT-FBK-008-public-link-correction.md), que la
declaraba como dependencia.

Tres cosas que la ficha no preveía y que la implementación obligó a decidir:

- **la portada y el texto son dos endpoints**, no uno. Está explicado arriba;
- **las rutas públicas van en `access_control` con `PUBLIC_ACCESS`, no en un
  cortafuegos sin seguridad.** Parece un detalle de configuración y no lo es:
  un cortafuegos con `security: false` ni siquiera *lee* la sesión, y sin
  leerla no hay forma de mandar al flujo normal a quien ya tiene cuenta. La
  regla que tapa la fuga (`FEAT-FBK-008` `RN-1`) dependía de esa línea;
- **el tope lo publica este contexto y lo cuenta `Feedback`.** El enlace es de
  `Work`, pero las correcciones están allí; un contador aquí sería una segunda
  copia del número, y una de las dos se quedaría vieja. El contrato entrega el
  tope, y quien lo aplica es quien puede contar de verdad.

**Falta** lo que la ficha dejó como preguntas abiertas: el aviso al autor
cuando su enlace agota el tope (`W-30`) y el tope de enlaces vivos por obra
(`W-31`).
