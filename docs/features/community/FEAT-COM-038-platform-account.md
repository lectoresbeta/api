---
id: FEAT-COM-038
title: Publicaciones de la plataforma (cuenta institucional)
context: Community
concept: Post
actors: [Admin]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/home.md
  - conversation:2026-09-25
endpoints:
  - publishPlatformPost
events: []
depends_on: [FEAT-COM-001, FEAT-COM-002, FEAT-MOD-012]
updated: 2026-09-25
---

# FEAT-COM-038 — Publicaciones de la plataforma

## Resumen

La cuenta «Lectores Beta», con la que habla el producto, y la forma de publicar en su nombre.

Junto con [`FEAT-COM-017`](FEAT-COM-017-home-work-recommendations.md) responde a `H-8` de
`docs/ui/home.md`: qué ve en la Home alguien que todavía no sigue a nadie. Obras que puede
corregir, autores a los que puede seguir, y lo que diga la plataforma.

## La decisión es que no es nada nuevo

**Es una cuenta normal con una marca.** Se consideraron dos alternativas y las dos pagaban
más de lo que valían:

| | Lo hecho | Un `PostType: PLATFORM` | Una entidad `Announcement` |
|---|---|---|---|
| El muro | **No cambia** | Una rama nueva: publicación sin autor | Mezclar dos listas al servir |
| Pintar la tarjeta | Como cualquiera | Caso aparte, sin avatar ni `@` | Caso aparte |
| Comentar, apoyar, repostear | Sale gratis | Sale gratis | Hay que duplicarlo o renunciar |

Y lo que hace que funcione ya estaba escrito: el muro **lo compone la audiencia, no el
seguimiento** (`FEAT-COM-001`). Una publicación `EVERYONE` llega a todo el mundo siga a quien
siga, así que la cuenta institucional alcanza a quien no sigue a nadie sin una sola línea
nueva en la consulta del muro.

## Reglas de negocio

- `RN-1` Lo que publica la cuenta institucional llega a todo el mundo, incluido quien no sigue
  a nadie. No por una regla nueva: por la audiencia.
- `RN-2` La marca se pone **por consola**, no desde la API, por lo mismo que el primer
  administrador (`FEAT-MOD-012` `RN-6`): quien pueda marcar una cuenta como institucional
  puede hacer que sus mensajes lleguen a todo el mundo con la voz de la plataforma, y eso no
  debe poder concederse por una petición HTTP por muy protegida que esté.
- `RN-3` El comando **no crea la cuenta**. Quien va a hablar en nombre de la plataforma se
  registra y activa como todo el mundo. Una cuenta que existe sin que nadie la haya dado de
  alta es una cuenta que nadie vigila.

  Sin cuenta designada, publicar responde `409 NO_PLATFORM_ACCOUNT`. Es un estado normal de
  una instalación recién puesta en marcha, no una avería.
- `RN-4` **Quien administra firma la acción; quien aparece en la tarjeta es la cuenta
  institucional.** La publicación no sale en el muro propio de quien la publicó.
- `RN-5` Se comenta, se apoya y se repostea como cualquier otra publicación.
- `RN-6` Exige `ROLE_ADMIN` y no `ROLE_MODERATOR`: hablar con la voz de la plataforma no es
  moderar.
- `RN-7` **La audiencia no se acepta del cuerpo**: siempre `EVERYONE`. Un anuncio de la
  plataforma para seguidores no significa nada, porque a la cuenta institucional no se la
  sigue para enterarse.
- `RN-8` La marca **no da ningún privilegio**. No salta límites, no modera y no ve nada que
  otra cuenta no vea. Lo único que cambia es que la interfaz puede distinguirla y que quien
  administra puede publicar en su nombre.
- `RN-9` Se puede bloquear y dejar de ver, como a cualquiera. Quien lo haga renuncia a los
  anuncios, y es su decisión: una cuenta que no se puede silenciar es un altavoz, no una
  cuenta.

## Relevo

Se marca la nueva y se desmarca la vieja, **en dos ejecuciones y en ese orden**, para que no
haya un instante sin ninguna. Por eso la columna no lleva índice único: dos cuentas marcadas
no rompen ninguna invariante —publicarían las dos— y la restricción solo impediría el relevo
ordenado.

Si hubiera varias, el contrato responde la más antigua.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Publicar como la plataforma | `POST /api/v1/admin/platform-posts` | `publishPlatformPost` |

Y un comando de consola:

```text
bin/console lectoresbeta:platform:account <email> [--revoke]
```

## Cómo cruza el límite entre contextos

`Community` no sabe qué cuenta es la institucional: lo pregunta por el contrato publicado
`PlatformAccount`, que devuelve **un identificador y nada más**. Quien quiera pintarla usa las
tarjetas de perfil, como con cualquier persona — que es justo la gracia de que sea una cuenta
normal.

## Eventos

Ninguno propio. Publica `PostPublished`, como cualquier otra publicación.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Una columna, `user_ctx.account.institutional`. Ninguna tabla.

## Criterios de aceptación

- [x] Lo que publica la plataforma llega a quien no sigue a nadie.
- [x] La tarjeta la firma la cuenta institucional, no quien administra.
- [x] La publicación no aparece en el muro propio de quien la publicó.
- [x] Se comenta, se apoya y se repostea como cualquier otra.
- [x] Sin cuenta designada, se dice con claridad.
- [x] Un moderador no puede publicar en nombre de la plataforma; un administrador sí.
- [x] La audiencia no se puede estrechar desde el cuerpo.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
