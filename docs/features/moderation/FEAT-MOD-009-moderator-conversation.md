---
id: FEAT-MOD-009
title: Conversación entre el moderador y las partes
context: Moderation
concept: Review
actors: [Moderator, User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (comunicación en las reclamaciones)
endpoints:
  - POST /admin/claims/{claimId}/messages
  - GET /me/claims/{claimId}/messages
  - POST /me/claims/{claimId}/messages
events: [ClaimMessageSent]
depends_on: [FEAT-MOD-002, FEAT-MOD-010]
updated: 2026-09-24
---

# FEAT-MOD-009 — Conversación con el moderador

## Resumen

Antes de decidir, el moderador puede **hablar con cada parte por separado**: pedir
aclaraciones al reclamante, dar al reclamado la oportunidad de explicarse.

## La forma es una estrella, no una sala

```text
        Reclamante
             │  (hilo privado)
             ▼
        MODERADOR  ◄──── ve los dos hilos
             ▲
             │  (hilo privado)
        Reclamado
```

**Las partes nunca se ven entre sí.** Cada una tiene un hilo privado con el moderador y no
sabe qué dice la otra, ni siquiera si hay otra.

No es una restricción técnica sino la razón de ser del mecanismo: poner a denunciante y
denunciado a discutir **crearía el conflicto que la moderación existe para evitar**. El
moderador media; no organiza un careo.

## Quién empieza

| Quién | Puede escribir |
|---|---|
| **Moderador** | Sí, a cualquiera de las partes, cuando quiera |
| **Parte** | Solo **respondiendo** dentro de su hilo, una vez el moderador lo ha abierto |
| Invitado sin cuenta | **No.** Puede reclamar, pero no seguir la reclamación (`MOD-10`) |

La parte no abre conversación por su cuenta. Si pudiera, la cola del moderador se llenaría de
alegatos no solicitados y el expediente dejaría de ser un procedimiento para convertirse en
una bandeja de entrada.

## Reglas de negocio

- `RN-1` Los hilos son **privados entre el moderador y cada parte**. Nadie más los ve.
- `RN-2` Una parte **solo puede escribir en su hilo**, y solo después de que el moderador lo
  abra.
- `RN-3` Los mensajes son **inmutables**: no se editan ni se borran. Forman parte del
  expediente.
- `RN-4` La conversación **no revela la identidad del moderador** (`FEAT-MOD-002` `RN-6`).
  Firma como «Moderación», no con su nombre.
- `RN-5` El moderador **no está obligado a esperar respuesta**. Puede decidir sin ella
  (`MOD-23`).
- `RN-6` El hilo se **cierra al resolverse** la reclamación. Se puede leer, no continuar.
- `RN-7` Todo mensaje genera **aviso al destinatario**, y el de la plataforma al usuario es
  operativo: afecta a un expediente que le concierne.
- `RN-8` Los mensajes **quedan en el registro de auditoría**.

`RN-4` protege al moderador de represalias y es lo que hace posible sostener `RN-1` de
`FEAT-MOD-002`: si el decisor es anónimo, la decisión se discute por su contenido.

`RN-3` importa más de lo que parece: si alguien puede editar lo que dijo, el expediente deja
de ser prueba de nada.

## Flujo

1. El moderador revisa la reclamación y necesita contexto.
2. Abre hilo con una parte, o con las dos.
3. La parte recibe aviso y ve el mensaje en **su sección de reclamaciones**
   ([`FEAT-MOD-010`](FEAT-MOD-010-my-claims.md)).
4. Responde en su hilo.
5. El moderador decide. Los hilos se cierran.

## Contrato de API

| Operación | Método y ruta | Quién |
|---|---|---|
| Escribir a una parte | `POST /admin/claims/{claimId}/messages` | Moderador |
| Ver mi hilo | `GET /me/claims/{claimId}/messages` | La parte |
| Responder | `POST /me/claims/{claimId}/messages` | La parte |

La ruta del moderador indica **a qué parte** escribe; la del usuario no necesita indicarlo:
solo tiene un hilo, el suyo.

**Un usuario nunca recibe el identificador de la otra parte.** Ni en la conversación, ni en el
detalle de la reclamación.

## Modelo de datos afectado

`claim_message`: `id`, `claim_id`, `thread_party` (`REPORTER` / `SUBJECT`), `author_type`
(`MODERATOR` / `PARTY`), `author_id`, `body`, `sent_at`.

`thread_party` es lo que separa los dos hilos dentro del mismo expediente. Es el campo del que
depende toda la privacidad de esta funcionalidad, y el que conviene cubrir con tests que
intenten leer el hilo ajeno.

## Criterios de aceptación

- [ ] Una parte no puede leer el hilo de la otra por ninguna vía de la API.
- [ ] Una parte no puede abrir conversación si el moderador no lo ha hecho.
- [ ] Los mensajes no se editan ni se borran.
- [ ] El usuario nunca ve la identidad del moderador ni la de la otra parte.
- [ ] Al resolverse la reclamación, los hilos quedan en solo lectura.
- [ ] Un invitado sin cuenta no tiene hilo.
- [ ] El moderador puede decidir sin esperar respuesta.
- [ ] Todo mensaje queda en el registro de auditoría.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-23 | ¿Cuánto espera el moderador antes de decidir sin respuesta? | Una parte que calla no puede congelar el expediente |
| MOD-29 | ¿Puede el usuario adjuntar archivos? | Útil para probar un plagio; abre una vía de subida sin moderar |
| MOD-30 | ¿Puede la parte leer su hilo después de resuelto y de sancionado? | Es su única constancia de lo ocurrido |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
