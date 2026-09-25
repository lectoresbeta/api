---
id: FEAT-USR-015
title: Configurar información de la página de autor (bio, foto, referencias)
context: User
concept: AuthorPage
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/my-profile.md
  - docs/ui/settings.md
  - conversation:2026-09-25
endpoints:
  - updateAuthorLinks
events: []
depends_on: [FEAT-USR-008, FEAT-USR-014, FEAT-USR-028, FEAT-USR-029]
updated: 2026-09-25
---

# FEAT-USR-015 — La información de la página de autor

## La decisión: la página de autor **es** el perfil

`P-5` llevaba abierta desde `docs/ui/my-profile.md` y era la que importaba: ¿«Mi perfil» y la
«página de autor» son la misma pantalla?

**Sí.** Y con eso cae `S-5` detrás: la «bio» de la página de autor es la descripción que ya
existe, un solo campo y un solo límite.

La alternativa —dos pantallas, con su propia biografía larga, su propia foto y sus propias
referencias— habría sido más expresiva y habría costado **dos perfiles que mantener**: dos
textos que envejecen por separado y dos sitios donde la misma persona se describe de forma
distinta. Quien lee un perfil no quiere saber cuál de los dos es el bueno.

## Qué faltaba de verdad

De lo que la ficha nombra —«bio, foto y referencias»— dos tercios ya estaban:

| | Dónde vive | Desde |
|---|---|---|
| Bio | `description` del perfil | `FEAT-USR-008` |
| Foto | Avatar y portada | `FEAT-USR-037`, `FEAT-USR-028` |
| Obras publicadas | `published_book` | `FEAT-USR-029` |
| **Referencias** | **Esta ficha** | — |

## Reglas de negocio

- `RN-1` Las referencias son pares **etiqueta + dirección**, en el orden en que su dueño las
  pone. El orden es suyo: quien pone su web primero y su cuenta de fotos después lo ha
  decidido.
- `RN-2` **Se sustituyen enteras.** Lo que el formulario manda es «estas son mis
  referencias», no «he añadido una», igual que con las temáticas de una obra. La lista vacía
  las quita todas.
- `RN-3` **Solo `http` y `https`.** Es la regla de seguridad de la ficha: la dirección la
  escribe un usuario y la pulsa cualquiera que abra su perfil, así que un `javascript:` o un
  `data:` sería algo que ejecuta lo que escribió un extraño.

  La regla vive en `Shared\Domain\ValueObject\WebAddress` y la comparte con el enlace de
  compra de `FEAT-USR-029`. Escrita dos veces, una de las dos copias se queda sin el día que
  alguien toque los esquemas permitidos.

  **El servidor no visita la dirección**: comprobar que la página existe convertiría cada
  guardado en una petición saliente hacia donde diga quien la escribe.
- `RN-4` **Una referencia sin etiqueta se rechaza**, y no se rellena con su dirección. Un
  enlace que no dice a dónde lleva es el que nadie pulsa, o el que se pulsa por error; y poner
  la URL como texto visible invita justamente a disfrazar el destino.
- `RN-5` Hay un tope de **seis**. Un perfil con veinte enlaces deja de ser una página de autor
  y pasa a ser un directorio de enlaces, que es otra cosa y atrae a quien quiere justamente
  eso.
- `RN-6` La etiqueta se **sanea**, como cualquier texto que escribe alguien y se pinta a otro.
  El marcado se retira en vez de rechazarse: decirle a quien pegó desde otro sitio que «su
  texto lleva HTML» no ayuda a nadie.
- `RN-7` Son **públicas**, como el resto del perfil, y se sirven con él.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Guardar mis referencias | `PUT /api/v1/me/author-links` | `updateAuthorLinks` |

**No hay `GET /authors/{userId}/page` ni `PUT /me/author-page`**, que era lo que
`docs/api/endpoints/user.md` anticipaba. Son consecuencia de `P-5`: con la página de autor
siendo el perfil, esos dos endpoints habrían sido el primer paso hacia dos perfiles. Las
referencias se leen en `GET /api/v1/users/{userId}`, con todo lo demás de esa persona.

## Eventos

Ninguno. Cambiar las referencias no es un hecho que nadie más necesite.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `user_ctx.author_link` | `id`, `user_id`, `label`, `url`, `position` |

Filas y no una columna JSON en la cuenta: se leen en lista, se ordenan, y una de ellas puede
tener que retirarse sola el día que una moderación lo pida — con JSON habría que reescribirlo
todo para quitar una.

## Criterios de aceptación

- [x] Se guardan y se leen en el perfil, en su orden.
- [x] Se sustituyen enteras, y la lista vacía las quita.
- [x] Solo se aceptan `http` y `https`.
- [x] Una referencia sin etiqueta se rechaza.
- [x] La etiqueta se sanea.
- [x] Hay un tope.
- [x] Son públicas, como el resto del perfil.

## Preguntas abiertas

Ninguna. `P-5` y `S-5` quedan resueltas arriba.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
