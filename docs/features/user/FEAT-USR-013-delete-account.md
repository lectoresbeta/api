---
id: FEAT-USR-013
title: Eliminar la cuenta
context: User
concept: Account
actors: [User]
spec_status: DRAFT
impl_status: BLOCKED
priority: P2
sources:
  - conversation:2026-09-22 (pestaña «Cuenta» de Configuración)
  - docs/ui/settings.md
  - _sources/use-cases.pdf
endpoints:
  - DELETE /me
events: [UserDeleted]
depends_on: [FEAT-USR-034]
updated: 2026-09-22
---

# FEAT-USR-013 — Eliminar la cuenta

## Resumen

La pestaña «Cuenta» ofrece eliminar la cuenta con esta advertencia:

> Si eliminas esta cuenta eliminarás todos los datos asociados a ella. Esta acción es
> irreversible.

**La primera frase no es cierta**, y no es un matiz de redacción: es una promesa que el
sistema no puede cumplir y que además no debería intentar cumplir.

## Qué no se puede borrar

| Dato | Por qué no |
|---|---|
| **Nombre de usuario** | Queda bloqueado 30 días como alias, para evitar suplantaciones inmediatas ([`decision:0005`](../../decisions/0005-username-with-temporary-aliases.md)) |
| **Correcciones entregadas a otros autores** | **El autor las pagó.** Borrarlas es quitarle algo que compró |
| **Movimientos de créditos** | Son inmutables y auditables por regla del contexto `Credits` |
| **Comentarios en obras ajenas** | Borrarlos deja conversaciones incoherentes en el muro de otros |
| **Correcciones recibidas en obras propias** | Las escribió otra persona. No son datos del titular |

El patrón es el mismo en todos los casos: **una parte de lo que la cuenta ha generado
pertenece a otros o forma parte de una transacción económica**. Eliminar la cuenta no puede
deshacer lo que otro pagó ni lo que otro escribió.

## La salida: anonimizar en vez de borrar

Conviene nombrarla porque resuelve la tensión entera:

| Se elimina | Se conserva |
|---|---|
| Nombre, correo, foto, biografía | Las correcciones que otros pagaron |
| Fecha de nacimiento y datos personales | Los movimientos de créditos |
| Credenciales y sesiones | Los comentarios, atribuidos a un usuario eliminado |
| Mensajes directos (`V-4`) | El nombre de usuario, bloqueado 30 días |

Cumple el derecho de supresión —desaparece todo lo que identifica a la persona— sin destruir
lo que pertenece a terceros.

Tiene un límite que conviene conocer: **el texto de una corrección puede identificar a quien
la escribió** por estilo o por contenido. La anonimización elimina el vínculo, no hace
irreconocible el texto. Es una limitación honesta y hay que documentarla, no ocultarla.

## Qué hay que decidir

| # | Pregunta |
|---|---|
| `V-4` | ¿Qué ocurre con los mensajes directos? Borrarlos vacía la conversación del otro |
| `U-3` | ¿Qué ocurre con las obras propias y con el feedback que otros les dedicaron? |
| `S-32` | ¿Se borra de verdad o se anonimiza? |
| `S-33` | ¿Hay periodo de gracia para arrepentirse? |
| `S-34` | ¿Qué pasa con los créditos del saldo? |
| `S-35` | ¿Se puede eliminar la cuenta con retenciones vigentes? |

`U-3` es la más difícil. Si un autor elimina su cuenta:

- sus obras desaparecen, y con ellas **las correcciones que varios lectores escribieron y
  cobraron**;
- o sus obras se conservan sin autor, lo que significa publicar obra inédita de alguien que
  ha pedido irse.

Ninguna de las dos es aceptable sin más. La combinación que suele funcionar es **retirar las
obras de la circulación pero conservar las correcciones para quienes las escribieron**: el
lector sigue viendo su trabajo en «Mis correcciones», sin acceso al texto original.

`S-35` tiene respuesta clara: **no**, o hay que liberarlas primero. Una retención de una
cuenta que ya no existe es saldo inmovilizado para siempre.

`S-34` también apunta a una respuesta: los créditos **no tienen valor fuera de la
plataforma**, así que se extinguen. Pero conviene decirlo, porque el usuario que se va con
saldo puede opinar otra cosa.

## Reglas de negocio

Lo poco que ya puede fijarse:

- `RN-1` La eliminación **exige confirmación explícita**.
- `RN-2` Exige **reautenticación**: contraseña actual o su equivalente en Google.
- `RN-3` El nombre de usuario **queda bloqueado 30 días** y después se libera.
- `RN-4` Se publica `UserDeleted` para que cada contexto aplique **su** política. `User` no
  decide qué hace `Credits` con el saldo ni `Feedback` con las correcciones.
- `RN-5` La operación es **asíncrona**: se marca la cuenta y cada contexto reacciona. Borrar
  en cascada de forma síncrona a través de seis contextos sería una transacción distribuida,
  justo lo que la arquitectura evita.
- `RN-6` Desde que se solicita, la cuenta **deja de poder iniciar sesión**.
- `RN-7` La advertencia de la interfaz **debe describir lo que realmente ocurre**.

`RN-7` no es cosmético. Una advertencia que promete un borrado total y deja rastros es un
problema de confianza y, según la jurisdicción, de cumplimiento.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Eliminar mi cuenta | `DELETE /me` | `deleteMyAccount` |

Devuelve `202`: lo que ocurre después es un proceso, no una respuesta.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `UserDeleted` | Al confirmarse | `userId`, `deletedAt`. **Ningún dato personal** |

Lo consumen todos los contextos. Cada uno decide qué significa en su modelo, que es
exactamente por lo que este evento no puede llevar instrucciones.

## Criterios de aceptación

Provisionales hasta resolver `U-3` y `S-32`:

- [ ] Eliminar exige confirmación y reautenticación.
- [ ] La cuenta deja de poder iniciar sesión inmediatamente.
- [ ] El nombre de usuario queda bloqueado 30 días.
- [ ] Ningún dato personal viaja en `UserDeleted`.
- [ ] Las correcciones que otros pagaron siguen siendo accesibles para quien las escribió.
- [ ] No quedan retenciones de créditos vivas de una cuenta eliminada.
- [ ] El texto de la advertencia coincide con lo que ocurre.

## Estado

**Especificación:** `DRAFT`. La pantalla aporta el flujo y la advertencia; **no resuelve** el
fondo.

**Implementación:** `BLOCKED` por `U-3`, `V-4` y `S-32`.

Es de las pocas funcionalidades donde implementar antes de decidir sería irreversible por
partida doble: los datos borrados no vuelven, y los que se conservaron después de prometer lo
contrario tampoco se pueden justificar.
