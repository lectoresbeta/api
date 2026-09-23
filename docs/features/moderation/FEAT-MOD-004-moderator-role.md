---
id: FEAT-MOD-004
title: Rol de moderador y aviso de reclamaciones
context: Moderation
concept: ModeratorRole
actors: [Admin, Moderator]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
endpoints:
  - GET /admin/moderators
  - PUT /admin/users/{userId}/moderator-role
events: [ModeratorRoleGranted, ModeratorRoleRevoked]
depends_on: []
updated: 2026-09-23
---

# FEAT-MOD-004 — Rol de moderador

## Resumen

Un usuario con **rol de moderador** puede acceder al backoffice y resolver reclamaciones.
Cuando llega una reclamación nueva, **se avisa por correo** a los moderadores que tengan ese
aviso activado.

## Dos roles, no uno

Conviene separarlos desde el principio:

| Rol | Puede |
|---|---|
| `Moderator` | Ver la cola y resolver reclamaciones |
| `Admin` | Todo lo anterior, **más conceder y revocar el rol de moderador**, gestionar usuarios y ordenar ajustes de créditos |

Si quien modera puede además nombrar moderadores, no hay forma de auditar cómo se formó el
equipo. Es la separación mínima para que el registro de auditoría signifique algo.

## Reglas de negocio

- `RN-1` El rol lo concede y lo revoca **solo un `Admin`**, y queda en el registro de
  auditoría.
- `RN-2` El aviso por correo es **operativo**: va a quien tiene el rol y el aviso activado, y
  **no depende de las preferencias generales** de notificación del usuario
  ([`FEAT-USR-039`](../user/FEAT-USR-039-notification-preferences.md) `RN-3`).
- `RN-3` El moderador puede **desactivar el aviso** sin perder el rol. Hay quien prefiere
  entrar a la cola cuando puede en vez de recibir correos.
- `RN-4` Perder el rol **cierra el acceso al backoffice de inmediato**, y sus reclamaciones en
  revisión vuelven a la cola.
- `RN-5` Un moderador es **un usuario normal** en todo lo demás: escribe, corrige y recibe
  correcciones como cualquiera. De ahí la regla de conflicto de interés
  ([`FEAT-MOD-002`](FEAT-MOD-002-review-claim.md) `RN-1`).
- `RN-6` Las acciones del moderador se registran **con su identidad**, aunque las partes no la
  conozcan.

`RN-2` es una excepción deliberada y hay que tenerla clara: son correos de trabajo, no
notificaciones de producto. Someterlos a las preferencias personales significaría que el
sistema deja de avisar de una denuncia porque alguien desactivó las notificaciones hace meses.

`RN-5` tiene una consecuencia práctica incómoda: **con pocos moderadores, una reclamación
puede no tener quién la revise** sin saltarse el conflicto de interés. Ver `MOD-15`.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar moderadores | `GET /admin/moderators` | `listModerators` |
| Conceder o revocar el rol | `PUT /admin/users/{userId}/moderator-role` | `setModeratorRole` |
| Preferencia de aviso | `PUT /me/moderator-alerts` | `setModeratorAlerts` |

Todo bajo `/admin` exige rol, y **cada llamada queda auditada**, también las de lectura: saber
quién consultó los datos de un usuario importa tanto como saber quién los cambió.

## Criterios de aceptación

- [ ] Solo un `Admin` concede o revoca el rol de moderador.
- [ ] Al llegar una reclamación, se avisa a los moderadores con el aviso activado.
- [ ] Ese aviso llega **aunque el usuario tenga las notificaciones desactivadas**.
- [ ] Un moderador puede desactivar el aviso sin perder el rol.
- [ ] Revocar el rol cierra el acceso de inmediato y devuelve sus casos a la cola.
- [ ] Cada acción en `/admin`, incluidas las lecturas, queda registrada.
- [ ] Un moderador sin rol no puede llamar a ningún endpoint de `/admin`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-6** | ¿Cómo se crea el primer `Admin`? | No puede concedérselo nadie: hay que sembrarlo |
| **MOD-15** | Con pocos moderadores, ¿qué pasa si todos son parte implicada? | La reclamación se queda sin quien la revise |
| MOD-16 | ¿Se avisa de cada reclamación o hay resumen periódico? | Un correo por denuncia puede ser mucho ruido |
| MOD-17 | ¿Exige el backoffice segundo factor? | Da acceso a contenido inédito de terceros |

`MOD-17` no es una pregunta de trámite: el backoffice permite leer obra inédita y datos
personales de cualquier usuario. Es la cuenta más valiosa de la plataforma para quien quiera
atacarla.

## Estado

**Especificación:** `DRAFT`. `MOD-6` bloquea la implementación: sin un primer `Admin` no hay
forma de arrancar.

**Implementación:** `TODO`.
