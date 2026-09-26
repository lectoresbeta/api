---
id: FEAT-MOD-006
title: Catálogo de sanciones
context: Moderation
concept: Sanction
actors: [Moderator]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-23 (catálogo de sanciones)
endpoints: [POST /admin/sanctions, POST /admin/sanctions/{sanctionId}/lift]
events: [SanctionImposed, SanctionLifted]
depends_on: [FEAT-MOD-002]
updated: 2026-09-26
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
- `RN-7c` Con JWT, una sanción tarda hasta **15 minutos** en cortar las lecturas
  ([`decision:0007`](../../decisions/0007-jwt-sessions.md)). Las **escrituras se bloquean de
  inmediato**, porque comprueban el estado de la cuenta y no solo la firma del token.
- `RN-8` Un usuario suspendido **sigue debiendo lo que debía**: la sanción no salda deudas ni
  las condona.
- `RN-9` Durante una **suspensión parcial**, la deuda del usuario queda **congelada** (`MOD-43`):
  ni crece ni le bloquea nada, y vuelve a contar al levantarse la sanción. Sin esto quedaría
  atrapado, porque corregir es la única forma de saldarla y la suspensión se lo impide.

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

**Decidido: la deuda se congela.** Mientras dura la suspensión parcial, el saldo negativo del
usuario queda sin efecto —no crece, no bloquea nada— y vuelve a contar al levantarse.

Así la sanción castiga lo que debe castigar —no poder participar— sin convertirse en una
condena indefinida por un motivo colateral.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `SanctionImposed` | Se impone | **`User`** (aplica el efecto), `Notification` |
| `SanctionLifted` | Caduca o se levanta | `User`, `Notification` |

`Moderation` **registra** la sanción; `User` la **aplica**. Si `Moderation` marcase la cuenta
directamente habría dos dueños del estado del usuario.

## Criterios de aceptación

- [x] Existen las cuatro familias y ninguna más.
- [x] Las de plazo fijo caducan sin intervención.
- [x] La suspensión total no caduca sola y **aparece como asunto vivo en el backoffice**.
- [x] La expulsión deja la cuenta en `BLOCKED` y **no** la anonimiza.
- [x] Una cuenta en `BLOCKED` no autentica. El correo **no se libera**, que es lo que lo impide.
- [x] La suspensión parcial permite entrar y leer, e impide publicar, comentar y corregir.
- [x] Toda sanción se comunica al usuario con motivo y duración. `Notification` lo escucha desde 2026-09-26.
- [x] Las sanciones caducadas siguen en el historial.
- [x] Ninguna sanción mueve créditos.
- [x] Una sanción no salda ni condona la deuda del usuario: no toca `Credits` en absoluto.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-44** | ¿Qué se conserva exactamente de una cuenta expulsada, si esa persona pide su supresión? | Conflicto entre el derecho de supresión y no readmitir |
| ~~MOD-25~~ | ~~¿Cómo se evita que una suspensión indefinida se olvide?~~ | **Resuelta** (2026-09-26): una cola de asuntos vivos en el backoffice, ordenada por antigüedad y con los días que lleva abierta cada uno |
| MOD-28 | ¿Hay escalado automático por reincidencia? | Tres avisos deberían pesar más que uno |

Resueltas: `MOD-27` (**solo lectura**), `MOD-26` (la expulsión **bloquea, no anonimiza**) y
`MOD-43` (**la deuda se congela** durante la suspensión).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-26). Las cuatro familias, imponerlas, levantarlas y el
rastro en auditoría. La frontera que la ficha pedía está donde debe: **`Moderation` registra la
sanción y `User` la aplica**, por hecho de integración. Si este contexto marcara la cuenta
directamente habría dos dueños del estado del usuario.

Dos decisiones de modelo que la implementación obligó a tomar:

- **la suspensión parcial es una fecha, no un estado de cuenta.** Caduca sola (`RN-2`), y un
  estado que hay que acordarse de apagar es un estado que alguien olvidará. Las otras dos sí
  son estados —`SUSPENDED` y `BLOCKED`— precisamente porque **no** caducan;
- **levantar una sanción no es idempotente**, al revés que casi todo lo demás en esta API.
  Levantar lo ya levantado responde `409`: es un acto administrativo que se registra y se
  comunica, y hacerlo dos veces dejaría en el historial algo que no ocurrió.

**Las tres cosas que faltaban**, cerradas el 2026-09-26 y separadas porque son de tres
contextos distintos:

- ~~**el aviso al usuario** (`RN-4`)~~ — **hecho** (2026-09-26).
  `NotifyTheSanctionedPerson` consume `SanctionImposed` y entrega `MODERATION_ALERT` con tipo,
  motivo y hasta cuándo. Es **operativo**: no pasa por preferencias ni por el interruptor
  general, por la misma razón que el aviso de contraseña cambiada — quien pudiera apagarlo se
  quedaría sin enterarse de lo que le está pasando a su cuenta. No lleva quién la impuso: un
  moderador no es un actor social, y nombrarlo convertiría una decisión de la plataforma en un
  asunto entre dos personas;
- ~~**la congelación de la deuda** durante la suspensión parcial (`RN-9`, `MOD-43`)~~ —
  **hecho** (2026-09-26). `Credits` escucha `SanctionImposed` y decide a solas qué significa:
  desde aquí no se ordena nada. Lo que se congela es **la retención de lo ya entregado**, no la
  corregibilidad —si los capítulos volvieran a admitir correcciones, cada una cobrada haría
  crecer la deuda, y la regla dice «ni crece»—, y se congela **hasta la fecha de fin de la
  sanción**, que es lo que permite que el vencimiento natural no necesite anunciarse. Al
  levantarla antes de tiempo la deuda vuelve a retener, pero solo hacia adelante: lo que el
  autor ya pudo leer no se vuelve a cerrar
  ([`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md) `RN-11`);
- ~~**la cola de asuntos vivos** del backoffice (`MOD-25`)~~ — **hecha** (2026-09-26):
  `GET /api/v1/admin/sanctions/open`, de la más antigua a la más reciente y con **cuántos días
  lleva abierta cada una**, que es el dato por el que se entra ahí — la fecha cruda obliga a
  quien revisa a hacer la resta.

  Dos decisiones:

  - **el filtro es «sin fecha de fin», no «de tipo X».** Qué familias son indefinidas lo dice
    el modelo al imponerlas —una suspensión parcial siempre trae plazo—, y repetir esa lista
    en la consulta sería un segundo sitio del que desdecirse el día que aparezca otra familia.
    De paso entran los **avisos**, que tampoco caducan y son lo que hace que la reincidencia
    pese;
  - **no es una cola con estado propio**, es una consulta sobre las sanciones que ya existen.
    Marcar una como «revisada» habría sido inventar un estado que la ficha no pide y que
    envejecería igual de mal que la sanción.
