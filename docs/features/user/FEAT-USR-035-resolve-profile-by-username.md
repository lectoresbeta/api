---
id: FEAT-USR-035
title: Resolver un perfil por nombre de usuario o alias
context: User
concept: Profile
actors: [Guest, User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - decision:0005
  - conversation:2026-09-22
endpoints: [GET /profiles/{username}]
events: []
depends_on: [FEAT-USR-033, FEAT-USR-034]
updated: 2026-09-22
---

# FEAT-USR-035 — Resolver un perfil por nombre de usuario o alias

## Resumen

La URL pública de un perfil es `lectoresbeta.com/profile/{username}`. El backend resuelve ese
nombre a una cuenta buscando **primero entre los nombres en uso y, si no aparece, entre los
alias vigentes**.

Es lo que mantiene vivos los enlaces compartidos después de un cambio de nombre.

## Orden de resolución

```text
GET /profiles/pabloblanco1
        │
        ├─ ¿hay un user con username = pabloblanco1?
        │       └─ sí ──▶ perfil, resolvedVia: USERNAME
        │
        ├─ ¿hay un alias VIGENTE con ese nombre?
        │       └─ sí ──▶ perfil del titular, resolvedVia: ALIAS
        │                  + canonicalUsername del titular
        │
        └─ no ──▶ 404
```

## Reglas de negocio

- `RN-1` Se busca primero entre los nombres en uso. Solo si no hay coincidencia se miran los
  alias.
- `RN-2` Solo resuelven los alias **vigentes**. Un alias caducado devuelve `404` aunque su
  fila siga existiendo porque el comando de purga aún no ha pasado (`FEAT-USR-036`).
- `RN-3` La respuesta incluye siempre `canonicalUsername`, el nombre actual del titular, y
  `resolvedVia` con el valor `USERNAME` o `ALIAS`.
- `RN-4` Cuando se resuelve por alias, el cliente debería **reemplazar la URL** por la
  canónica. La API da el dato; la redirección es cosa del frontend.
- `RN-5` La búsqueda no distingue mayúsculas: el nombre se normaliza a minúsculas antes de
  consultar.
- `RN-6` Es un endpoint **público**: no requiere sesión, igual que el resto del perfil.
- `RN-7` Devuelve **solo datos públicos**: nunca email ni fecha de nacimiento
  (`FEAT-USR-022`).
- `RN-8` Un perfil de una cuenta eliminada devuelve `404`.
- `RN-9` Los alias con `reason = ACCOUNT_DELETED` **bloquean el nombre pero nunca resuelven**:
  siempre devuelven `404`. No hay perfil al que llevar (`FEAT-USR-034` `RN-13`).

`RN-2` es la regla crítica de toda esta funcionalidad: si se comprueba la existencia de la
fila en lugar de su vigencia, un enlace caducado seguiría funcionando durante días, que es
justo lo que el plazo de un mes pretende acotar.

## Por qué no es una redirección del servidor

Sería natural responder `301` a la URL canónica. No se hace porque **el endpoint sirve datos,
no páginas**: quien construye la URL del navegador es el frontend. Devolver el nombre
canónico y dejar que el cliente actualice la barra de direcciones mantiene la API como API.

Si más adelante hubiera renderizado en servidor, el `301` se resolvería ahí, con los mismos
datos.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Resolver perfil | `GET /profiles/{username}` | `getProfileByUsername` |

Convive con `GET /users/{userId}` (`FEAT-USR-014`), que resuelve por identificador. Son dos
puertas al mismo perfil: una para enlaces humanos y otra para referencias internas.

Las referencias entre recursos de la API usan **siempre `UserId`**, nunca el nombre: el
nombre cambia y el identificador no.

## Rendimiento

La resolución consulta dos tablas en el peor caso. Índices necesarios:

- `user(username)`, único;
- `username_alias(username)`, único;
- `username_alias(expires_at)`, para filtrar por vigencia y para la purga.

Al ser la puerta de entrada a cualquier perfil compartido, es una consulta caliente y
**pública**: conviene protegerla con límite de peticiones para que no sirva para enumerar
nombres (`N-4`).

## Criterios de aceptación

- [ ] Un nombre en uso resuelve al perfil con `resolvedVia: USERNAME`.
- [ ] Un alias vigente resuelve al perfil de su titular con `resolvedVia: ALIAS`.
- [ ] La respuesta incluye siempre el nombre canónico actual.
- [ ] Un alias caducado devuelve `404`, aunque su fila no se haya borrado.
- [ ] Un nombre inexistente devuelve `404`.
- [ ] La resolución no distingue mayúsculas de minúsculas.
- [ ] Funciona sin sesión iniciada.
- [ ] No devuelve email ni fecha de nacimiento.
- [ ] Un perfil de cuenta eliminada devuelve `404`.
- [ ] El nombre de una cuenta eliminada devuelve `404` durante los 30 días en que sigue bloqueado.
- [ ] Ese mismo nombre sigue sin estar disponible para registrarse durante ese mes.
- [ ] Si un usuario registra un nombre que fue alias de otro **ya caducado**, el enlace resuelve al nuevo titular.

El último criterio describe el comportamiento correcto y a la vez el riesgo que el mes de
alias acota: pasado ese plazo, un enlace antiguo puede llevar a otra persona. Es el motivo de
que el plazo exista —en los cambios de nombre y también al borrar la cuenta— y de que no se
pueda acortar sin más.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-11 | ¿La URL es `/profile/{username}` o `/@{username}`? | El diseño muestra `@bealonso`; la URL del ejemplo usa `/profile/` |
| N-12 | ¿Se indexan los perfiles en buscadores? | Un alias que caduca y cambia de titular tendría consecuencias de SEO |
| N-13 | ¿Conviene advertir al visitante de que llegó por un enlace antiguo? | Transparencia frente a suplantación |
| N-4 | ¿Qué límite de peticiones tiene? | Sin él permite enumerar nombres |

## Estado

**Especificación:** `DRAFT`. El mecanismo está completo; falta fijar la forma de la URL.

**Implementación:** `TODO`.
