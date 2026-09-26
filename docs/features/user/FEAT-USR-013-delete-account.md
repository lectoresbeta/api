---
id: FEAT-USR-013
title: Eliminar la cuenta — anonimización
context: User
concept: Account
actors: [User]
spec_status: APPROVED
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
updated: 2026-09-24
---

# FEAT-USR-013 — Eliminar la cuenta

## Resumen

La pestaña «Cuenta» ofrece eliminar la cuenta con esta advertencia:

> Si eliminas esta cuenta eliminarás todos los datos asociados a ella. Esta acción es
> irreversible.

**La primera frase no es cierta**, y no es un matiz de redacción: es una promesa que el
sistema no puede cumplir y que además no debería intentar cumplir.

**Decidido (`S-32`): eliminar una cuenta la anonimiza.** El texto de la advertencia tiene que
reescribirse en consecuencia (`RN-7`).

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

> **No confundir con la expulsión.** Darse de baja **anonimiza**; ser expulsado deja la cuenta
> en **`BLOCKED`, con sus datos intactos**, para impedir volver a registrarse
> ([`FEAT-MOD-006`](../moderation/FEAT-MOD-006-sanctions.md)). Son dos finales distintos
> porque responden a intereses distintos: uno protege a la persona, el otro a la plataforma.

## Anonimizar, no borrar

Es la decisión de fondo, y resuelve la tensión entera: **desaparece la persona, permanece lo
que pertenece a otros.**

| Se elimina | Se conserva, atribuido a un usuario eliminado |
|---|---|
| Nombre y nombre de usuario | Las correcciones que otros autores pagaron |
| Correo y credenciales | Los movimientos de créditos |
| Foto y biografía | Los comentarios en conversaciones ajenas |
| Fecha de nacimiento y datos personales | Las valoraciones recibidas por su feedback |
| Sesiones abiertas | El nombre de usuario, **bloqueado 30 días** como alias |
| Mensajes directos (`V-4`) | |

Cumple el derecho de supresión sin destruir lo ajeno: el autor que pagó una corrección la
conserva, y el lector que la escribió no pierde el reconocimiento de su trabajo en los
contadores del sistema.

### Qué significa «anonimizar» en la práctica

Tres condiciones, y las tres importan:

1. **El vínculo se rompe de verdad.** No basta con ocultar el nombre en la interfaz: la fila
   de usuario deja de contener datos personales. Si el nombre sigue en la base de datos
   «pero no se muestra», no se ha anonimizado nada.
2. **Es irreversible.** Si existiera forma de reconstruir quién era, seguiría siendo un dato
   personal, solo que peor guardado.
3. **Las referencias sobreviven.** `userId` sigue existiendo como identificador sin datos
   detrás, porque hay correcciones y movimientos que apuntan a él.

### Su límite, dicho abiertamente

**El texto de una corrección puede identificar a quien la escribió**, por estilo, por
contenido o porque menciona detalles de una conversación. La anonimización elimina el
vínculo; no hace irreconocible el texto.

Es una limitación real y conviene documentarla en vez de dejar que la descubra alguien
después. Si en algún caso hiciera falta ir más lejos, la vía sería permitir al usuario
solicitar la supresión del contenido concreto, no cambiar la regla general (`S-41`).

## Qué hay que decidir

| # | Pregunta |
|---|---|
| `V-4` | ¿Qué ocurre con los mensajes directos? | **Resuelta (2026-09-26): se conservan sin autor identificable.** La conversación del otro sigue completa y legible, el remitente aparece como «Usuario eliminado» sin enlace a ningún perfil, y no se puede responder ni abrir una conversación nueva. Es el mismo criterio que ya se aplicó a las correcciones pagadas y a los movimientos de crédito: se suprime la persona y se conserva lo que pertenece a terceros |
| `U-3` | ¿Qué ocurre con las obras propias y con el feedback que otros les dedicaron? |
| `S-33` | ¿Hay periodo de gracia para arrepentirse? |
| `S-34` | ¿Qué pasa con los créditos del saldo? |
| `S-35` | ¿Se puede eliminar la cuenta con **deuda** pendiente? |

`S-33` gana importancia ahora: si la anonimización es irreversible —y lo es—, un clic por
error no tiene vuelta atrás. Un periodo de gracia de unos días, durante el cual la cuenta no
autentica pero aún no se ha anonimizado, es barato y evita el único caso en el que el usuario
no puede repararlo por sí mismo.

`U-3` es la más difícil. Si un autor elimina su cuenta:

- sus obras desaparecen, y con ellas **las correcciones que varios lectores escribieron y
  cobraron**;
- o sus obras se conservan sin autor, lo que significa publicar obra inédita de alguien que
  ha pedido irse.

Ninguna de las dos es aceptable sin más. La combinación que suele funcionar es **retirar las
obras de la circulación pero conservar las correcciones para quienes las escribieron**: el
lector sigue viendo su trabajo en «Mis correcciones», sin acceso al texto original.

`S-35` cambia de forma con el sistema de créditos definitivo: no hay retenciones que liberar,
pero **sí puede haber deuda**. Una cuenta anonimizada con saldo negativo es, contablemente,
crédito emitido que nadie va a devolver. Lo honesto es contabilizarlo como tal y no fingir que
se cobra (`C-21`).

