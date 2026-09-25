---
id: FEAT-NOT-003
title: Aplicar las preferencias de notificación del usuario
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/ui/settings.md
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-USR-039, FEAT-NOT-002]
updated: 2026-09-25
---

# FEAT-NOT-003 — Aplicar las preferencias de notificación

## Resumen

Que lo que alguien apaga en Configuración deje de llegarle, **en los dos canales**.

`FEAT-USR-039` construyó las preferencias y `Notify` ya aplica el canal `PLATFORM` desde
entonces. El canal `EMAIL` se guardaba, se servía y **no lo aplicaba nadie**, porque no había
correos de aviso que apagar. Con `FEAT-NOT-002` los hay, y esta ficha cierra el hueco.

## Dónde se pregunta, y por qué ahí

**Al entregar, no al publicar el hecho.**

Quien publica un hecho —`Feedback`, `Work`, `Credits`— no conoce las preferencias de nadie ni
debe conocerlas, y entre que el hecho se publica y se entrega pueden pasar minutos en los que
alguien cambie de opinión. Preguntar al entregar es lo que hace que un cambio surta efecto en
el acto y no cuando la cola se ponga al día.

Es también la razón de que `NotificationPreferencesChanged` no lo consuma nadie: no hace falta
proyectar una preferencia que se pregunta.

## Reglas de negocio

- `RN-1` Cada canal se comprueba **por separado**. Apagar la campana no apaga el correo.
- `RN-2` El interruptor general suspende los dos, y **suspende, no sobrescribe**: al quitarlo,
  cada casilla vuelve a decir lo que su dueño eligió.
- `RN-3` Los avisos **operativos no preguntan nada**. La activación, el restablecimiento de
  contraseña, los avisos de seguridad y las comunicaciones legales salen siempre, también con
  el interruptor general puesto. Un catálogo que los incluyera invitaría a apagarlos, y el
  primero que faltase dejaría a alguien sin poder recuperar su cuenta.
- `RN-4` Un tipo de aviso **desconocido se permite**. Es lo que hace que añadir un aviso no
  exija migración ni desplegar el cliente: llega hasta que alguien decida que se puede apagar,
  y no al revés.
- `RN-5` Un canal que ese tipo **no admite** se rechaza, en la pantalla y en la entrega. Nadie
  recibe un correo por cada mensaje directo por mucho que active la casilla.
- `RN-6` Que `Notification` pregunte a `User` por un contrato publicado es legítimo y no
  contradice `decision:0014`: **un consumidor de eventos no es un contrato respondiendo**, así
  que no hay ningún contexto esperando al otro lado.

## Lo que cambia respecto de FEAT-USR-039

| | Antes | Ahora |
|---|---|---|
| Canal `PLATFORM` | Se aplicaba | Igual |
| Canal `EMAIL` | Se guardaba y **no se aplicaba** | Se aplica |
| Aviso silenciado en plataforma | No se creaba fila | Se crea con `inbox = false`, para poder anotar el correo si sí lo quiere |

El último es el único cambio de comportamiento, y va explicado en `FEAT-NOT-002`: con dos
canales independientes, la fila deja de ser «lo que se le enseña» y pasa a ser **el registro
de lo que se hizo con ese hecho**.

## Criterios de aceptación

- [x] Apagar el correo de un tipo deja de mandar ese correo y **no toca** la campana.
- [x] Apagar la campana de un tipo deja de enseñarlo y **no toca** el correo.
- [x] El interruptor general apaga los dos.
- [x] Con el interruptor general puesto, los operativos siguen saliendo.
- [x] Quitar el interruptor general recupera lo que cada casilla decía.
- [x] Un tipo sin preferencia guardada usa el valor por defecto que declara el catálogo.
- [x] Un aviso silenciado no se cuenta en el contador de no leídos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-12 | ¿Se le puede decir a alguien que un aviso no le llegó porque lo tiene apagado? | La fila existe, así que es posible. Hoy no se enseña |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
