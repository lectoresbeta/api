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
| **Expulsión** | Definitiva | La cuenta pasa a **`BLOCKED`**. No se anonimiza |

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

## La expulsión bloquea, no anonimiza

Es la diferencia con darse de baja voluntariamente, y es deliberada:

| | Baja voluntaria | **Expulsión** |
|---|---|---|
| Estado final | `DELETED` | **`BLOCKED`** |
| Datos personales | Se suprimen | **Se conservan** |
| ¿Puede volver a registrarse? | Sí | **No**, con el mismo correo |
| Quién lo decide | El usuario | Un moderador |

El motivo es que **no se puede a la vez borrar a alguien y recordarlo para impedirle volver**.
Si la expulsión anonimizase, la cuenta dejaría de identificar a nadie y la persona podría
registrarse de nuevo al minuto siguiente: la sanción más grave del catálogo sería la más fácil
de esquivar.

Conservar el correo es lo mínimo que la hace efectiva.

### Lo que eso obliga a asumir

Conservar datos de alguien expulsado tiene un coste que conviene reconocer: **si esa persona
ejerce su derecho de supresión, hay dos intereses en conflicto** —el suyo y el de la
plataforma en no readmitirle—.

La salida habitual es conservar **solo lo imprescindible para impedir el reingreso** —un
identificador derivado del correo, no el correo en claro— y suprimir el resto. Ver `MOD-44`:
es una decisión que conviene tomar con criterio legal, no de diseño.

## Reglas de negocio

- `RN-1` Toda sanción lleva **tipo, motivo, quién la impuso y la reclamación que la originó**.
- `RN-2` Las de plazo fijo **caducan solas**. La total y la expulsión, no.
- `RN-3` Cualquier sanción **se puede levantar antes de tiempo**, con motivo.
- `RN-4` Al usuario **se le comunica** tipo, motivo y duración. Una sanción que no se entiende
  no corrige nada: solo hace que la persona se vaya.
- `RN-5` Las sanciones **se acumulan en el historial** del usuario, también las caducadas. Es
  lo que permite que la reincidencia pese.
- `RN-6` Una sanción **no revierte créditos**. Eso lo hace la reclamación estimada, si procede.
- `RN-7` La expulsión **la puede revocar un moderador**, igual que la suspensión total. Al no
  anonimizar, no destruye nada que impida volver atrás.
- `RN-7b` Una cuenta en `BLOCKED` **no autentica** y su correo **no se puede reutilizar** para
  registrarse.
- `RN-8` Un usuario suspendido **sigue debiendo lo que debía**: la sanción no salda deudas ni
  las condona.

`RN-6` mantiene separadas dos cosas que es cómodo mezclar: **devolver el crédito repara al
perjudicado; la sanción corrige al infractor.** Una reclamación puede producir lo primero sin
lo segundo, y al revés.

`RN-7` obliga a que la interfaz del backoffice la trate como lo que es. Debería pedir
confirmación explícita y, idealmente, no estar a un clic de las demás.

## El alcance de la suspensión parcial: solo lectura

**El usuario puede entrar, pero no interactuar.** La cuenta queda en modo lectura.

| Puede | No puede |
|---|---|
| Entrar y navegar | Publicar obras y capítulos |
| Leer obras y capítulos | Comentar y publicar en el muro |
| Leer sus correcciones recibidas | **Enviar correcciones** |
| Consultar su saldo | Reaccionar, repostear, mandar mensajes |

Que pueda entrar no es una concesión menor: le permite leer la sanción, entender por qué la
tiene y ver cuándo termina. Una suspensión que además cierra la puerta no corrige nada, solo
hace que la persona se vaya.

### El conflicto con la deuda

Hay un caso que esto crea y conviene resolver antes de implementarlo:

> **Un usuario con saldo negativo y suspensión parcial no puede corregir, y corregir es la
> única forma de saldar la deuda.** Queda atrapado.

Tres salidas posibles:

| Salida | A favor | En contra |
|---|---|---|
| **Permitir corregir durante la suspensión** | No hay trampa | Debilita la sanción justo para el corrector fraudulento, que es el caso más común |
| Congelar la deuda mientras dure | La deuda no crece ni bloquea | Un estado más que mantener |
| Dejarlo atrapado | Simple | La sanción se vuelve indefinida de hecho |

**Recomiendo la segunda**: la deuda queda congelada y sin efecto mientras dura la suspensión,
y vuelve a contar al levantarse. Ver `MOD-43`.

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
- [ ] La expulsión deja la cuenta en `BLOCKED` y **no** la anonimiza.
- [ ] Una cuenta en `BLOCKED` no autentica y su correo no se puede reutilizar.
- [ ] La suspensión parcial permite entrar y leer, e impide publicar, comentar y corregir.
- [ ] Toda sanción se comunica al usuario con motivo y duración.
- [ ] Las sanciones caducadas siguen en el historial.
- [ ] Ninguna sanción mueve créditos.
- [ ] Una sanción no salda ni condona la deuda del usuario.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-43** | Con suspensión parcial y saldo negativo, ¿cómo salda la deuda? | Sin salida, la sanción se vuelve indefinida de hecho |
| **MOD-44** | ¿Qué se conserva exactamente de una cuenta expulsada, si esa persona pide su supresión? | Conflicto entre el derecho de supresión y no readmitir |
| MOD-25 | ¿Cómo se evita que una suspensión indefinida se olvide? | Sería una expulsión que nadie decidió |
| MOD-28 | ¿Hay escalado automático por reincidencia? | Tres avisos deberían pesar más que uno |

Resueltas: `MOD-27` (**solo lectura**: entra, no interactúa) y `MOD-26` (la expulsión
**bloquea, no anonimiza**).

## Estado

**Especificación:** `DRAFT`. `MOD-43` conviene cerrarla antes de implementar: es un callejón
sin salida para un usuario real, no un caso teórico.

**Implementación:** `TODO`.
