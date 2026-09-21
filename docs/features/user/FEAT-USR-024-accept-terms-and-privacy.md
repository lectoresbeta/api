---
id: FEAT-USR-024
title: Aceptar las condiciones de uso y la política de privacidad
context: User
concept: Account
actors: [Guest]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - figma:1800-13778 (1470:9482, checkbox del formulario)
  - docs/ui/account-creation.md
endpoints: [POST /auth/register]
events: []
depends_on: [FEAT-USR-001]
updated: 2026-09-21
---

# FEAT-USR-024 — Aceptar las condiciones de uso y la política de privacidad

## Resumen

El formulario de registro incluye una casilla: «Aceptas nuestras **condiciones de uso** y
nuestra **política de privacidad**», con ambos textos enlazados.

**La exigencia no depende del método de alta**: quien se registra con Google también tiene
que aceptarlos.

Parece un detalle de interfaz y no lo es: la aceptación hay que **poder demostrarla después**,
y eso solo se consigue si el backend registra qué versión concreta se aceptó y cuándo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Aceptar al registrarse | Es obligatorio para completar el registro |

## Reglas de negocio

- `RN-1` El registro no se completa sin la aceptación. Es una validación de servidor, no solo
  del formulario.
- `RN-2` Se registra **la versión concreta** de cada documento aceptado, no un booleano. Un
  `accepted: true` sin versión no demuestra nada si los términos cambian después.
- `RN-3` Se registra la marca temporal de la aceptación.
- `RN-4` El registro de aceptación es inmutable: una aceptación nueva es un registro nuevo.
- `RN-5` Las condiciones de uso y la política de privacidad son documentos independientes y
  se versionan por separado, aunque la casilla sea una sola.
- `RN-7` **La exigencia es la misma sea cual sea el método de alta.** Registrarse con Google
  (`FEAT-USR-002`) también requiere aceptar ambos documentos: el proveedor externo acredita
  quién es la persona, no qué ha aceptado. Sin aceptación no se crea la cuenta.
- `RN-8` Iniciar sesión en una cuenta ya existente no exige volver a aceptar nada.
- `RN-6` El usuario debe poder consultar qué aceptó y cuándo.

`RN-5` importa porque los dos documentos cambian por motivos distintos y con frecuencias
distintas.

## Flujo principal

1. El formulario muestra la casilla con los enlaces a ambos documentos.
2. El usuario la marca.
3. El registro incluye la aceptación.
4. El sistema comprueba que se acepta la versión vigente de cada documento.
5. Guarda la aceptación con su versión y fecha.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Registro sin aceptación | Se rechaza | `422` con `code: TERMS_NOT_ACCEPTED` |
| Alta con Google sin aceptación | **No se crea la cuenta** (`RN-7`) | `422` con `code: TERMS_NOT_ACCEPTED` |
| Se acepta una versión que ya no es la vigente | **Por definir.** Probablemente se rechaza y se pide releer | Pendiente |
| Cambio de términos con usuarios ya registrados | **Fuera de alcance de esta ficha.** Requiere un flujo de reaceptación | Ver `T-2` |

## Contrato de API

Forma parte de `POST /auth/register` (`FEAT-USR-001`) y de
`POST /auth/oauth/google/callback` cuando esa llamada implica **crear** una cuenta
(`FEAT-USR-002`). No tiene endpoint propio en el alta.

Sí necesita, al menos:

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar documentos legales vigentes | `GET /legal/documents` | `getLegalDocuments` |
| Consultar mis aceptaciones | `GET /me/legal-acceptances` | `getMyLegalAcceptances` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `legal_document` | Tipo (`TERMS_OF_USE`, `PRIVACY_POLICY`), versión, fecha de entrada en vigor |
| `legal_acceptance` | `user_id`, documento, versión, fecha. **Inmutable** |

## Diseño (Figma)

`1470:9482`. Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Un registro sin aceptación devuelve `422`, aunque el cliente permita enviarlo.
- [ ] La aceptación guarda la versión de cada documento, no solo un booleano.
- [ ] La aceptación guarda la fecha.
- [ ] Condiciones de uso y política de privacidad se registran por separado.
- [ ] Un registro de aceptación no se puede modificar ni borrar.
- [ ] El usuario puede consultar qué versiones aceptó y cuándo.
- [ ] Un alta con Google sin aceptación no crea la cuenta y devuelve `422`.
- [ ] La aceptación registrada por la vía de Google guarda versión y fecha igual que la de email.
- [ ] Iniciar sesión con Google en una cuenta existente no exige volver a aceptar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| T-1 | ¿Dónde viven los textos legales: en el backend, en el CMS del frontend, o en ficheros estáticos? | Determina si `GET /legal/documents` existe |
| T-2 | ¿Qué ocurre cuando cambian los términos con usuarios ya registrados? ¿Hay reaceptación obligatoria? | Flujo completo sin diseñar |
| T-3 | ¿Hace falta consentimiento separado para comunicaciones comerciales? El pie del correo incluye «Cancelar suscripción» | Requisito probable de protección de datos |
| T-4 | ¿El alta con Google también exige aceptación? | **Resuelto:** sí. Sin aceptación no se crea la cuenta (`RN-7`, `FEAT-USR-002`) |
| T-5 | ¿Cómo se recoge la aceptación en la interfaz de Google: casilla previa o pantalla intermedia? | La regla de backend ya cierra el hueco; falta la decisión de diseño. Ver `FEAT-USR-002` |

## Estado

**Especificación:** `DRAFT`. Resuelto `T-4`. Falta decidir dónde viven los textos legales
(`T-1`) y cómo se recoge la aceptación en el alta con Google (`T-5`).

**Implementación:** `TODO`.
