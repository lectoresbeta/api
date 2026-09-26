---
id: FEAT-USR-038
title: Ajustes de privacidad del usuario
context: User
concept: Privacy
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Privacidad» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/privacy-settings
  - PUT /me/privacy-settings
events: [PrivacySettingsChanged]
depends_on: [FEAT-USR-014, FEAT-COM-011]
updated: 2026-09-24
---

# FEAT-USR-038 — Ajustes de privacidad del usuario

## Resumen

Cuatro ajustes que el usuario controla desde la pestaña «Privacidad»:

| Ajuste | Tipo |
|---|---|
| ¿Quién puede ver mi perfil? | `Todos` / … (`S-13`) |
| ¿Quién puede comentar mis textos? | `Todos` / … (`S-13`) |
| ¿Quién puede mandarme mensajes? | `Todos` / … (`S-13`) |
| Visibilidad de actividad | Sí / No |

**No son preferencias de visualización: son reglas de autorización.** Esa es la única forma
correcta de leerlos, y determina dónde se implementan.

## Por qué se implementan en el backend

Es la clase de ajuste que acaba resolviéndose ocultando botones en el cliente. No basta:

- un perfil restringido tiene que responder `403` o `404` **aunque se llame al endpoint
  directamente**;
- un usuario que no admite mensajes tiene que ser rechazado en el `POST`, no solo perder el
  formulario;
- quien no puede comentar tiene que ser rechazado al enviar, no solo no ver el botón.

`AGENTS.md` lo exige explícitamente: *«Never rely only on frontend behavior for
permissions»*.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Consultar y modificar **sus** ajustes | Es el titular |
| Cualquier otro | — | Los ajustes ajenos **no se exponen** |

Que no se expongan importa: saber que alguien tiene el perfil restringido ya es información
sobre esa persona.

## Los tres desplegables

**Decidido** (`S-13`): los tres admiten los mismos tres valores.

| Valor | Quién |
|---|---|
| `EVERYONE` | Cualquiera |
| `FOLLOWERS` | Solo quienes le siguen |
| `NOBODY` | Nadie |

Que los tres compartan enum es deliberado: son la misma pregunta —¿hasta dónde llega esto?—
aplicada a tres cosas distintas. Un enum por ajuste multiplicaría por tres el trabajo de la
autorización sin añadir expresividad.

`NOBODY` significa cosas muy distintas en cada uno, y conviene verlas juntas:

| Ajuste en `NOBODY` | Efecto real |
|---|---|
| Ver mi perfil | El perfil deja de resolver para todos. **La cuenta se vuelve invisible** |
| Comentar mis textos | Nadie puede corregir ninguna obra, sea cual sea su modalidad |
| Mandarme mensajes | Bandeja cerrada |

El primero es el que más sorprende y merece confirmación (`S-41`): una cuenta invisible sigue
publicando obras que aparecen en el catálogo con un autor que no se puede abrir.

## Reglas de negocio

- `RN-1` Los ajustes son **del usuario**, no de sus obras ni de sus publicaciones.
- `RN-2` El ajuste global es un **techo**: una obra puede ser más restrictiva que el perfil,
  **nunca más permisiva** (`S-14`, resuelta).
- `RN-3` Cambiar un ajuste **no reescribe el pasado**: no borra mensajes ya recibidos ni
  comentarios ya publicados. Afecta a lo que ocurra a partir de ese momento.
- `RN-4` Todo ajuste tiene un **valor por defecto explícito** al crear la cuenta:
  `EVERYONE` en los tres.
