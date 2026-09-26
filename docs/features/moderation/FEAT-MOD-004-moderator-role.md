---
id: FEAT-MOD-004
title: Rol de moderador y aviso de reclamaciones
context: Moderation
concept: ModeratorRole
actors: [Admin, Moderator]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
endpoints:
  - GET /admin/moderators
  - PUT /admin/users/{userId}/moderator-role
events: [ModeratorRoleGranted, ModeratorRoleRevoked]
depends_on: []
updated: 2026-09-24
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
| `Admin` | Todo lo anterior, **más conceder y revocar el rol de moderador**, gestionar usuarios y ordenar ajustes de créditos. Es el «superadministrador»: resuelve lo que ningún moderador puede resolver |

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
- `RN-7` Una reclamación **que afecta a un moderador no la resuelve él**. Si no queda ningún
  moderador elegible, **la resuelve el `Admin`** (`MOD-15`).
- `RN-8` Se envía **un correo por cada reclamación** que entra, no un resumen periódico
  (`MOD-16`).
- `RN-9` El acceso al backoffice exige **segundo factor** (`MOD-17`).
- `RN-10` El primer `Admin` se crea **por comando de consola**
  ([`FEAT-MOD-012`](FEAT-MOD-012-bootstrap-admin.md)), nunca desde la API.

`RN-2` es una excepción deliberada y hay que tenerla clara: son correos de trabajo, no
notificaciones de producto. Someterlos a las preferencias personales significaría que el
sistema deja de avisar de una denuncia porque alguien desactivó las notificaciones hace meses.

`RN-5` tenía una consecuencia incómoda —con pocos moderadores, una reclamación puede quedarse
sin nadie elegible— y `RN-7` es la salida: **el `Admin` es el último recurso**. No es una regla
de excepción sino parte del diseño, porque el conflicto de interés no se negocia ni siquiera
cuando la cola aprieta.

`RN-9` no es una precaución de trámite. El backoffice permite leer **obra inédita y datos
personales de cualquier usuario**: es la cuenta más valiosa de la plataforma para quien quiera
atacarla.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar moderadores | `GET /admin/moderators` | `listModerators` |
| Conceder o revocar el rol | `PUT /admin/users/{userId}/moderator-role` | `setModeratorRole` |
| Preferencia de aviso | `PUT /me/moderator-alerts` | `setModeratorAlerts` |

Todo bajo `/admin` exige rol, y **cada llamada queda auditada**, también las de lectura: saber
quién consultó los datos de un usuario importa tanto como saber quién los cambió.

## Criterios de aceptación

- [x] Solo un `Admin` concede o revoca el rol de moderador.
- [x] Al llegar una reclamación, se avisa a los moderadores con el aviso activado.
- [x] Ese aviso llega **aunque el usuario tenga las notificaciones desactivadas**.
- [x] Un moderador puede desactivar el aviso sin perder el rol.
- [x] Revocar el rol cierra el acceso de inmediato y devuelve sus casos a la cola.
- [x] Cada acción en `/admin`, incluidas las lecturas, queda registrada.
- [x] Un moderador sin rol no puede llamar a ningún endpoint de `/admin`.
- [x] Una reclamación sobre un moderador llega al `Admin` si no hay otro elegible.
- [x] Entra un correo por cada reclamación registrada.
- [ ] El acceso al backoffice exige segundo factor. *(`RN-9`: no hay 2FA en la plataforma todavía.)*
- [x] No existe ninguna vía por API de conceder el rol de `Admin`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-42 | ¿Qué pasa si el propio `Admin` es parte implicada? | Es el último recurso: por encima no hay nadie |
| MOD-16b | Con un correo por reclamación, ¿hace falta agrupar los de un mismo expediente? | Diez denuncias agrupadas no deberían ser diez correos |

Resueltas: `MOD-6` (**comando de consola**,
[`FEAT-MOD-012`](FEAT-MOD-012-bootstrap-admin.md)), `MOD-15` (**decide el `Admin`**),
`MOD-16` (**un correo por reclamación**) y `MOD-17` (**segundo factor obligatorio**).

`MOD-42` no tiene solución técnica limpia y conviene saberlo: si la única cuenta con el máximo
privilegio está implicada, la salida es organizativa —otro `Admin`— no de producto.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-26). Todo **salvo `RN-9`**, y por eso no es `DONE`.

El **segundo factor para el backoffice no está**: no hay 2FA en ninguna parte de la
plataforma todavía, así que implementarlo aquí sería construir la mitad de una funcionalidad
transversal dentro de una ficha de moderación. Queda anotado como lo que es — un requisito
pendiente de una cuenta que lee obra inédita y datos personales de cualquiera— y debería
resolverse antes de que el backoffice se use de verdad.

~~`RN-8` —un correo por reclamación— llega cuando exista la reclamación.~~ — **existe**
([`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md)), y la preferencia que lo enciende y lo apaga
también.

**Cómo se resuelve el rol es la decisión que sostiene `RN-4`.** El token no lleva roles
([`decision:0007`](../../decisions/0007-jwt-sessions.md)), así que se consultan contra la base
de datos **en cada petición autenticada**, a través del contrato publicado `ModeratorRoles`.
Cuesta una lectura por clave primaria; a cambio, retirar el rol cierra el backoffice en el
acto en vez de dentro de quince minutos, que es justo lo que no se puede permitir en la cuenta
más valiosa de la plataforma.

Un `Admin` lleva también `ROLE_MODERATOR`: es un nivel por encima y no un rol distinto.

Conceder el rol a quien lo tuvo **reactiva su ficha** en vez de crear otra —la clave es la
cuenta, y su historia importa— y vuelve a encender el aviso por correo: quien recupera el rol
vuelve al trabajo, y el silencio hay que elegirlo otra vez.
