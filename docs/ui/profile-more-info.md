---
screen: Mi perfil — Más info
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-029, FEAT-USR-030]
actors: [Writer]
updated: 2026-09-22
---

# Mi perfil — pestaña «Más info»

Trayectoria del autor **fuera** de Lectores Beta. Parte de [Mi perfil](my-profile.md).

## Sub-pestañas

| Sub-pestaña | Contenido | Estado |
|---|---|---|
| **Obras publicadas** | Libros ya editados, con editorial, año y enlace de compra | Documentada aquí |
| **Premios y reconocimientos** | Méritos del autor | **Sin capturas.** `FEAT-USR-030` sigue `PENDING` |

## Obras publicadas

Rejilla de tarjetas. **La última tarjeta es siempre un «+»** para añadir otra, así que el
mecanismo de alta está integrado en la propia rejilla.

```text
┌──────────┐  ┌──────────┐
│ portada  │  │    +     │
│ HYPNOS   │  │          │
└──────────┘  └──────────┘
 Hypnos        Título
 Amazon        Editorial
 2020          Año
 [Comprar]     [Comprar]
```

| Campo | Ejemplo | Nota |
|---|---|---|
| Portada | Imagen real subida por el autor | Formato vertical, proporción de portada de libro |
| Título | «Hypnos» | |
| Editorial | **«Amazon»** | Ver abajo |
| Año | «2020» | |
| Acción | «Comprar» | Enlace externo |

En el estado vacío hay además un botón «Añadir obra» bajo el mensaje; con contenido, esa
función la cumple la tarjeta «+».

### «Editorial» no siempre es una editorial

El ejemplo dice **«Amazon»**, que no es un sello editorial sino una plataforma de venta y
autopublicación.

Es una señal clara de cómo se va a usar el campo: **texto libre**, sin catálogo cerrado y sin
validación contra una lista de editoriales. Quien se autopublica escribirá la plataforma;
quien publica con un sello, el sello. Ambos son respuestas correctas a «¿dónde salió este
libro?».

Validar contra un catálogo dejaría fuera precisamente al perfil más habitual de esta
plataforma: el autor aficionado que se ha publicado por su cuenta.

### La portada es una subida más

La tarjeta muestra una portada real, así que el autor sube una imagen. Es un tipo de fichero
que no estaba en las convenciones: no es un avatar ni una portada de perfil, y su proporción
es distinta —vertical, de libro, no cuadrada ni panorámica—.

Le aplican las mismas reglas que al resto de imágenes: validación por contenido, límite de
tamaño en servidor y **eliminación de metadatos EXIF**.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Listar las obras publicadas de un autor | `FEAT-USR-029` |
| 2 | Añadir, editar y eliminar una obra publicada | `FEAT-USR-029` |
| 3 | Subir la **portada** de una obra publicada | `FEAT-USR-029`, `file-uploads.md` |
| 4 | Editorial como **texto libre**, sin catálogo | `FEAT-USR-029` |
| 5 | Orden de la rejilla | `FEAT-USR-029`, `P-15` |
| 6 | Premios y reconocimientos | `FEAT-USR-030`, sin diseño |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-15 | ¿Quién decide el orden de la rejilla? En la captura, la obra añadida va antes del «+» | Propuesta: el autor, con año descendente por defecto |
| P-18 | ¿Qué proporción y tamaño máximo tiene la portada? | No hay recomendación en pantalla, a diferencia del avatar |
| P-19 | ¿Qué contiene «Premios y reconocimientos»? | Sin capturas. `FEAT-USR-030` no se puede especificar |
| P-16 | ¿Hay límite de obras publicadas por perfil? | Evitar perfiles inflados |
| P-9 | ¿Se valida de algún modo que el libro exista? | La respuesta natural, viendo «Amazon», es que no |
| P-10 | ¿Hay afiliación en el enlace «Comprar»? | Implicaciones comerciales |
| P-20 | ¿Qué muestra la tarjeta si el autor no sube portada? | El marcador de la tarjeta «+» no sirve como portada por defecto |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | Estas capturas usan el layout **ancho** (menú con etiquetas, créditos al pie), no el estrecho de capturas posteriores | Refuerza `I-6`: hay dos iteraciones y no consta cuál es la vigente |
| A-2 | La insignia vuelve a decir «0 Level» en inglés, y en otras capturas «Nivel 5» | Sistema de niveles descartado. **No se implementa** |
| A-3 | «Mis Amigos» con mayúscula, corregido en capturas posteriores | Unificar |
| A-4 | Los contadores están a 0 pese a haber contenido en el perfil | Datos de maqueta |
