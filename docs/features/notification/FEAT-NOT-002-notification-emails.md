---
id: FEAT-NOT-002
title: Entregar notificaciones por email
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - _sources/use-cases.pdf#p7
  - docs/ui/settings.md
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-NOT-001, FEAT-USR-039]
updated: 2026-09-25
---

# FEAT-NOT-002 — Entregar notificaciones por email

## Resumen

Que los avisos salgan también por correo, y no solo a la campana.

Hoy **no sale ni un correo de notificación**. Salen los operativos —activación, contraseña,
cambio de correo, bloqueo de obra— porque cada uno tiene su propio consumidor escrito a mano.
Todo lo demás se queda dentro de la plataforma, esperando a que alguien entre a mirar.

Eso vacía de sentido media pantalla de Configuración, que lleva desde `FEAT-USR-039`
ofreciendo un interruptor de correo por tipo de aviso que no aplica nadie.

## Cadencia: inmediato, uno por aviso

**Confirmado por producto** (conversación 2026-09-25).

| Alternativa | Por qué no |
|---|---|
| Resumen diario | Retrasa avisos que pierden sentido con el tiempo: una solicitud de acceso contestada mañana ya no sirve |
| Inmediato para lo importante + resumen para el resto | Hace falta un programador, una tabla de pendientes y una lista de qué es importante. Es un bloque en sí mismo |

Lo que hace viable el envío inmediato sin convertirlo en spam es que **el filtro ya está hecho
en el catálogo**: `NotificationTopic::channels()` declara desde `FEAT-USR-039` qué avisos
existen por correo, y los ruidosos —mensajes directos, respuestas a comentarios, me gusta,
movimientos de créditos— están marcados como solo plataforma. No hay que decidirlo otra vez
aquí.

## Los dos catálogos tienen que decir lo mismo

`Notification` tiene su `NotificationKind` y `User` tiene su `NotificationTopic`, y **están
separados a propósito**: son dos contextos y ninguno importa el enum del otro. Se emparejan
por el valor de la cadena.

Eso abre un hueco real: si un tipo dijera aquí que sale por correo y allí no tuviera casilla
de correo, saldría un aviso que nadie puede apagar. Y al revés, la pantalla ofrecería una
casilla que no hace nada.

No se resuelve con una tabla de traducción —que habría que mantener— sino con
**`tests/Unit/Architecture/NotificationCatalogueTest.php`**, que compara los dos catálogos en
cada ejecución. Es la misma solución que las rutas: una convención que solo vive en un
documento se incumple el día que alguien tiene prisa.

## Reglas de negocio

- `RN-1` Sale por correo **lo que el tipo declara** que existe en ese canal, y nada más.
- `RN-2` Un aviso se manda **una sola vez** por correo, aunque RabbitMQ reentregue el hecho.
- `RN-3` **Un fallo al enviar no se pierde.** Si el proveedor de correo falla, el consumidor
  reintenta; en el reintento el aviso ya existe y lo que se comprueba es si llegó a salir, no
  si existe. Sin eso, el primer fallo dejaría a alguien sin su correo para siempre, que es
  justo lo que la deduplicación tapa.
- `RN-4` El correo **no lleva contenido**: ni una línea de una obra, de una corrección o de un
  mensaje (`FEAT-NOT-001` `RN-4`). Lleva qué ha pasado, quién y dónde mirarlo.
- `RN-5` Un destinatario sin dirección resoluble —cuenta eliminada— no recibe nada y no se
  reintenta: reintentar no la va a resucitar.
- `RN-6` Los avisos **operativos** siguen teniendo su propio consumidor y su propio texto. No
  pasan por aquí, y es deliberado: llevan cosas que un aviso genérico no tiene —un enlace de
  un solo uso, una dirección a la que recurrir— y perderlas sería perder el correo entero.
- `RN-7` El texto se compone **en el servidor**. La campana manda `kind` y `payload` y deja la
  frase al cliente (`FEAT-NOT-001`), pero un correo no tiene cliente que la componga.

## Las dos columnas nuevas, y por qué

| Columna | Para qué |
|---|---|
| `emailed_at` | `RN-2` y `RN-3`. Distingue «ya se mandó» de «existe el aviso», que es lo que hace correcto el reintento |
| `inbox` | Que los dos canales se puedan apagar por separado, como promete la pantalla |

La segunda merece explicación. Hasta ahora, un aviso silenciado **no se creaba**. Con dos
canales independientes eso deja de valer: alguien puede querer el correo y no la campana, y
sin fila no hay dónde anotar que el correo salió.

Así que la fila se crea siempre y es **el registro de lo que se hizo con ese hecho**;
`inbox = false` significa que no se enseña en la campana. Lo que el usuario pidió se sigue
cumpliendo: no se le enseña y no se le cuenta.

## Flujo principal

1. Un consumidor convierte un hecho en un aviso llamando a `Notify`.
2. Si ya hay fila para ese destinatario, tipo y hecho, se reutiliza; si no, se crea.
3. Se resuelve si ese aviso se enseña en la campana, y se anota.
4. Se resuelve si sale por correo: que el tipo lo admita, que su dueño no lo haya apagado y
   que no haya salido ya.
5. Se compone el texto y se manda.
6. Se anota que salió.

## Flujos alternativos y errores

| Caso | Comportamiento |
|---|---|
| El proveedor de correo falla | Excepción; el consumidor reintenta y el aviso sigue sin `emailed_at`, así que vuelve a intentarlo |
| El destinatario no tiene dirección | No se manda y no se reintenta |
| El tipo no existe por correo | No se manda, y no es un error |
| El hecho se reentrega | La fila ya existe y `emailed_at` también: no sale un segundo correo |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `notification` | `emailed_at` anulable, `inbox` booleano con valor por defecto `true` |

## Criterios de aceptación

- [x] Un aviso cuyo tipo existe por correo genera un correo.
- [x] Un aviso que solo existe en la plataforma no genera ninguno.
- [x] El mismo hecho reentregado no manda dos correos.
- [x] Si el envío falla, el reintento vuelve a intentarlo.
- [x] Una cuenta sin dirección no provoca reintentos.
- [x] El correo no contiene texto de la obra, la corrección ni el mensaje.
- [x] Los operativos siguen saliendo por su camino, con su texto.
- [x] Los dos catálogos de tipos coinciden, comprobado en cada ejecución.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-3 | ¿Qué proveedor de correo se usa en producción? | Heredada de `FEAT-NOT-008`. No bloquea: `Mailer` es un puerto |
| N-10 | ¿Hace falta un resumen diario cuando haya volumen? | Hoy no: los tipos ruidosos ya son solo de plataforma. Con datos reales se verá |
| N-11 | ¿Se registra el rebote de un correo, para dejar de escribir a una dirección muerta? | Depende del proveedor (`N-3`) |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
