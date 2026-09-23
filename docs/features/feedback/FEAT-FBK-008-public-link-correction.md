---
id: FEAT-FBK-008
title: Corregir por enlace público sin cuenta
context: Feedback
concept: Correction
actors: [Guest]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (enlace público de corrección)
  - docs/decisions/0006-credit-system.md
endpoints:
  - GET /public/{token}
  - POST /public/{token}/corrections
events: [PublicCorrectionSubmitted]
depends_on: [FEAT-WRK-010, FEAT-FBK-003]
updated: 2026-09-24
---

# FEAT-FBK-008 — Corregir por enlace público

## Resumen

El autor genera una **URL pública** de su texto con el cuestionario y la reparte **fuera de la
plataforma**. Quien la recibe lee y corrige **sin registrarse**.

**No cuesta créditos al autor ni los da al corrector.** Los correctores los ha conseguido él
por su cuenta.

## Por qué importa más de lo que parece

No es una funcionalidad secundaria: hace tres cosas a la vez.

**1. Es una válvula de seguridad para la economía.** El riesgo mayor del sistema de créditos
es el bloqueo —un autor sin créditos no recibe correcciones, y para conseguirlos necesita
textos que corregir—. El enlace público rompe ese círculo **por fuera**: un autor a cero
siempre tiene una salida que no depende de nadie más que de su agenda.

**2. Tiene impacto exactamente nulo sobre la masa de créditos.** Nada se crea, nada se
destruye. Es el único mecanismo que se puede añadir sin calibrar nada.

**3. Es el mejor canal de captación disponible.** La conversión normal pide a un desconocido
que se registre para *probablemente* hacer algo. Aquí la persona **ya ha leído un texto entero
y ha escrito una crítica** antes de que se le pida nada.

## El mensaje de captación

Al terminar, se le muestra qué habría ganado:

> Acabas de escribir una corrección que en Lectores Beta vale **6 créditos**.
> Crea tu cuenta, empieza con 10 y gana créditos ayudando a otros autores.

### Por qué **no** se le abonan esos créditos

La mejora evidente —«crea tu cuenta y te los abonamos»— abre un agujero:

1. Publico un texto con el cuestionario más exigente posible → precio 20.
2. Abro un enlace público, **que no me cuesta nada**.
3. Me corrijo a mí mismo desde una ventana de incógnito.
4. Me registro con otra cuenta → 10 créditos de la nada.
5. Repito.

El enlace público es gratis para el autor **y el precio lo fija su propio cuestionario**: esas
dos cosas juntas hacen imposible cerrar el agujero sin desmontar el mecanismo.

Y hay un motivo de fondo más importante que el fraude concreto: abonar créditos **convertiría
un flujo sin apuestas en uno con dinero**, y arrastraría hasta aquí todo el control antifraude
([`FEAT-FBK-012`](FEAT-FBK-012-correction-fraud-control.md)) que hoy este flujo no necesita.
Si nadie cobra, una corrección vacía por enlace público no daña a nadie más que al autor que
la pidió.

## La fuga que hay que tapar

- `RN-1` **Un usuario con sesión iniciada que abre un enlace público va al flujo normal**, con
  su corrección y sus créditos. El formulario anónimo solo aparece para quien no tiene sesión.

Sin esta regla, el autor podría pegar el enlace en su muro y conseguir que usuarios
registrados le corrijan gratis: él se ahorraría los créditos y ellos perderían los suyos.

## Resto de reglas de negocio

- `RN-2` Una corrección por enlace público **no mueve créditos en ninguna dirección**.
- `RN-3` El enlace es **revocable** por el autor en cualquier momento.
- `RN-4` El enlace admite **10 correcciones por defecto** (`C-36`), configurable por el autor,
  y una caducidad opcional. Diez cubre el caso real —repartirlo a tu grupo de escritura— y
  limita el daño si circula más de la cuenta.
- `RN-5` Las páginas servidas por el enlace llevan **`noindex`**: es obra inédita y no puede
  acabar en un buscador.
- `RN-6` El formulario pide un **nombre, opcional y sin verificar**. «Sin identificarse» no
  debería significar «sin saber quién es»: al autor le importa distinguir la crítica de su
  hermana de la de un compañero de taller. Es una etiqueta, no una identidad.
- `RN-7` El formulario incluye la **aceptación legal** necesaria: quien envía está aportando
  un texto propio sin haber aceptado nada.
