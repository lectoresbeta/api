---
id: FEAT-MOD-007
title: Registro de auditoría de acciones administrativas
context: Moderation
concept: AuditLog
actors: [Admin]
spec_status: REVIEW
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-25 (bloque de deudas y transparencia)
  - docs/bounded-contexts/moderation.md
endpoints:
  - GET /admin/audit-log
events: []
depends_on: [FEAT-MOD-004, FEAT-MOD-002]
updated: 2026-09-25
---

# FEAT-MOD-007 — Registro de auditoría de acciones administrativas

## Resumen

Toda acción administrativa queda anotada con **quién, qué, sobre qué, cuándo y por qué**. Ya
se anota; lo que falta es poder leerlo.

## Por qué importa que se pueda leer

Un registro que nadie puede consultar **no es un registro: es una tranquilidad**. Invita a
confiar en que hay control sin que lo haya, que es peor que no tenerlo, porque nadie va a
buscar el control por otro lado.

Y hay algo concreto que hoy no tiene respuesta: cuando alguien discute una decisión —una
reclamación estimada que le retiró créditos, un bloqueo de su obra— la única forma de saber
qué pasó es mirar la base de datos a mano.

## Quién puede leerlo

**Solo un `ADMIN`.** Un moderador no audita a sus compañeros.

El registro existe para que el poder que se ejerce sobre los usuarios sea revisable **por
quien responde de la plataforma**, no para vigilancia horizontal dentro del equipo. Saberse
observado por los iguales cambia cómo se decide, y lo cambia hacia decidir lo que no genera
preguntas en vez de lo correcto.

Es coherente con `FEAT-MOD-004`: conceder y retirar roles también es solo de `ADMIN`.

## Qué se anota

Lo que ya escribe `RecordAuditEntry` hoy, y todo lo que se añada después:

| Acción | Quién la anota hoy |
|---|---|
| `MODERATOR_ROLE_GRANTED` / `REVOKED` | `FEAT-MOD-004` |
| `CLAIM_REVIEWED` | `FEAT-MOD-002`, con la **motivación escrita** |

## Reglas de negocio

- `RN-1` Solo un `ADMIN` consulta el registro.
- `RN-2` El registro es **inmutable y solo de inserción**. No hay operación que edite ni borre
  una entrada, ni la habrá: un registro que se puede editar no es un registro.
- `RN-3` Toda acción administrativa se anota **en la misma transacción** que el efecto que
  produce. Un registro que puede perderse mientras el efecto se conserva es peor que no
  tenerlo.
- `RN-4` La entrada lleva **la identidad del actor**, aunque las partes implicadas no la
  conozcan ([`FEAT-MOD-002`](FEAT-MOD-002-review-claim.md) `RN-6`). Un poder que se ejerce sin
  dejar nombre es un poder que nadie puede revisar después.
- `RN-5` El `payload` **nunca contiene contenido**: ni texto de obra, ni de corrección, ni de
  mensajes privados. El registro dice **qué se hizo**, no qué estaba escrito.
- `RN-6` La **motivación** de una decisión sí se guarda y sí se muestra aquí: es material
  interno del expediente y este es el expediente.
- `RN-7` Se filtra por **actor**, por **objeto** (tipo e identificador), por **acción** y por
  **rango de fechas**. Sin filtros, un registro es un montón.
- `RN-8` Se ordena de lo más reciente a lo más antiguo, y se pagina.
- `RN-9` Consultar el registro **no se anota**. Anotar cada lectura convertiría el registro en
  su propio ruido; si en algún momento hace falta auditar a los auditores, será una decisión
  aparte (`MOD-48`).

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es `ADMIN` | Se rechaza | `403` |
| Sin sesión | Se rechaza | `401` |
| Sin entradas que cumplan el filtro | Lista vacía | `200` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar el registro | `GET /admin/audit-log` | `listAuditLog` |

Bajo `/admin`, que ya exige `ROLE_MODERATOR` por `security.yaml`; esta ruta añade su propia
regla de `ROLE_ADMIN`, como ya hace la de conceder roles.

## Eventos

Ninguno. Ni publica ni consume: se escribe desde dentro del contexto, de forma síncrona y
transaccional, precisamente para que no dependa de que una cola entregue nada.

## Modelo de datos afectado

`moderation_ctx.audit_entry` ya existe con `actor_id`, `action`, `target_type`, `target_id`,
`reason`, `payload` y `occurred_at`.

Hacen falta índices para los filtros: `(occurred_at DESC)`, `(actor_id, occurred_at DESC)` y
`(target_type, target_id, occurred_at DESC)`.

## Criterios de aceptación

- [ ] Un `ADMIN` consulta el registro y ve las entradas más recientes primero.
- [ ] Un moderador que no es `ADMIN` recibe `403`.
- [ ] Filtra por actor, por objeto, por acción y por fechas.
- [ ] Una decisión sobre una reclamación aparece con su motivación escrita.
- [ ] Ninguna entrada contiene texto de una obra, de una corrección o de un mensaje.
- [ ] No existe ninguna operación que edite o borre una entrada.
- [ ] Consultar el registro no genera entradas nuevas.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-48 | ¿Se audita quién consulta el registro? | Hoy no. El registro contiene datos sensibles sobre usuarios |
| MOD-49 | ¿Cuánto se conserva una entrada? | Hay obligaciones legales que dependen de la jurisdicción (`MOD-9`) |
| MOD-50 | ¿Puede un moderador consultar **sus propias** acciones para defenderse de una queja? | Hoy no, y es un caso razonable |

## Estado

**Especificación:** `REVIEW` — completa, pendiente de validación.

**Implementación:** `PARTIAL`. El registro se escribe desde `FEAT-MOD-004` y `FEAT-MOD-002`,
con su entidad, su repositorio y su servicio de aplicación. **Falta** poder leerlo: la
consulta, los filtros, los índices y el endpoint restringido a `ADMIN`.