- `RN-4b` Los tres ajustes usan el mismo enum: `EVERYONE`, `FOLLOWERS`, `NOBODY` (`S-13`).
- `RN-5` Un usuario bloqueado ([`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md)) no
  gana acceso por ninguna combinación de estos ajustes. El bloqueo es más fuerte.
- `RN-6` Restringir «quién puede comentar» **no interrumpe las correcciones en curso**: quien
  ya empezó, entrega y cobra.

`RN-2` no es un detalle de implementación: es la regla que evita que un ajuste de privacidad
sea ignorado silenciosamente por otro más permisivo.

## El conflicto con la modalidad de acceso de cada obra

Ya existía un eje **por obra**, `BetaReaderAccessMode` (`PUBLIC` / `ON_REQUEST` / `PRIVATE`),
que decide quién puede acceder a una obra concreta para corregirla. El nuevo ajuste global
responde casi a la misma pregunta con otro alcance.

| | Ajuste global | Modalidad de la obra |
|---|---|---|
| Alcance | Todo lo que escribe el usuario | Una obra |
| Pregunta | ¿Quién puede comentar mis textos? | ¿Quién puede ser lector beta de esta obra? |

**Decidido (`S-14`): el ajuste global es un techo.**

| Ajuste global | Modalidad de la obra | Quién puede comentar |
|---|---|---|
| `Todos` | `PUBLIC` | Cualquiera |
| `Todos` | `ON_REQUEST` | Quien lo solicite y el autor acepte |
| `Todos` | `PRIVATE` | Solo los invitados |
| **Restrictivo** | `PUBLIC` | **El global.** La restricción alcanza a todas las obras |
| **Restrictivo** | `PRIVATE` | El de la obra, que ya es más estrecho |

En una frase: **el perfil pone el máximo, la obra puede bajarlo.**

Es la única lectura que mantiene el ajuste como ajuste de privacidad. Si la obra pudiera
ganarle, el ajuste global sería una recomendación, y quien lo endureciera creería haber
cerrado una puerta que sigue abierta en cada obra publicada como `PUBLIC`.

Para la implementación: el ajuste global **no reescribe** la modalidad de cada obra. Las dos
se guardan como están y **la autorización evalúa las dos**, quedándose con la más
restrictiva. Reescribir las obras al cambiar el ajuste haría imposible volver atrás: al
relajar el perfil, nadie sabría qué modalidad tenía antes cada obra.

### La consecuencia en créditos

El sistema **no retiene créditos**
([`decision:0006`](../../decisions/0006-credit-system.md)), así que endurecer el ajuste no
deja saldo inmovilizado que liberar. Queda una pregunta más simple: **qué pasa con quien está
corrigiendo ahora mismo**.

Lo coherente con `RN-3` es que **quien ya empezó, termina**: entrega su corrección y cobra el
precio anotado. El ajuste afecta a las correcciones nuevas.

La alternativa —cortar las correcciones en curso— destruiría trabajo real de un tercero por
una decisión del autor, y el tercero no ha hecho nada mal. Ver `S-36`.

## Alcance de «¿Quién puede ver mi perfil?»

El perfil no es una pantalla: es una URL pública, el nombre del autor en cada tarjeta del
catálogo y el avatar junto a cada comentario.

| Elemento | Con el perfil restringido |
|---|---|
| `/profile/{username}` | Ver abajo |
| Nombre del autor en el catálogo | Por decidir (`S-15`) |
| Autoría de comentarios ya publicados | No se reescribe (`RN-3`) |
| Obras de ese autor | Son de `Work`, no del perfil |

**Qué devolver es una decisión de seguridad, no de estilo.** Un `403` confirma que la cuenta
existe; un `404` no dice nada. Para un ajuste cuya razón de ser es no ser encontrado,
`404` es la respuesta coherente, aunque sea menos informativa.

## «Visibilidad de actividad» está sin definir

La etiqueta no dice qué es «actividad». Los candidatos tienen consecuencias muy distintas:

| Candidato | Consecuencia de ocultarlo |
|---|---|
| Publicaciones del muro | Desaparecen del muro de sus seguidores |
| Obras que lee | Razonable y poco problemático |
| Correcciones que ha hecho | Afecta a `FEAT-FBK-010` y al contador público del perfil (`U-17`) |
| A quién sigue | Afecta a los contadores de seguidos |

Hasta que `S-16` se responda, esta parte de la ficha no puede especificarse.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar mis ajustes | `GET /me/privacy-settings` | `getMyPrivacySettings` |
| Modificarlos | `PUT /me/privacy-settings` | `updateMyPrivacySettings` |

Se devuelven **solo al titular**. No hay endpoint para consultar los ajustes de otro usuario:
el efecto de esos ajustes se ve en las respuestas de los demás endpoints, no preguntando por
ellos.

Forman parte de `GET /me/context`
([`FEAT-USR-027`](FEAT-USR-027-session-context.md)) solo si el cliente necesita pintar
estados; el filtrado real lo hace el servidor en cada operación.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `PrivacySettingsChanged` | Al guardar | `userId`, ajustes modificados |

Lo consumen los read models que dependen de la visibilidad (muro, catálogo, perfil). **No
lleva datos del perfil**, solo qué cambió.

**Consume**

Ninguno.

## Modelo de datos afectado

`UserPrivacySettings`: `userId`, `profileVisibility`, `commentPermission`,
`messagePermission`, `activityVisible`.

Un registro por usuario, creado con los valores por defecto al crear la cuenta (`RN-4`): un
ajuste ausente no puede interpretarse como «todo permitido».

## Criterios de aceptación

- [x] Cada ajuste se aplica en el backend, no solo en la interfaz.
- [x] Llamar directamente a un endpoint restringido devuelve error, no datos.
- [ ] Con el perfil restringido, `/profile/{username}` responde de forma que **no confirme la
      existencia de la cuenta**. *Ese endpoint es [`FEAT-USR-014`](FEAT-USR-014-view-public-profile.md)
      y todavía no existe. Lo que sí se comprueba es lo único que hoy expone una cuenta: con el
      perfil en `NOBODY` deja de aparecer al buscar a quién invitar.*
- [ ] Un usuario bloqueado no gana acceso por ningún ajuste. *[`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md) no existe: no hay bloqueos que probar.*
- [x] Cambiar un ajuste no borra ni oculta retroactivamente lo ya publicado.
- [x] Los ajustes de un usuario no son consultables por otro.
- [x] Una cuenta nueva tiene valores por defecto explícitos.
- [x] Entre ajuste global y modalidad de obra se aplica el más restrictivo.
- [x] Endurecer el ajuste global **no modifica** la modalidad guardada de cada obra.
- [x] Relajarlo después devuelve a cada obra su modalidad original.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-36** | Al endurecer el ajuste global, ¿puede terminar quien ya está corrigiendo? | Cortarlo destruiría trabajo real de un tercero |
| **S-16** | ¿Qué es «actividad»? | Sin ello, el cuarto ajuste no se puede especificar |
| S-41 | Con el perfil en `NOBODY`, ¿qué se ve del autor en el catálogo? | Una cuenta invisible sigue publicando obras |
| **S-15** | Con el perfil restringido, ¿desaparece el autor del catálogo? ¿`403` o `404`? | Un `403` confirma que la cuenta existe |
| S-20 | ¿«Seguidores» incluye a los lectores beta con acceso concedido? | Un LB no tiene por qué seguir al autor |
| S-21 | ¿Estos ajustes afectan a `Guest`, o el catálogo ya es público para todos? | Ligado a `L-8` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `S-13` resuelta: `EVERYONE` / `FOLLOWERS` / `NOBODY`. `S-16`
queda como detalle de qué se considera «actividad».

**Implementación:** `PARTIAL` (2026-09-24). Ni tabla nueva: `user_privacy_settings` estaba en
el esquema desde el andamiaje. La migración que acompaña a este cambio solo **rellena** las
cuentas anteriores, para que la invariante de `RN-4` —ninguna fila falta— sea cierta y no
aspiracional.

### Lo que hace

- `GET` y `PUT /me/privacy-settings`, solo del titular. El `PUT` es **parcial**: lo que no se
  envía se queda como estaba, porque la pantalla mueve un desplegable cada vez.
- Una cuenta nace con los tres en `EVERYONE`, escritos **en la misma transacción que la
  cuenta**. Una cuenta sin ajustes, aunque fuese un segundo, es una cuenta cuya privacidad
  alguien tiene que suponer.
- Un valor desconocido se rechaza con `UNKNOWN_AUDIENCE`. Es el único sitio del backend donde
  interpretar sería peor en las dos direcciones a la vez.
- **El techo funciona** (`RN-2`, `S-14`): con `commentPermission` en `NOBODY` no se puede
  corregir ninguna obra propia, sea cual sea su modalidad, **ni siquiera quien ya tiene acceso
  concedido** — lo que se cierra es la puerta de comentar, no la de entrar. Y relajarlo
  devuelve a cada obra la modalidad que su autor eligió, porque endurecerlo no reescribió
  ninguna.
- `profileVisibility` en `NOBODY` retira a la cuenta del buscador de a quién invitar
  ([`FEAT-RDG-006`](../reading/FEAT-RDG-006-find-beta-readers.md)), que es hoy el único sitio
  donde una cuenta se puede encontrar.
- Publica `PrivacySettingsChanged` con los tres ajustes y nada del perfil.

### `FOLLOWERS` ya significa lo que dice

Durante un tiempo se comportó como `NOBODY`, y era la respuesta correcta: nadie podía seguir a
nadie, así que el conjunto de seguidores de cualquier autor estaba vacío. Con
[`FEAT-COM-010`](../community/FEAT-COM-010-subscribe-to-author.md) dejó de serlo, y **cambió
un solo sitio** —que es la razón de que el contrato devuelva un booleano en vez de entregar el
ajuste—: `CheckAuthorAudience` y `VisibleProfile` consultan la copia del grafo que `User`
mantiene con los hechos de `Community`.

Esa copia hace las dos audiencias **consistentes en diferido**: entre seguir a alguien y ver
su perfil, o poder comentar sus textos, pasa lo que tarde la cola.

> **Lo que hay que decidir** (`C-22`): seguir es **unilateral**, así que «solo mis seguidores»
> significa en la práctica **«cualquiera que pulse Seguir»**. Quien restringe su perfil a
> `FOLLOWERS` probablemente espera algo más fuerte. Las dos salidas —que seguir a una cuenta
> restringida requiera **aprobación**, o que `FOLLOWERS` signifique **seguimiento mutuo**— son
> funcionalidades nuevas, no un ajuste de esta. Mientras se decide, el ajuste hace
> literalmente lo que promete.

Lo que sí está garantizado, y probado: seguir a alguien **no abre lo que ha cerrado**. Con
`NOBODY` no ve el perfil ni comenta nadie, seguidor o no. El seguimiento decide quién entra en
una audiencia, no cuál eligió su titular.

### Qué falta

- **«Visibilidad de actividad» no se expone**, y no por olvido: la etiqueta no dice qué es
  «actividad» (`S-16`) y los candidatos tienen consecuencias muy distintas. La columna existe
  y nada la lee; ofrecer un interruptor que no hace nada sería peor que no ofrecerlo, porque
  alguien lo activaría y se creería protegido.
- `messagePermission` se guarda y **todavía no se aplica**: la mensajería
  ([`FEAT-COM-011`](../README.md)) no existe. No es un agujero — no hay nada que proteger
  mientras no se pueda mandar un mensaje.
- El perfil público (`FEAT-USR-014`) y el bloqueo (`FEAT-COM-034`) no existen, así que dos
  criterios de aceptación no se pueden comprobar.
- `S-15` sigue abierta: con el perfil restringido, qué se ve del autor en el catálogo.
