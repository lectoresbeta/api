---
id: FEAT-USR-010
title: Configurar quién puede enviarme mensajes directos
context: User
concept: Privacy
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/settings.md
  - docs/ui/user-profile.md
  - conversation:2026-09-25
endpoints: [PUT /me/privacy-settings]
events: [PrivacySettingsChanged]
depends_on: [FEAT-USR-038]
updated: 2026-09-25
---

# FEAT-USR-010 — Quién puede escribirme

## Resumen

El ajuste que decide quién puede abrir una conversación contigo:
`EVERYONE`, `FOLLOWERS` o `NOBODY`.

**Por defecto, cualquiera.** Es la respuesta a `U-21`, que preguntaba si se puede escribir a
quien no lo tiene habilitado — el botón «Enviar mensaje» aparece siempre en el perfil ajeno.

## Por qué abierto por defecto

| Alternativa | Qué rompe |
|---|---|
| Solo a quien sigo | El caso de uso principal: contactar a un autor cuya obra quieres corregir. Habría que seguirle primero, que es pedirle a alguien que se comprometa antes de preguntar |
| Solo seguimiento mutuo | Lo anterior, por dos. En una plataforma que empieza vacía, los mensajes serían inalcanzables |

Abierto por defecto **con la puerta a la mano**: la misma escala que la visibilidad del perfil
(`FEAT-USR-038`), en la misma pantalla y con las mismas tres opciones. Aprender una escala y
no tres es parte de que un ajuste se use.

## El ajuste existía y no lo aplicaba nadie

`messagePermission` se guarda, se sirve y se puede cambiar desde `FEAT-USR-038`. Lo que no
había era **quién lo consultara**, porque no había mensajes directos que cortar.

Es el mismo patrón que `FEAT-NOT-003` con el canal de correo: media pantalla de ajustes
ofreciendo un control que no controlaba nada.

## Cómo llega la respuesta a `Community`

Por un **contrato publicado**, `MessageAudience`
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)):

```text
MessageAudience::acceptsMessagesFrom(recipientId, senderId): bool
```

**Un booleano, nunca el ajuste.** Entregar `FOLLOWERS` obligaría a quien pregunta a aprender
qué significa «seguidores» aquí e irlo a resolver por su cuenta, y entonces la regla viviría
en dos sitios con dos respuestas.

Es la misma forma que `AuthorAudience`, que responde lo equivalente para los comentarios, y
está separada de ella a propósito: aquella habla de la audiencia de los textos de un autor y
esta de su buzón. Una puerta, una pregunta.

## Reglas de negocio

- `RN-1` Tres valores, los mismos que el resto de ajustes de privacidad.
- `RN-2` Por defecto `EVERYONE`, escrito explícitamente al crear la cuenta. **Una fila que
  falta nunca se lee como «todo permitido»**: así es como un ajuste de privacidad deja de
  aplicarse en silencio.
- `RN-3` `FOLLOWERS` significa **quien sigue al destinatario**, no al revés. Conviene decirlo
  porque seguir es unilateral y es fácil leerlo al contrario.
- `RN-4` Quien se escribe **a sí mismo** no existe: no hay conversación de una persona.
- `RN-5` El ajuste **gobierna abrir la conversación, no continuarla**. Endurecerlo no cierra
  las que ya existen: quien ya te escribió puede seguir, y eso es lo que evita que un hilo a
  medias se quede sin respuesta por un ajuste tocado después. Para cortar a alguien concreto
  está bloquear.
- `RN-6` **Bloquear siempre corta**, en los dos sentidos y también las conversaciones
  abiertas (`FEAT-COM-034`). El ajuste es una preferencia; el bloqueo es una regla de acceso.

`RN-5` es la decisión que más fácil se lee al revés, y la razón es concreta: un ajuste que
cierra hilos abiertos convierte «prefiero que no me escriban» en «desaparezco de
conversaciones que estaba teniendo».

## Criterios de aceptación

- [x] Por defecto cualquiera puede escribir.
- [x] Con `NOBODY` nadie abre una conversación nueva.
- [x] Con `FOLLOWERS` solo abre quien sigue al destinatario.
- [x] Endurecer el ajuste **no cierra** las conversaciones ya abiertas.
- [x] Un bloqueo corta también las abiertas.
- [x] El ajuste viaja en `PrivacySettingsChanged`, como los demás.
- [x] `Community` decide con un booleano y nunca ve el ajuste.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| S-20 | ¿Debería poder cerrarse una conversación concreta sin bloquear a la persona? | Es lo que hace falta entre «me molesta este hilo» y «no quiero saber nada de ti» |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25). Resuelve `U-21` de `docs/ui/user-profile.md`.
