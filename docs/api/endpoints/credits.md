# Endpoints — `Credits`

> Convenciones transversales en [`../conventions/`](../conventions/). Esquemas en `openapi/`.
>
> Estado: **una sola operación especificada**. El resto del contexto trabaja por eventos, no
> por HTTP.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `GET /api/v1/credits/balance` | `getCreditBalance` | Saldo del usuario autenticado | FEAT-CRD-001 | **Implementado** |
| `GET /credits/transactions` | `listCreditTransactions` | Historial de movimientos | FEAT-CRD-008 | PENDING |
| `POST /corrections/{correctionId}/tip` | `tipCorrection` | Propina a una corrección | FEAT-CRD-017 | PENDING |
| `GET /admin/credits/health` | `getEconomyHealth` | Estado agregado de la economía | FEAT-CRD-012 | PENDING |

**La mayor parte de este contexto no tiene API.** Los créditos se mueven al recibir eventos de
integración, nunca por una llamada HTTP de otro contexto
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)). No existe —ni
debe existir— un endpoint para sumar o restar créditos.

---

## `GET /api/v1/credits/balance`

**`operationId`:** `getCreditBalance` · **Funcionalidad:** [`FEAT-CRD-001`](../../features/credits/FEAT-CRD-001-check-credit-balance.md)

### Propósito

Devolver el saldo de créditos del usuario autenticado.

### Autorización

Cualquier usuario autenticado, **solo su propio saldo**. La ruta no lleva identificador: el
usuario sale del token, de modo que no hay ninguna comprobación de pertenencia que se pueda
olvidar.

No existe variante para consultar el saldo ajeno. La vista agregada de la economía es
[`FEAT-CRD-012`](../../features/credits/FEAT-CRD-012-economy-health.md) y trabaja con totales.

**Una cuenta sin activar puede llamarla.** La restricción de
[`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md) afecta a
las escrituras; esto es una lectura, y la cabecera de la aplicación la necesita desde el primer
momento.

### Reglas aplicadas

`RN-1` a `RN-4` de [`FEAT-CRD-001`](../../features/credits/FEAT-CRD-001-check-credit-balance.md).

### Respuesta

`200 OK`.

```json
{ "balance": 10 }
```

Un **único** entero con signo. No hay «saldo disponible» frente a «saldo total» porque el
sistema no retiene créditos ([`decision:0006`](../../decisions/0006-credit-system.md) §3).

Dos consecuencias que el cliente debe respetar:

- **`balance` puede ser negativo** ([`FEAT-CRD-018`](../../features/credits/FEAT-CRD-018-negative-balance.md)).
  Un cliente que lo trate como natural mostrará cifras equivocadas justo en el caso en que el
  usuario más necesita entender lo que pasa.
- **Un usuario sin movimientos devuelve `0`, no `404`.** Es el caso normal de una cuenta
  recién registrada y aún sin activar.

`Cache-Control: no-store`. El saldo cambia por hechos ajenos a quien lo consulta —alguien
entrega una corrección sobre su obra— así que una respuesta cacheada es una respuesta falsa.

### Errores específicos

Ninguno propio. `401` sin autenticar, según [`../conventions/errors.md`](../conventions/errors.md).

**No hay `404`.**

### Efectos

Ninguno. Es una lectura: no publica eventos ni mueve créditos.
