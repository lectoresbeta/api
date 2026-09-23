---
id: FEAT-USR-038
title: Ajustes de privacidad del usuario
context: User
concept: Privacy
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Privacidad» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/privacy-settings
  - PUT /me/privacy-settings
events: [PrivacySettingsChanged]
depends_on: [FEAT-USR-014, FEAT-COM-011]
updated: 2026-09-22
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

## Reglas de negocio

- `RN-1` Los ajustes son **del usuario**, no de sus obras ni de sus publicaciones.
- `RN-2` El ajuste global es un **techo**: una obra puede ser más restrictiva que el perfil,
  **nunca más permisiva** (`S-14`, resuelta).
- `RN-3` Cambiar un ajuste **no reescribe el pasado**: no borra mensajes ya recibidos ni
  comentarios ya publicados. Afecta a lo que ocurra a partir de ese momento.
- `RN-4` Todo ajuste tiene un **valor por defecto explícito** al crear la cuenta.
- `RN-5` Un usuario bloqueado ([`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md)) no
  gana acceso por ninguna combinación de estos ajustes. El bloqueo es más fuerte.
- `RN-6` Restringir «quién puede comentar» puede dejar **accesos de lector beta vigentes sin
  efecto**. Eso tiene consecuencias en créditos: ver abajo.

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

Con la reserva previa
([`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md)), conceder
acceso a un lector beta **retiene créditos del autor**. Si después el autor restringe quién
puede comentar y ese lector deja de poder hacerlo, hay **una retención que ya no se va a
usar**.

Dejarla viva inmoviliza saldo indefinidamente; liberarla sin avisar deja al lector con un
trabajo a medias que ya no podrá entregar.

Lo coherente con `RN-3` es que **los accesos ya concedidos se respeten** y el ajuste solo
afecte a los nuevos: quien estaba a medio corregir termina, su corrección se entrega y la
retención se consume como estaba previsto.

La alternativa —revocar los accesos vigentes— obliga a tres cosas a la vez: liberar la
retención, avisar al lector de que su trabajo ya no sirve y decidir qué pasa con un borrador
a medias. Ver `S-36`.

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

- [ ] Cada ajuste se aplica en el backend, no solo en la interfaz.
- [ ] Llamar directamente a un endpoint restringido devuelve error, no datos.
- [ ] Con el perfil restringido, `/profile/{username}` responde de forma que **no confirme la
      existencia de la cuenta**.
- [ ] Un usuario bloqueado no gana acceso por ningún ajuste.
- [ ] Cambiar un ajuste no borra ni oculta retroactivamente lo ya publicado.
- [ ] Los ajustes de un usuario no son consultables por otro.
- [ ] Una cuenta nueva tiene valores por defecto explícitos.
- [ ] Entre ajuste global y modalidad de obra se aplica el más restrictivo.
- [ ] Endurecer el ajuste global **no modifica** la modalidad guardada de cada obra.
- [ ] Relajarlo después devuelve a cada obra su modalidad original.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-36** | Al endurecer el ajuste global, ¿se respetan los accesos ya concedidos? | Si se revocan, hay retenciones que liberar y trabajo a medias que se pierde |
| **S-13** | ¿Qué opciones tienen los desplegables? | Definen enums de autorización |
| **S-16** | ¿Qué es «actividad»? | Sin ello, el cuarto ajuste no se puede especificar |
| **S-15** | Con el perfil restringido, ¿desaparece el autor del catálogo? ¿`403` o `404`? | Un `403` confirma que la cuenta existe |
| S-20 | ¿«Seguidores» incluye a los lectores beta con acceso concedido? | Un LB no tiene por qué seguir al autor |
| S-21 | ¿Estos ajustes afectan a `Guest`, o el catálogo ya es público para todos? | Ligado a `L-8` |

## Estado

**Especificación:** `DRAFT`. `S-14` resuelta: el ajuste global es un techo. Quedan `S-13`
—sin las opciones de los desplegables no hay enums— y `S-16`, que define qué es «actividad».

**Implementación:** `TODO`.
