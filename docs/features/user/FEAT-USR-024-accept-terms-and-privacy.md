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
| Se acepta una versión que ya no es la vigente | **Por definir.** Probablemente se rechaza y se pide releer | Pendiente |
| Cambio de términos con usuarios ya registrados | **Fuera de alcance de esta ficha.** Requiere un flujo de reaceptación | Ver `T-2` |

## Contrato de API

Forma parte de `POST /auth/register` (`FEAT-USR-001`). No tiene endpoint propio en el alta.

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

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| T-1 | ¿Dónde viven los textos legales: en el backend, en el CMS del frontend, o en ficheros estáticos? | Determina si `GET /legal/documents` existe |
| T-2 | ¿Qué ocurre cuando cambian los términos con usuarios ya registrados? ¿Hay reaceptación obligatoria? | Flujo completo sin diseñar |
| T-3 | ¿Hace falta consentimiento separado para comunicaciones comerciales? El pie del correo incluye «Cancelar suscripción» | Requisito probable de protección de datos |
| T-4 | ¿El registro por Google, Facebook o LinkedIn también exige aceptación? El diseño no lo muestra | **Hueco real**: esos botones no llevan casilla |

`T-4` es el más urgente: tal como está el diseño, quien entre por un proveedor social **no
acepta nada**.

## Estado

**Especificación:** `DRAFT`. Falta resolver `T-4` y decidir dónde viven los textos legales.

**Implementación:** `TODO`.
