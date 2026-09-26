---
id: FEAT-USR-042
title: Preferencias de apariencia (tema)
context: User
concept: Preferences
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/settings.md
  - conversation:2026-09-26
endpoints:
  - GET /me/appearance-settings
  - PUT /me/appearance-settings
events: []
depends_on: [FEAT-USR-027]
updated: 2026-09-26
---

# FEAT-USR-042 — Preferencias de apariencia (tema)

## Resumen

Claro, oscuro o lo que diga el sistema. Es la última fila de la pantalla de **Configuración** y
la más pequeña de todas: un valor por persona.

## Por qué el backend guarda algo que pinta el cliente

La pregunta es legítima, porque un tema se puede guardar entero en el navegador y no molestar a
nadie.

Se guarda aquí por una razón concreta: **el tema tiene que sobrevivir al dispositivo**. Quien
eligió oscuro en el portátil y abre la aplicación en el móvil espera oscuro, y una preferencia
en `localStorage` no viaja. Lo mismo al cerrar sesión y volver.

Y viaja en el **contexto de sesión** (`FEAT-USR-027`), no en una petición propia: el layout ya
pide esa respuesta en cada carga, y pedir el tema aparte significaría pintar la pantalla en
claro y cambiarla a oscuro medio segundo después, que es peor que no tener la preferencia.

## `SYSTEM` es el valor por defecto, y es explícito

Tres valores y no dos:

| Valor | Qué significa |
|---|---|
| `LIGHT` | Claro siempre |
| `DARK` | Oscuro siempre |
| `SYSTEM` | Lo que diga el sistema operativo de quien mira |

`SYSTEM` por defecto porque es lo que hace el resto del mundo y porque **no decide por nadie**:
quien tiene el móvil en oscuro ya dijo lo que quería, y abrirle la aplicación en claro sería
contradecirle en la primera pantalla.

Y **se guarda explícitamente**, igual que los interruptores de recepción (`FEAT-USR-011`): una
fila que falta no puede significar a la vez «no ha decidido» y «que mande el sistema». Sin fila
se lee el mismo valor por defecto, dicho en voz alta.

## Solo el tema

La pantalla no tiene capturas, así que la ficha se queda en lo que el registro nombra: **el
tema**. Nada de tamaño de letra, densidad o animaciones reducidas.

No es un olvido: cada uno de esos sería otra decisión de producto sin diseño que la respalde, y
un campo que se añade «porque cabe» es un campo que hay que mantener en la API, en el contrato
y en la pantalla para siempre. La tabla admite otro valor el día que exista uno.

**No confundir con `FEAT-USR-016`**, que es la apariencia de la *página de autor*: aquella la
elige el autor y la ven los demás, esta la elige cada quien y solo la ve él.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Leer y cambiar su tema | Sesión. **No hace falta activar la cuenta** |

Se puede cambiar sin activar, y a propósito: es una preferencia de pantalla, no una operación
sobre contenido, y obligar a activar la cuenta para poner el modo oscuro sería absurdo.

## Reglas de negocio

- `RN-1` Un valor por persona. `LIGHT`, `DARK` o `SYSTEM`.
- `RN-2` Sin fila, `SYSTEM`.
- `RN-3` Un valor desconocido se rechaza nombrándolo. No se ignora ni se cae al valor por
  defecto: quien manda `oscuro` cree que ha cambiado algo.
- `RN-4` Viaja en el **contexto de sesión**, para que la primera pintada ya sea la correcta.
- `RN-5` No publica eventos. A nadie fuera de `User` le importa de qué color ve alguien la
  pantalla.
- `RN-6` No hace falta cuenta activada.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Valor desconocido | Se rechaza | `422` `UNKNOWN_THEME` |
| Sin `theme` en el cuerpo | Se rechaza | `422` `UNKNOWN_THEME` |
| Sin sesión | | `401` |

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Ver mi tema | `GET /me/appearance-settings` | `getMyAppearanceSettings` | `openapi/paths/settings.yaml` |
| Cambiarlo | `PUT /me/appearance-settings` | `updateMyAppearanceSettings` | `openapi/paths/settings.yaml` |

`PUT` y no `PATCH`: hay un solo campo, y no existe la diferencia entre «no lo toques» y
«cámbialo» cuando solo hay uno.

El campo `theme` se añade además a la respuesta de `GET /me/context`.

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Tabla nueva `user_ctx.appearance_settings`: `user_id` (clave), `theme`, `updated_at`.
Migración `Version20260928030000`.
