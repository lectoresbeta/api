---
id: FEAT-MOD-006
title: Catálogo de sanciones
context: Moderation
concept: Sanction
actors: [Moderator]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-23 (catálogo de sanciones)
endpoints: []
events: [SanctionImposed, SanctionLifted]
depends_on: [FEAT-MOD-002]
updated: 2026-09-23
---

# FEAT-MOD-006 — Catálogo de sanciones

## El catálogo

| Sanción | Duración | Efecto |
|---|---|---|
| **Aviso** | — | Ninguno funcional. Queda registrado |
| **Suspensión parcial** | 3 días / 1 semana / 1 mes | Limita ciertas acciones, no el acceso |
| **Suspensión total** | **Indefinida**, hasta que alguien la revoque | No puede acceder a la plataforma |
| **Expulsión** | Definitiva | La cuenta se **anonimiza** |

Cuatro familias y nada más. Un catálogo corto es una virtud: cuantos más matices tenga, más
difícil es que dos moderadores sancionen lo mismo de la misma manera.

## Las dos suspensiones no se parecen

| | Parcial | Total |
|---|---|---|
| ¿Puede entrar? | **Sí** | No |
| ¿Se acaba sola? | **Sí**, al cumplirse el plazo | **No**: requiere que alguien la revoque |
| Para qué sirve | Corregir una conducta | Detener algo mientras se investiga |

La **total es indefinida por diseño**, y eso la hace cualitativamente distinta: nadie la
levanta si nadie se acuerda. Merece aparecer en la cola del backoffice como asunto vivo, o se
convierte en una expulsión silenciosa que nadie decidió (`MOD-25`).

## La expulsión anonimiza

Es la misma anonimización que cuando un usuario se da de baja
([`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md)): desaparece la persona y permanece
lo que pertenece a otros —las correcciones que los autores pagaron, los movimientos de
créditos—.

Eso tiene una consecuencia que conviene ver antes de implementarla: **una cuenta anonimizada
ya no identifica a nadie, así que no impide volver a registrarse**. Si se quiere que la
expulsión signifique algo duradero, hace falta conservar algún dato que la haga efectiva, y
eso choca de frente con la anonimización. Ver `MOD-26`.

Es una tensión real, no un detalle: no se puede a la vez borrar a alguien y recordarlo para
impedirle volver.

## Reglas de negocio

- `RN-1` Toda sanción lleva **tipo, motivo, quién la impuso y la reclamación que la originó**.
- `RN-2` Las de plazo fijo **caducan solas**. La total y la expulsión, no.
- `RN-3` Cualquier sanción **se puede levantar antes de tiempo**, con motivo.
- `RN-4` Al usuario **se le comunica** tipo, motivo y duración. Una sanción que no se entiende
  no corrige nada: solo hace que la persona se vaya.
- `RN-5` Las sanciones **se acumulan en el historial** del usuario, también las caducadas. Es
  lo que permite que la reincidencia pese.
- `RN-6` Una sanción **no revierte créditos**. Eso lo hace la reclamación estimada, si procede.
- `RN-7` La expulsión **es irreversible**, porque la anonimización lo es.
- `RN-8` Un usuario suspendido **sigue debiendo lo que debía**: la sanción no salda deudas ni
  las condona.

`RN-6` mantiene separadas dos cosas que es cómodo mezclar: **devolver el crédito repara al
perjudicado; la sanción corrige al infractor.** Una reclamación puede producir lo primero sin
lo segundo, y al revés.

`RN-7` obliga a que la interfaz del backoffice la trate como lo que es. Debería pedir
confirmación explícita y, idealmente, no estar a un clic de las demás.

## El alcance de la suspensión parcial

Qué limita exactamente está **por definir** (`MOD-27`). Las combinaciones útiles:

| Alcance | Impide | Cuándo tendría sentido |
|---|---|---|
| **Corregir** | Enviar correcciones | Correcciones fraudulentas o de mala calidad |
| **Publicar** | Publicar obras y capítulos | Contenido inapropiado reiterado |
| **Interactuar** | Comentar, publicar en el muro, mensajes | Conducta abusiva con otros usuarios |
| **Reclamar** | Presentar reclamaciones | Reclamaciones en falso reiteradas |

La cuarta ya existe de otra forma —el bloqueo acumulativo de
[`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md)—, así que probablemente no haga falta como
sanción.

**Recomiendo que el alcance sea una combinación elegible**, no un valor único: la conducta que
se corrige no siempre cae en una sola categoría.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `SanctionImposed` | Se impone | **`User`** (aplica el efecto), `Notification` |
| `SanctionLifted` | Caduca o se levanta | `User`, `Notification` |

`Moderation` **registra** la sanción; `User` la **aplica**. Si `Moderation` marcase la cuenta
directamente habría dos dueños del estado del usuario.

## Criterios de aceptación

- [ ] Existen las cuatro familias y ninguna más.
- [ ] Las de plazo fijo caducan sin intervención.
- [ ] La suspensión total no caduca sola y aparece como asunto vivo en el backoffice.
- [ ] La expulsión anonimiza la cuenta y pide confirmación explícita.
- [ ] Toda sanción se comunica al usuario con motivo y duración.
- [ ] Las sanciones caducadas siguen en el historial.
- [ ] Ninguna sanción mueve créditos.
- [ ] Una sanción no salda ni condona la deuda del usuario.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-27** | ¿Qué alcances tiene la suspensión parcial, y son combinables? | Sin alcance, «suspensión parcial» no significa nada |
| **MOD-26** | Si la expulsión anonimiza, ¿cómo se impide volver a registrarse? | No se puede borrar a alguien y recordarlo a la vez |
| MOD-25 | ¿Cómo se evita que una suspensión indefinida se olvide? | Sería una expulsión que nadie decidió |
| MOD-28 | ¿Hay escalado automático por reincidencia? | Tres avisos deberían pesar más que uno |

## Estado

**Especificación:** `DRAFT`. `MOD-27` bloquea `APPROVED`: hay que decir qué limita una
suspensión parcial.

**Implementación:** `TODO`.
