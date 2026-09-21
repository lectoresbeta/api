# 0003 — Las operaciones de escritura exigen tener la cuenta activada

- **Estado:** Aceptada
- **Fecha:** 2026-09-21
- **Afecta a:** `User`, `Work`, `Reading`, `Feedback`, `Community`, `Credits`

## Contexto

El diseño del alta permite completar el onboarding sin haber activado la cuenta: el aviso de
verificación aparece como panel informativo junto a los tres pasos, no como bloqueo. Eso
reduce la fricción del registro, que es deseable.

Pero deja una cuenta funcional creada solo con una dirección de correo que nadie ha
comprobado. Y Lectores Beta tiene dos incentivos económicos que lo convierten en un problema
concreto:

- cada cuenta nueva recibe **20 créditos** de bienvenida;
- invitar a alguien que después participa otorga **+5 créditos** al invitador
  (`FEAT-CRD-005`).

Con el registro sin verificar, generar créditos consiste en inventar direcciones de correo.
No es un riesgo teórico: es el camino más corto para romper la economía de la plataforma,
que es el mecanismo central del producto.

## Decisión

Una cuenta en `PENDING_ACTIVATION` puede leer y completar el onboarding, pero **no puede
ejecutar ninguna operación de escritura**. Además:

- los **20 créditos de bienvenida se abonan al activar la cuenta**, no al registrarla:
  `Credits` consume `AccountActivated`, no `UserRegistered`;
- las obras de un autor sin activar **no admiten comentarios**, de modo que el bloqueo cubre
  también lo que la cuenta recibe, no solo lo que hace.

La comprobación se implementa como una política única aplicada en el borde HTTP, no repetida
en cada controlador.

Alcance detallado en [`FEAT-USR-025`](../features/user/FEAT-USR-025-block-writes-until-activation.md).

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Bloquear el onboarding hasta activar | La más simple de razonar | Rompe el diseño: el usuario tendría que salir a su correo antes de poder avanzar | El diseño ya decidió lo contrario, y con motivo: abandonaría mucha gente |
| Dejar escribir y abonar créditos al registrar | Cero fricción | Cuentas falsas gratis, con saldo y capacidad de farmear el crédito por invitación | Compromete el mecanismo central del producto |
| Limitar por volumen en vez de por estado | Permite empezar a usar la plataforma | Un límite alto no frena el abuso, uno bajo molesta a usuarios legítimos | Complejidad sin resolver el problema |
| Verificar el email solo para retirar valor | Ataca el punto exacto | No hay retirada de valor: los créditos se gastan dentro | No aplica en este modelo |

## Consecuencias

**Positivas**

- Verificar el correo pasa a ser la barrera natural contra el registro masivo, sin coste para
  el usuario legítimo, que solo tiene que abrir un email.
- Los créditos existen únicamente asociados a direcciones reales.
- El alta sigue siendo fluida: el onboarding no se interrumpe.
- El estado de la cuenta queda como un único punto donde razonar sobre permisos.

**Negativas**

- Un usuario que termine el onboarding y no active la cuenta llega a un Home donde no puede
  hacer nada. Hace falta comunicarlo bien (`A-2`).
- La entrega del correo de activación se vuelve crítica: un correo que no llega es un usuario
  que no puede usar la plataforma. Refuerza los requisitos de `FEAT-NOT-008`.
- `Feedback` necesita conocer el estado del autor de la obra para aplicar `RN-4`, lo que
  añade una proyección alimentada por eventos.

**Coste de revertirla**

Bajo en la dirección de relajar el bloqueo. Alto en la contraria: si se lanza sin la barrera
y aparecen cuentas con saldo obtenido de forma fraudulenta, hay que decidir qué hacer con
créditos ya gastados.

## Cumplimiento

- La política se aplica en un único punto del borde HTTP; una comprobación duplicada en un
  controlador es motivo de rechazo en revisión.
- Test funcional por área: una cuenta `PENDING_ACTIVATION` recibe `403` en cada familia de
  operaciones de escritura.
- Test de que `Credits` no abona nada al consumir `UserRegistered`.
