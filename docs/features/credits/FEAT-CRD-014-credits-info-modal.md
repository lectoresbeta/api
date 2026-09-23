---
id: FEAT-CRD-014
title: Modal informativo del sistema de créditos
context: Credits
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P2
sources:
  - figma:1800-14718 (Info Credits)
  - docs/ui/home.md
endpoints: []
events: []
depends_on: [FEAT-CRD-001]
updated: 2026-09-24
---

# FEAT-CRD-014 — Modal informativo del sistema de créditos

## Resumen

Modal que explica en dos columnas cómo se ganan y cómo se gastan los créditos. Se abre desde
el icono ⓘ del bloque de créditos del menú lateral, disponible en toda la aplicación.

Es contenido explicativo, no una operación de negocio. Su valor documental está en lo que
**revela sobre el modelo de créditos**, que no coincide con lo documentado.

## Contenido

| Zona | Texto |
|---|---|
| Título | «Gana créditos y úsalos para mejorar tús textos.» |
| Obtén créditos | «Consigue créditos leyendo textos de otros usuarios y aportando feedback valioso.» |
| Usa tús créditos | «Utiliza tus créditos para poner tus obras en corrección y recibir feedback de los demás usuarios.» |
| Botón izquierdo | «Ver puntuación de créditos» → `FEAT-CRD-015` |
| Botón derecho | «Consulta tús movimientos.» → `FEAT-CRD-008` |

Las dos ilustraciones son marcadores de posición.

## Reglas de negocio

- `RN-1` El modal es contenido estático: no consulta el saldo ni ninguna regla en vivo.
- `RN-2` Los dos botones llevan a funcionalidades reales, no a texto: la tabla de puntuación
  (`FEAT-CRD-015`) y el historial de movimientos (`FEAT-CRD-008`).
- `RN-3` El texto **no debe fijar cifras concretas**. Las cantidades viven en
  `FEAT-CRD-015`, que se sirve desde el backend; repetirlas aquí garantizaría que un día
  digan cosas distintas.

## Lo que este modal cambia respecto a lo documentado

### «Poner tus obras en corrección»

`credit-system.pdf` establece que el autor paga **al recibir cada comentario**
(`FEAT-CRD-006`). El modal describe otra mecánica: se paga **al poner la obra en
corrección**, es decir, al ofrecerla para que la comenten.

| | Modelo documentado | Modelo que sugiere el modal |
|---|---|---|
| Cuándo se paga | Por cada comentario recibido | Una vez, al abrir la obra a feedback |
| Coste para el autor | Desconocido de antemano, crece con cada comentario | Conocido y acotado |
| Riesgo | El saldo puede agotarse a mitad | Ninguno: si no hay saldo, no se abre |
| Encaje con `C-1` | Es la opción A, post-pago | Es la opción B, reserva previa |

**Esta es la decisión `C-1`**, la más importante que sigue abierta, y el modal se inclina por
la opción que `FEAT-CRD-006` ya recomendaba.

No se ha cambiado el modelo: la contradicción queda registrada para que se resuelva de forma
explícita, porque afecta al diseño de dos bounded contexts.

### «Corrección» como vocabulario de producto

Una obra «en corrección» parece ser una obra publicada y abierta a recibir feedback. El menú
lateral del diseño contiene además un bloque llamado `Correcciones`, lo que indica que el
término es de producto y no una licencia del redactor.

Si designa un estado del ciclo de vida de `Work`, hay que nombrarlo, añadirlo al glosario y
documentar sus transiciones (`M-2`, y `W-5` de `Work`).

## Criterios de aceptación

- [ ] El modal se abre desde el icono ⓘ del menú lateral en cualquier pantalla.
- [ ] El botón «Ver puntuación de créditos» lleva a la tabla de puntuación.
- [ ] El botón «Consulta tus movimientos» lleva al historial (`FEAT-CRD-008`).
- [ ] El texto del modal no contiene cifras de créditos codificadas.
- [ ] El modal se puede cerrar con «×» y con la tecla de escape.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **M-1** | **¿Los créditos se gastan al poner la obra en corrección o al recibir cada comentario?** | **Bloqueante.** Es `C-1`. Define `FEAT-CRD-006`, `FEAT-CRD-009` y el flujo entre `Feedback` y `Credits` |
| M-2 | ¿Qué es una obra «en corrección»? ¿Es un estado de `Work`? | Vocabulario y ciclo de vida sin documentar |
| M-3 | ¿Qué ilustraciones acompañan a cada columna? | Pendientes de producir |
| M-4 | ¿El modal se muestra solo bajo demanda, o también la primera vez de forma automática? | Se solapa con el paso 4/4 del tour (`FEAT-USR-026`) |

## Erratas del diseño

- «tús» por «tus», tres veces: en el título, en «Usa tús créditos» y en «Consulta tús
  movimientos.».
- El botón «Consulta tús movimientos.» termina en punto; los demás botones no.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