`S-34` también apunta a una respuesta: los créditos **no tienen valor fuera de la
plataforma**, así que se extinguen. Pero conviene decirlo, porque el usuario que se va con
saldo puede opinar otra cosa.

## Qué pasa con las obras

**Decidido** (`U-3`): las obras de una cuenta anonimizada **dejan de ser accesibles**.

Y con ellas, la referencia se rompe en un sitio concreto y buscado:

| Qué ocurre | |
|---|---|
| La obra | **Deja de ser accesible** para todos. No aparece en catálogo, perfil ni búsquedas |
| Las correcciones que recibió | **Se conservan**, y con ellas los créditos que generaron |
| La referencia de esas correcciones a la obra | **Se pierde**: la obra ya no es alcanzable |
| El corrector | Sigue viendo que hizo el trabajo y conserva lo que cobró |

Es la combinación que respeta a las dos partes: **el autor se lleva su obra** —nadie sigue
leyendo lo que él pidió retirar— y **el corrector conserva lo que ganó**, porque el trabajo
existió y ya se pagó.

Tiene una consecuencia visible que conviene diseñar bien: en «Mis correcciones»
(`FEAT-FBK-010`) aparecerán entradas **sin obra
detrás**. No es un error y no debe parecerlo: la interfaz tiene que decir que la obra ya no
está disponible, no mostrar un hueco.

El contenido **no se borra**: queda inaccesible. Borrarlo destruiría además el material que
haría falta si alguien discutiera una reclamación sobre esa obra.

## Reglas de negocio

- `RN-0` Eliminar la cuenta **la anonimiza**: se suprime todo dato personal y se conserva,
  sin autor identificable, lo que pertenece a terceros.
- `RN-1` La eliminación **exige confirmación explícita**.
- `RN-2` Exige **reautenticación**: contraseña actual o su equivalente en Google.
- `RN-3` El nombre de usuario **queda bloqueado 30 días** y después se libera.
- `RN-4` Se publica `UserDeleted` para que cada contexto aplique **su** política. `User` no
  decide qué hace `Credits` con el saldo ni `Feedback` con las correcciones.
- `RN-5` La operación es **asíncrona**: se marca la cuenta y cada contexto reacciona. Borrar
  en cascada de forma síncrona a través de seis contextos sería una transacción distribuida,
  justo lo que la arquitectura evita.
- `RN-6` Desde que se solicita, la cuenta **deja de poder iniciar sesión**.
- `RN-7` La advertencia de la interfaz **debe describir lo que realmente ocurre**: que los
  datos personales se eliminan y que las correcciones, comentarios y movimientos de créditos
  permanecen sin autor identificable.
- `RN-8` La anonimización es **irreversible** y **no reconstruible**: no se conserva ninguna
  tabla desde la que recuperar la identidad.
- `RN-9` El `userId` **sobrevive** como identificador sin datos detrás. Las correcciones y los
  movimientos de créditos siguen apuntando a él.
- `RN-10` Cada contexto anonimiza **lo suyo**. `User` no borra filas de `Feedback` ni de
  `Credits`: publica el hecho y cada uno aplica su política (`RN-4`).

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
- [ ] Las correcciones que otros pagaron siguen siendo accesibles para el autor que las pagó.
- [ ] Tras anonimizar, **ningún dato personal permanece** en la fila del usuario.
- [ ] No existe forma de reconstruir la identidad desde los datos conservados.
- [ ] Las correcciones y movimientos siguen resolviendo su `userId` sin romperse.
- [ ] La deuda de una cuenta eliminada se contabiliza como emisión, no se da por cobrada.
- [ ] El texto de la advertencia coincide con lo que ocurre.

## Estado

**Especificación:** `APPROVED` (2026-09-24). `U-3` resuelta: las obras dejan de ser accesibles y las
correcciones conservan sus efectos sin referenciarlas. `V-4` —qué ocurre con los mensajes
directos, que forman parte de la conversación de otro— sigue abierta, pero no impide
implementar la anonimización.

**Implementación:** desbloqueada (2026-09-26). `U-3` y `V-4` resueltas: las obras se retiran de circulación conservando las correcciones, y los mensajes directos se conservan sin autor identificable.

`S-32` está resuelta —se anonimiza— y con ella el fondo del asunto. Lo que falta son dos
casos concretos:

- **`U-3`, las obras propias.** La anonimización dice qué pasa con la persona, no con su
  obra. Si las obras desaparecen, se llevan por delante las correcciones que varios lectores
  escribieron y cobraron; si permanecen, se publica obra inédita de alguien que ha pedido
  irse. La combinación que suele funcionar: **retirar las obras de circulación y conservar
  las correcciones para quienes las escribieron**, sin acceso al texto original.
- **`V-4`, los mensajes directos.** Borrarlos vacía la conversación del otro interlocutor,
  que no ha pedido nada. **Resuelta:** se conservan sin autor identificable.

Sigue siendo de las pocas funcionalidades donde implementar antes de decidir es irreversible
por partida doble: los datos borrados no vuelven, y los conservados después de prometer lo
contrario tampoco se pueden justificar.
