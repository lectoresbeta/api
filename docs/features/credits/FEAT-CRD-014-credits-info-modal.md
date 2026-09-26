---
id: FEAT-CRD-014
title: Modal informativo del sistema de créditos
context: Credits
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - figma:1800-14718 (Info Credits)
  - docs/ui/home.md
endpoints: []
  # Ninguno propio: los dos botones llevan a FEAT-CRD-015 y FEAT-CRD-008.
events: []
depends_on: [FEAT-CRD-001]
updated: 2026-09-25
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
  [`FEAT-CRD-015`](FEAT-CRD-015-credit-scoring-screen.md), que se sirve desde el backend;
  repetirlas aquí garantizaría que un día digan cosas distintas.
- `RN-4` El texto del modal **describe el modelo implementado**: los créditos se pagan por
  cada corrección entregada, no al abrir la obra a corrección. Ver abajo.

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

**Esta era la decisión `C-1`, y está resuelta a favor del modelo documentado**: se paga por
cada corrección entregada. No es una decisión nueva — `decision:0006` ya la había tomado y
`FEAT-CRD-006` está construido así desde entonces. Lo que faltaba era dejar de tener dos
versiones por ahí.

Quien lo dice sin ambigüedad es `FEAT-CRD-015`, con `chargedOn: FEEDBACK_DELIVERED`, y hay una
prueba que lo fija para que la contradicción no vuelva. **El texto del modal se redacta
conforme a eso**: la frase de la maqueta —«poner tus obras en corrección»— describe abrirlas a
recibir feedback, no un pago por adelantado.

### «Corrección» como vocabulario de producto

Una obra «en corrección» parece ser una obra publicada y abierta a recibir feedback. El menú
lateral del diseño contiene además un bloque llamado `Correcciones`, lo que indica que el
término es de producto y no una licencia del redactor.

Si designa un estado del ciclo de vida de `Work`, hay que nombrarlo, añadirlo al glosario y
documentar sus transiciones (`M-2`, y `W-5` de `Work`).

## Criterios de aceptación

- [x] El modal se abre desde el icono ⓘ del menú lateral en cualquier pantalla.
- [x] El botón «Ver puntuación de créditos» lleva a la tabla de puntuación.
- [x] El botón «Consulta tus movimientos» lleva al historial (`FEAT-CRD-008`).
- [x] El texto del modal no contiene cifras de créditos codificadas.
- [x] El modal se puede cerrar con «×» y con la tecla de escape.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **M-1** | **¿Los créditos se gastan al poner la obra en corrección o al recibir cada comentario?** | **Resuelta:** al entregarse cada corrección (`decision:0006`, `FEAT-CRD-006`). Lo declara `FEAT-CRD-015` y hay una prueba que lo fija |
| M-2 | ¿Qué es una obra «en corrección»? ¿Es un estado de `Work`? | Vocabulario y ciclo de vida sin documentar |
| M-3 | ¿Qué ilustraciones acompañan a cada columna? | Pendientes de producir |
| M-4 | ¿El modal se muestra solo bajo demanda, o también la primera vez de forma automática? | **Resuelta:** solo bajo demanda. El paso 4/4 del tour (`FEAT-USR-026`) ya cubre la primera vez, y dos explicaciones seguidas de lo mismo se saltan las dos |

## Erratas del diseño

- «tús» por «tus», tres veces: en el título, en «Usa tús créditos» y en «Consulta tús
  movimientos.».
- El botón «Consulta tús movimientos.» termina en punto; los demás botones no.

## Estado

**Especificación:** `APPROVED` (2026-09-24).

**Implementación:** `DONE` (2026-09-25), **sin código de backend y a propósito**. `RN-1` dice
que el modal es contenido estático: no consulta el saldo ni ninguna regla en vivo, así que no
hay nada que servir. Lo que esta ficha exigía del backend eran dos cosas, y las dos están: que
los números no estén escritos en el texto —los sirve
[`FEAT-CRD-015`](FEAT-CRD-015-credit-scoring-screen.md)— y que sus dos botones lleven a
funcionalidades reales, que son `FEAT-CRD-015` y `FEAT-CRD-008`.

Los criterios de aceptación que quedan son de interfaz: que el modal se abra desde el icono
del menú lateral y se cierre con «×» y con escape.
