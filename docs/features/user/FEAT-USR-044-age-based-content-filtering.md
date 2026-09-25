---
id: FEAT-USR-044
title: Filtrado automático de contenido por edad
context: User
concept: Account
actors: [User, Guest]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - conversation:2026-09-25 (bloque de contenido sensible)
  - docs/features/work/FEAT-WRK-017-content-rating.md
endpoints: []
events: []
depends_on: [FEAT-USR-022, FEAT-WRK-017]
updated: 2026-09-25
---

# FEAT-USR-044 — Filtrado automático de contenido por edad

## Resumen

Una obra marcada `ADULTS_ONLY` no se sirve a quien no es mayor de edad. En el catálogo, al
abrir la obra, al leer un capítulo, al pedir el cuestionario y al corregir.

No es una preferencia: es una regla del sistema, y el usuario no la desactiva. Lo que sí
elige son las **preferencias de contenido sensible**, que son otra cosa y están en
[`FEAT-USR-043`](FEAT-USR-043-content-preferences.md).

## La regla, en una frase

**Quien no ha declarado fecha de nacimiento no es mayor de edad.**

Tratar lo no declarado como adulto convertiría un paso opcional del onboarding en la forma de
saltarse el filtro, y sería además la forma más fácil: no contestar.

## Dónde se aplica

| Punto | Qué pasa si no es mayor de edad |
|---|---|
| Catálogo | La obra **no aparece** |
| Abrir la obra | `404`, no `403`: que exista ya es información |
| Leer un capítulo | `404` |
| Pedir el cuestionario | `404` |
| Empezar una corrección | Se rechaza |
| Solicitar acceso de lector beta | Se rechaza |

El `404` en lugar del `403` es el criterio general de la plataforma
([`errores`](../../api/conventions/errors.md)): si alguien no debería saber siquiera que el
recurso existe, `404`. Un `403` que dijera «esto es para adultos» sería un índice de qué obras
lo son.

## Cómo viaja la edad

Por el contrato publicado `ReaderMaturity`, que responde **un booleano y nada más**.

No una fecha, no una edad: `Work` tiene que decidir si sirve un texto, y para eso no necesita
saber cuándo nació nadie. La fecha de nacimiento es dato privado y no sale de `User`
([`FEAT-USR-022`](FEAT-USR-022-onboarding-profile-data.md) `RN-4b`).

Tampoco viaja en el token de acceso, que no lleva datos personales
([`decision:0007`](../../decisions/0007-jwt-sessions.md) `RN-4`). Se resuelve en cada petición,
que es además lo que hace que cumplir años tenga efecto el mismo día.

## Reglas de negocio

- `RN-1` **Mayor de edad son 18 años cumplidos.** El umbral lo define `User` y ningún otro
  contexto lo conoce.
- `RN-2` Quien **no ha declarado** fecha de nacimiento **no es mayor de edad**.
- `RN-3` Quien **no tiene sesión** no es mayor de edad. Un visitante anónimo nunca ve contenido
  `ADULTS_ONLY`.
- `RN-4` El filtro **no se puede desactivar**. No es una preferencia ni un ajuste.
- `RN-5` Se aplica en **todos** los puntos de la tabla anterior, y la comprobación es del lado
  del servidor siempre. Que la interfaz no enseñe un botón no es una comprobación.
- `RN-6` **El autor ve siempre su propia obra**, sea cual sea su edad y su clasificación.
- `RN-7` La clasificación la pone el autor ([`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md))
  y la puede revisar moderación. Este filtro **no la decide**, solo la aplica.
- `RN-8` Una obra que pasa a `ADULTS_ONLY` **deja de servirse inmediatamente** a quien no
  tenga edad, incluidos los lectores beta que ya tuvieran acceso concedido.
- `RN-9` La fecha de nacimiento, una vez declarada, **no se cambia libremente**: el camino es
  una solicitud a soporte. Un campo editable es el segundo modo de saltarse el filtro.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Menor pide una obra `ADULTS_ONLY` | No existe para él | `404` |
| Usuario sin fecha de nacimiento declarada | Igual que un menor | `404` |
| Visitante sin sesión | Igual | `404` |
| El autor, menor de edad, pide su propia obra | La ve | `200` |
| Menor intenta empezar una corrección | Se rechaza | `404` |

## Contrato de API

Ninguno propio. **Es una regla transversal, no una pantalla**: lo que cambia es el
comportamiento de operaciones que ya existen.

## Eventos

Ninguno. Preguntar la edad no es un hecho, y publicarlo sería sacar de `User` justo el dato
que no debe salir.

## Modelo de datos afectado

Ninguno nuevo. `User` ya guarda la fecha de nacimiento y `Work` la clasificación.

## Criterios de aceptación

- [ ] Una obra `ADULTS_ONLY` no aparece en el catálogo de quien no es mayor de edad.
- [ ] Abrirla directamente por su identificador responde `404`.
- [ ] Un capítulo suyo responde `404`.
- [ ] El cuestionario responde `404`.
- [ ] No se puede empezar una corrección sobre ella.
- [ ] No se puede solicitar acceso de lector beta.
- [ ] Quien no ha declarado fecha de nacimiento recibe el mismo trato que un menor.
- [ ] Un visitante sin sesión, también.
- [ ] El autor ve su propia obra aunque sea menor.
- [ ] Marcar una obra como `ADULTS_ONLY` la retira de inmediato a los lectores beta menores.
- [ ] Ningún endpoint devuelve la fecha de nacimiento de otra persona.
- [ ] Ninguna respuesta de error revela que la obra existe.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| U-22 | **Invitar a alguien menor a ser lector beta de una obra `ADULTS_ONLY`.** ¿Se impide al invitar, o solo al leer? | Hoy la invitación se cursa y la lectura falla después: el autor no entiende por qué |
| U-23 | Un **enlace público** (`FEAT-WRK-010`) no tiene sesión detrás. ¿Puede servir una obra `ADULTS_ONLY`? | Sería el agujero más grande del filtro |
| U-24 | ¿Se verifica la edad de alguna forma, o basta con declararla? | Declararla es un trámite de diez segundos para quien quiera saltárselo |
| U-25 | ¿Hay jurisdicciones donde el umbral no sean 18 años? | El umbral está en un sitio, pero es uno solo |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `PARTIAL`. El filtro se aplica ya en seis sitios —catálogo, obra,
capítulo, cuestionario, panel de corrección y solicitud de acceso— a través del contrato
`ReaderMaturity`, que también existe y responde lo que debe.

**Falta**, y son los tres agujeros conocidos:

- la **invitación** a ser lector beta no lo comprueba (`U-22`);
- el **enlace público** no tiene forma de comprobarlo (`U-23`), y hoy la funcionalidad no está
  implementada, así que el agujero todavía no está abierto;
- no hay **prueba funcional** que fije el comportamiento en los seis puntos a la vez, que es
  lo que impide que uno nuevo se escriba sin el filtro.
