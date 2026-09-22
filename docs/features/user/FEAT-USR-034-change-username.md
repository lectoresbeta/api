---
id: FEAT-USR-034
title: Cambiar el nombre de usuario y alias temporal
context: User
concept: Profile
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - decision:0005
  - conversation:2026-09-22
endpoints: [PUT /me/username]
events: [UsernameChanged]
depends_on: [FEAT-USR-033]
updated: 2026-09-22
---

# FEAT-USR-034 — Cambiar el nombre de usuario y alias temporal

## Resumen

El usuario puede cambiar su nombre de usuario **una vez cada 30 días**. El nombre anterior no
queda libre: se convierte en un **alias** de la cuenta durante 30 días, de modo que los
enlaces de perfil ya compartidos siguen funcionando.

Ese alias no solo mantiene vivos los enlaces: **impide que otra persona ocupe el nombre** y
herede el tráfico dirigido a alguien distinto. Esa es su razón de ser principal.

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

## Reglas de negocio

- `RN-1` Solo se permite **un cambio cada 30 días**, contados desde el último cambio.
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
  actualicen.

`RN-8` es lo que hace que todo esto sea seguro: el nombre de usuario es **presentación y
enrutado**, nunca identidad. Cualquier tabla que lo use como clave foránea rompería con el
primer cambio.

## Flujo principal

1. El usuario propone un nombre nuevo.
2. Se comprueba que hayan pasado 30 días desde el último cambio.
3. Se valida el formato y que no sea un nombre reservado.
4. Se comprueba que esté libre: ni usuario ni alias vigente.
5. En una única transacción: el nombre actual pasa a alias con caducidad a 30 días, y el
   nuevo se asigna a la cuenta.
6. Se publica `UsernameChanged`.

El paso 5 debe ser atómico. Si el alias se crease sin completar el cambio, el usuario
bloquearía su propio nombre; y al revés, el cambio sin alias rompería los enlaces que la
funcionalidad existe para proteger.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Cambio antes de 30 días | Se rechaza, indicando cuándo podrá hacerlo | `429` con `code: USERNAME_CHANGE_TOO_SOON` y la fecha |
| Nombre ya en uso | Se rechaza | `409` con `code: USERNAME_TAKEN` |
| Nombre ocupado por un alias vigente | Se rechaza **con el mismo código** que el anterior | `409` con `code: USERNAME_TAKEN` |
| Nombre reservado | Se rechaza | `422` con `code: USERNAME_RESERVED` |
| Formato inválido | Se rechaza | `422` con `code: VALIDATION_FAILED` |
| Mismo nombre que ya tiene | No es un cambio: se acepta sin efecto y sin consumir el cupo | `200` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |

El caso del alias devuelve el mismo código que el de un nombre en uso **a propósito**:
distinguirlos revelaría que alguien tuvo ese nombre y lo cambió hace menos de un mes.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar nombre de usuario | `PUT /me/username` | `changeUsername` |

`GET /me` y `GET /me/context` devuelven además cuándo podrá volver a cambiarlo, para que el
formulario pueda desactivarse sin tener que fallar primero.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UsernameChanged` | Se cambia el nombre | `Community` (read models) | `userId`, `previousUsername`, `newUsername`, `aliasExpiresAt` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `user` | `username`, `username_changed_at` |
| `username_alias` | `username`, `user_id`, `created_at`, `expires_at` |

Índices: único sobre `username_alias(username)`, e índice sobre `expires_at` para la
resolución y para el comando de purga.

## Criterios de aceptación

- [ ] Cambiar el nombre asigna el nuevo y crea el alias con el anterior.
- [ ] El alias caduca 30 días después del cambio.
- [ ] Durante ese mes, el nombre anterior resuelve al mismo perfil.
- [ ] Durante ese mes, nadie más puede registrar ese nombre.
- [ ] Pasado el mes, el nombre anterior deja de resolver.
- [ ] Pasado el mes, el nombre queda disponible aunque la fila del alias no se haya borrado.
- [ ] Un segundo cambio antes de 30 días se rechaza con `429` e indica la fecha.
- [ ] Un nombre ocupado por alias devuelve el mismo error que uno en uso.
- [ ] El cambio y la creación del alias son atómicos.
- [ ] Tras el cambio, el `UserId` es el mismo y todas las referencias internas siguen válidas.
- [ ] Se publica `UsernameChanged` con el nombre anterior, el nuevo y la caducidad.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-6 | ¿Puede el usuario recuperar **su propio** alias sin esperar los 30 días? | Arrepentirse de un cambio es el caso más previsible, y hoy quedaría atrapado un mes |
| N-7 | ¿«Una vez al mes» son 30 días corridos o mes natural? | Se asume **30 días**; confirmar |
| N-8 | ¿Se avisa al usuario de que su nombre antiguo caducará? | Podría querer recuperarlo |
| N-9 | ¿Qué ocurre con los alias al eliminar la cuenta? | Se libera el nombre de inmediato, o se mantiene bloqueado |
| N-10 | ¿Hay histórico de cambios más allá del alias vigente? | Útil para moderación y para investigar suplantaciones |

`N-6` es la más probable en la práctica: alguien cambia el nombre, no le gusta, y no puede
volver atrás en un mes teniendo el nombre antiguo reservado **para sí mismo**.

## Estado

**Especificación:** `DRAFT`. El mecanismo está completo; `N-6` y `N-9` conviene cerrarlos
antes de implementar.

**Implementación:** `TODO`.
