---
screen: Crear una publicación
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-COM-002, FEAT-COM-019, FEAT-COM-028, FEAT-COM-029]
actors: [User]
updated: 2026-09-22
---

# Crear una publicación

Modal que se abre al pulsar la caja «¿Qué estás pensando?», tanto en la
[Home](home.md) como en la pestaña «Mi muro» de [Mi perfil](my-profile.md).

## El modal

| Zona | Contenido |
|---|---|
| Cabecera | Avatar, **nombre con un desplegable** (chevron) y, debajo, **«Publicar para cualquiera»**. «×» a la derecha |
| Cuerpo | Área de texto con el marcador «¿Qué estás pensando?» |
| Pie | Tres iconos de adjunto: **vídeo**, **imagen** y **enlace** |
| Acción | Botón **«Publicar»** |

La caja de la pestaña dice «Enviar» y el modal dice «Publicar». Conviene unificar (`P-7`).

## Hallazgo: hay selector de audiencia

**«Publicar para cualquiera»**, con un desplegable junto al nombre. Es la primera vez que
aparece: hasta ahora una publicación no tenía audiencia, solo intención y formato.

Implica que una publicación lleva un tercer atributo, y que el muro debe **filtrar por él**
en cada consulta. No es un detalle de interfaz: cambia quién ve qué.

El diseño solo muestra el valor por defecto, así que **no se sabe qué otras opciones hay**.
Candidatas razonables: solo seguidores, solo lectores beta de la obra, solo yo. Ver `C-1`.

Hasta conocerlas, no se puede especificar el filtrado del muro ni el modelo.

## Hallazgo: se puede adjuntar vídeo

Los tres iconos del pie son vídeo, imagen y enlace. **El vídeo no estaba contemplado.**

La nota de diseño de la Home enumeraba los formatos como «Solo texto, Texto+imagen, Artículo
(link)». El vídeo añade una cuarta forma y arrastra consigo consideraciones que la imagen no
tiene: tamaño, duración, transcodificación, reproducción y coste de almacenamiento.

Ver `C-2`.

## Hallazgo: una publicación puede llevar un relato dentro

En el muro se ve una publicación cuyo cuerpo es una **tarjeta de obra**: portada, título «Los
Guardianes del Horizonte», etiqueta de género «Ficción», «10 min lectura» y sinopsis.

No es un enlace externo: es **contenido de la plataforma embebido en la publicación**. La
tarjeta es la misma que aparece en el carrusel de la Home (`FEAT-COM-017`).

Es distinto de un artículo externo:

| | Enlace externo | Relato de la plataforma |
|---|---|---|
| Qué se guarda | La URL y su previsualización | El `WorkId` |
| Qué se muestra | Título y descripción cacheados | La tarjeta viva, con sus datos actuales |
| Si el destino cambia | La previsualización se queda obsoleta | La tarjeta se actualiza sola |
| Si el destino desaparece | Enlace roto | La tarjeta desaparece y el texto se queda |

Ver [`FEAT-COM-028`](../features/community/FEAT-COM-028-embed-a-work-in-a-post.md), que
resuelve la última fila: borrar la publicación porque el autor de la obra la archivó sería
dejar que uno borrase el texto del otro sin saberlo.

## Cómo se renderiza un repost

La captura del muro muestra dos publicaciones reposteadas:

```text
⟳ BEATRIZ ALONSO REPOSTEÓ
┌──────────────────────────────────────┐
│ (avatar) Pedro Martínez · 3 días  ···│
│ texto original                       │
│ [tarjeta de obra o imagen]           │
│ ♡ 999  💬 999  ⟳ 999  ↗ 999          │
└──────────────────────────────────────┘
```

- Encabezado discreto en mayúsculas con quien repostea.
- Debajo, **la publicación original completa**: su autor, su antigüedad, su contenido y sus
  contadores.
- **No hay texto propio de quien repostea**: no es una cita, es una republicación.

De aquí salen dos preguntas de modelo, `C-3` y `C-4`.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Crear una publicación de texto | `FEAT-COM-002` |
| 2 | Adjuntar una imagen | `FEAT-COM-002` |
| 3 | Adjuntar un **vídeo** | `FEAT-COM-002`, `C-2` |
| 4 | Adjuntar un **enlace** con previsualización | `FEAT-COM-002` |
| 5 | Incluir un **relato** de la plataforma | `FEAT-COM-028` |
| 5b | Exigir el relato cuando la intención es buscar lectores beta | `FEAT-COM-003` |
| 6 | Audiencia de la publicación, y filtrado del muro por ella | `FEAT-COM-029` |
| 7 | Repostear y servir el original embebido | `FEAT-COM-019` |
| 8 | El muro propio, paginado | `FEAT-COM-026` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-1** | **¿Qué opciones tiene el selector de audiencia?** | **Resuelta (2026-09-26): `EVERYONE` y `FOLLOWERS`**, las dos que el muro ya filtra en la consulta. Desbloquea `FEAT-COM-029` |
| **C-2** | ¿Se admite vídeo? ¿Con qué límites de tamaño y duración? ¿Se transcodifica? | Coste de almacenamiento y proceso muy superior al de una imagen |
| C-3 | ¿Los contadores de un repost son los del original o los suyos propios? | Define si el repost es una entidad con interacciones o un puntero |
| C-4 | ¿Se puede repostear con comentario propio? El diseño no lo muestra | Cambia el modelo de `Repost` |
| C-5 | ¿Se puede adjuntar más de una cosa a la vez: texto, imagen y enlace juntos? | El pie sugiere que sí; la nota de diseño de la Home sugería formatos excluyentes |
| C-6 | ¿Cuál es la longitud máxima del texto? | Validación |
| C-7 | ¿Qué ocurre con una publicación cuyo relato embebido se elimina o se oculta? | La tarjeta se queda sin destino |
| C-8 | ¿La previsualización de un enlace externo la genera el backend? | Implica peticiones salientes desde el servidor |
| C-9 | ¿Se puede editar o eliminar una publicación propia? | El menú «···» existe pero no hay diseño de sus opciones sobre publicaciones propias |
| P-7 | «Enviar» en la caja y «Publicar» en el modal | Coherencia |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | La insignia de nivel ahora muestra «5» | Sigue siendo la insignia descartada. **No se implementa** |
| A-2 | La descripción del perfil se trunca con «Ver más» | Requisito menor de `FEAT-USR-028`: devolver el texto completo y truncar en cliente |
| A-3 | La biografía dice «soy Laura» en el perfil de Beatriz Alonso | Texto de maqueta |
