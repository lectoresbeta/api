# 0007 — La sesión de la API se resuelve con JWT

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** `User`, y a toda operación autenticada de la API
- **Resuelve:** `S-1`

## Contexto

Toda la autorización de la plataforma cuelga del mecanismo de sesión, así que `S-1` bloqueaba
cualquier endpoint que no fuese público.

El diseño había ido acumulando, sin que se mirasen juntas, **cuatro reglas que exigen cortar
una sesión de inmediato**:

| Regla | Exige |
|---|---|
| [`FEAT-USR-041`](../features/user/FEAT-USR-041-change-password.md) `RN-3` | Cerrar las demás sesiones al cambiar la contraseña |
| [`FEAT-USR-040`](../features/user/FEAT-USR-040-change-email.md) `RN-8` | Cerrar las demás sesiones al cambiar el correo |
| [`FEAT-MOD-006`](../features/moderation/FEAT-MOD-006-sanctions.md) | Una cuenta `BLOCKED` o con suspensión total no autentica |
| [`FEAT-MOD-006`](../features/moderation/FEAT-MOD-006-sanctions.md) | Una suspensión parcial cambia en caliente lo que el usuario puede hacer |

Un token sin estado no se revoca. Esa es la tensión que había que resolver.

## Decisión

**Se usa JWT**, con la limitación asumida de forma explícita: **la revocación tarda hasta lo
que dure el token, 15 minutos.**

Se acepta a cambio de la simplicidad: sin almacén de sesiones, sin consulta por petición, sin
estado que replicar entre instancias.

### Qué significa en la práctica

| Situación | Qué ocurre |
|---|---|
| El usuario cambia su contraseña | Las demás sesiones dejan de valer **en ≤ 15 min** |
| Se expulsa a un usuario | Sigue dentro **hasta 15 min** |
| Se impone una suspensión | Empieza a notarla **en ≤ 15 min** |
| El usuario cierra sesión | El cliente descarta el token; el servidor no lo sabe |

**Las reglas que dicen «se cierran las demás sesiones» pasan a significar «dejan de ser
válidas en cuanto caduque el token vigente»**, y así deben documentarse: prometer un corte
inmediato que no ocurre es peor que asumir la ventana.

### La ventana no es igual de grave en todos los casos

Conviene tenerlo claro para no sobreproteger lo que no hace falta:

- **Cambio de contraseña y de correo**: 15 minutos es aceptable. El atacante ya tenía acceso;
  la ventana no le da nada nuevo.
- **Expulsión y suspensión**: 15 minutos de margen a alguien que acaba de ser sancionado es
  molesto pero no catastrófico. Lo que sí conviene es que **las operaciones de escritura
  comprueben el estado de la cuenta**, no solo la firma del token: eso reduce la ventana real
  a cero para lo que importa, sin renunciar a la simplicidad en las lecturas.

Esa comprobación puntual es el matiz que hace tolerable la decisión. Sin ella, un usuario
expulsado podría seguir publicando un cuarto de hora.

## Reglas

- `RN-1` El **token de acceso caduca a los 15 minutos**.
- `RN-2` Hay **token de refresco** de vida larga, **almacenado y revocable**. Es el único
  elemento con estado, y es lo que permite que cerrar sesión signifique algo.
- `RN-3` Cambiar contraseña o correo **invalida todos los tokens de refresco** del usuario.
- `RN-4` El token lleva `sub`, `iat`, `exp` y un `jti`. **No lleva datos personales** ni
  permisos que puedan quedar obsoletos.
- `RN-5` **Las operaciones de escritura verifican el estado de la cuenta** contra la base de
  datos. La firma del token no basta para escribir.
- `RN-6` La firma usa una clave del entorno, **nunca en el repositorio**.
- `RN-7` El token viaja en `Authorization: Bearer`. No se guarda en `localStorage` si puede
  evitarse.

`RN-4` merece énfasis: meter roles en el token es la forma más rápida de que alguien conserve
durante quince minutos un permiso que se le acaba de retirar. Los permisos se consultan.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Token opaco en base de datos** | Revocación inmediata, trivial | Una consulta por petición; almacén compartido entre instancias | Más correcto, pero el coste operativo no compensa la ventana de 15 minutos |
| **Sesión de Symfony con cookie** | Simple y seguro por defecto | Mal encaje con clientes que no son navegador; estado compartido | Limita el tipo de cliente desde el primer día |
| **JWT sin refresco** | Lo más simple posible | Cerrar sesión no significaría nada | El token de refresco es lo mínimo para que «cerrar sesión» exista |

## Consecuencias

**Positivas**

- No hay almacén de sesiones que operar ni replicar.
- Las lecturas no consultan nada para autenticar.
- Escalar horizontalmente no requiere estado compartido.

**Negativas**

- **La revocación tarda hasta 15 minutos**, y hay que decirlo en las fichas que prometen lo
  contrario.
- `RN-5` introduce una consulta en cada escritura, así que parte del ahorro se pierde justo
  donde más importa.
- Si la clave de firma se filtra, **todos los tokens vigentes son válidos** y no hay forma de
  invalidarlos salvo rotar la clave y echar a todo el mundo.

**Qué coste tendría revertirla**

Moderado. Cambiar a token opaco afecta al adaptador de autenticación y al cliente, no al
dominio. Conviene que la verificación viva tras un servicio propio desde el principio, para
que sustituirla sea cambiar una implementación.

## Cumplimiento

- Ninguna clase de dominio conoce el JWT: se resuelve en Infrastructure.
- Un test comprueba que el token **no contiene roles ni datos personales**.
- Un test comprueba que una escritura con token válido pero cuenta `BLOCKED` **se rechaza**.
- Las fichas que dicen «se cierran las demás sesiones» indican la ventana de 15 minutos.