- `RN-8` Estas correcciones **se marcan** en la bandeja del autor como recibidas por enlace
  público.
- `RN-9` **No admiten propina, valoración ni respuesta del autor** (`C-37`): no hay cuenta al
  otro lado ([`FEAT-CRD-017`](../credits/FEAT-CRD-017-author-tip.md)). Si el autor quiere
  contestar, tiene a esa persona en su agenda: es de ahí de donde la sacó.
- `RN-11` **No cuentan en el contador público** de correcciones recibidas del perfil (`C-38`):
  quien tenga una agenda grande parecería mucho más corregido que quien no la tiene. Sí se ven
  en la bandeja del autor.
- `RN-10` Hay **limitación de frecuencia** por enlace y por origen. Un formulario público sin
  límite es una invitación al abuso.

`RN-8` no es cosmético: sin la marca, el autor no entendería por qué en unas correcciones
puede dar propina y en otras no.

`RN-3` a `RN-5` son la contrapartida de lo que el autor está haciendo: cambiar privacidad por
feedback gratis. Es su decisión, pero la plataforma existe para custodiar obra inédita y tiene
que darle los controles.

## Flujo principal

1. El autor genera el enlace (`FEAT-WRK-010`).
2. Lo reparte fuera de la plataforma.
3. Alguien sin sesión lo abre, lee el texto y responde el cuestionario.
4. Envía.
5. La corrección llega al autor, marcada como pública.
6. Al corrector se le muestra el mensaje de captación.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Enlace revocado o caducado | No se sirve nada | `410` |
| Tope de correcciones alcanzado | Se puede leer, no corregir | `409` |
| **Usuario con sesión iniciada** | Redirige al flujo normal | `302` |
| Respuesta por debajo del mínimo | Se rechaza | `422` |
| Sin aceptar las condiciones | Se rechaza | `422` |
| Envíos repetidos desde el mismo origen | Limitación de frecuencia | `429` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Ver el texto y el cuestionario | `GET /public/{token}` | `getPublicCorrectionPage` |
| Enviar la corrección | `POST /public/{token}/corrections` | `submitPublicCorrection` |

El token es **opaco, largo y aleatorio**: es la única credencial que protege obra inédita.

La respuesta del `POST` incluye **el valor en créditos que habría tenido** la corrección, que
es lo que alimenta el mensaje de captación. Es el único sitio del sistema donde una cifra de
créditos se muestra a alguien que no tiene cuenta, y es informativa: no hay saldo, no hay
movimiento.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `PublicCorrectionSubmitted` | Al enviarse | `Notification` (avisa al autor). **`Credits` no lo consume** |

Que `Credits` no lo consuma es la forma más clara de expresar que esto está fuera de la
economía.

## Modelo de datos afectado

La misma `Correction` de [`FEAT-FBK-003`](FEAT-FBK-003-answer-correction-questionnaire.md),
con `origin: PUBLIC_LINK`, `readerId` nulo y `authorLabel` con el nombre declarado.

El índice único `(chapterId, readerId)` **no aplica** a estas: no hay lector identificado y
varias personas pueden corregir el mismo capítulo por el mismo enlace.

## Criterios de aceptación

- [ ] Alguien sin cuenta puede leer y corregir desde el enlace.
- [ ] La corrección no mueve créditos en ninguna dirección.
- [ ] Un usuario con sesión iniciada es redirigido al flujo normal, con créditos.
- [ ] El autor puede revocar el enlace y deja de servir de inmediato.
- [ ] Las páginas del enlace llevan `noindex`.
- [ ] Se respeta el tope de correcciones del enlace.
- [ ] La corrección aparece marcada como pública y no admite propina.
- [ ] Al enviar se muestra el valor en créditos que habría tenido.
- [ ] **No se abona ningún crédito** al corrector, ni al registrarse después.
- [ ] Hay limitación de frecuencia.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-46 | ¿Puede el autor denunciar una corrección pública fraudulenta? | No mueve créditos, así que no hay nada que devolver; pero sí puede ser contenido hiriente |

Resueltas: `C-36` (**10 correcciones** por defecto), `C-35` (**no se vincula** en la primera
versión), `C-37` (**el autor no responde**) y `C-38` (**no cuentan** en el contador público).

`C-35` se descarta de momento a conciencia: recoger el correo de alguien que **no es usuario**
para mejorar una conversión es un compromiso que conviene tomar a propósito, con casilla
explícita y base legal, no de pasada. La idea es buena y puede volver más adelante.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
