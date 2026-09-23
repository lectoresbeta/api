---
id: FEAT-MOD-012
title: Comando de creación del primer administrador
context: Moderation
concept: ModeratorRole
actors: [Admin]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (el primer admin se activa por comando)
endpoints: []
events: [ModeratorRoleGranted]
depends_on: [FEAT-MOD-004]
updated: 2026-09-23
---

# FEAT-MOD-012 — Primer administrador

## Resumen

El rol de administrador lo concede otro administrador. Para el primero eso no sirve, así que
se crea con un **comando de consola**.

```bash
bin/console lectoresbeta:admin:grant <email>
```

## Reglas de negocio

- `RN-1` El comando **concede el rol a una cuenta que ya existe**. No crea usuarios: quien va
  a administrar se registra como todo el mundo.
- `RN-2` La cuenta debe estar **activada**.
- `RN-3` La ejecución queda en el **registro de auditoría**, con actor `CONSOLE`.
- `RN-4` Es **idempotente**: ejecutarlo dos veces no cambia nada.
- `RN-5` También permite **revocar**, que es la salida si el único administrador pierde el
  acceso.
- `RN-6` No hay ninguna otra vía de crear un administrador **desde la API**.

`RN-1` evita el patrón habitual de crear una cuenta técnica sin dueño: una cuenta con el
máximo privilegio y sin persona detrás es la que nadie vigila.

`RN-6` es lo que hace que el registro de auditoría signifique algo. Si existiera un endpoint
para autoconcederse el rol, cualquier fallo de autorización sería catastrófico.

## Por qué un comando y no una semilla en la base de datos

Una semilla crea el administrador **en todos los entornos por igual**, incluidos los de
desarrollo, con credenciales conocidas. Es el origen clásico del `admin/admin` que sobrevive
hasta producción.

El comando exige una acción deliberada, sobre una cuenta real, en el entorno concreto.

## Criterios de aceptación

- [ ] El comando concede el rol a una cuenta existente y activada.
- [ ] Falla con un mensaje claro si la cuenta no existe o no está activada.
- [ ] Es idempotente.
- [ ] Permite revocar.
- [ ] Queda registrado en auditoría con actor `CONSOLE`.
- [ ] No existe ningún endpoint que conceda el rol de administrador.
- [ ] No hay ninguna semilla que cree administradores automáticamente.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-38 | ¿Cuántos administradores debería haber como mínimo? | Con uno solo, perder su acceso deja la plataforma sin gobierno |
| MOD-39 | ¿Exige el rol de administrador segundo factor obligatorio? | `MOD-17` lo da por bueno para el backoffice; conviene que aquí sea innegociable |

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
