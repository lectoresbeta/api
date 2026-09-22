---
screen: Subir foto de perfil
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-037, FEAT-USR-028]
actors: [User]
updated: 2026-09-22
---

# Subir foto de perfil

Flujo que se abre desde el lápiz del avatar en [Mi perfil](my-profile.md). Dos modales
encadenados y dos desenlaces posibles.

## Recorrido

```text
  Perfil sin foto
    │ pulsa el lápiz del avatar
    ▼
  ┌─ Modal «Añadir foto» ─────────────────┐
  │  recomendaciones                      │
  │  [Usar la cámara]  [Cargar foto]      │
  └───────────────────────────────────────┘
    │ elige un fichero
    ├──── tipo no admitido ──▶ aviso rojo, el modal sigue abierto
    ▼
  ┌─ Modal «Editar foto» ─────────────────┐
  │  vista previa con recorte circular    │
  │  Zoom ──────●───────                  │
  │  Girar ─────────●───                  │
  │  [Cancelar]  [Guardar cambios]        │
  └───────────────────────────────────────┘
    │
    ▼
  Avatar actualizado + aviso verde
```

## 1. Modal «Añadir foto»

| Elemento | Contenido |
|---|---|
| Título | «Añadir foto» y «×» para cerrar |
| Icono | Nube de subida |
| Texto | «No es necesario que sea una foto de estudio, pero sí que siga unas recomendaciones» |
| Recomendación | «Dimensiones óptimas 180 x 180px» |
| Recomendación | «Tamaño máximo del archivo 2MB» |
| Acciones | «Usar la cámara» · «Cargar foto» (principal) |

Las dimensiones se presentan como **recomendación**, no como requisito: el texto dice
«óptimas». El tamaño máximo, en cambio, es un límite real que el servidor debe imponer.

«Usar la cámara» no añade nada al backend: produce una imagen que entra por el mismo camino.
Sí tiene una consecuencia práctica, ver `F-3`.

## 2. Modal «Editar foto»

| Elemento | Contenido |
|---|---|
| Título | «Editar foto» y «×» |
| Vista previa | La imagen con una **máscara circular** de recorte superpuesta |
| Control | **Zoom**, deslizador |
| Control | **Girar**, deslizador |
| Acciones | «Cancelar» · «Guardar cambios» (principal) |

El recorte es circular y los dos deslizadores permiten encuadrar. La pregunta que esto abre
para el backend es **quién aplica la transformación**: ver la sección siguiente.

## 3. Éxito

- El avatar se actualiza **en tres sitios a la vez**: la cabecera, el perfil y la caja de
  publicación del muro.
- Aviso verde abajo a la izquierda: «Tu foto de perfil se ha subido correctamente», con «×».

Que el avatar aparezca en tres sitios simultáneos importa: el contexto de sesión
(`FEAT-USR-027`) lo sirve para la cabecera, así que **tras subir la foto hay que refrescarlo**
o el usuario verá su avatar nuevo en el perfil y el viejo arriba.

## 4. Error

- Aviso rojo: «No se acepta el tipo de archivo. Selecciona una imagen.»
- **El modal «Añadir foto» permanece abierto**, para que el usuario pueda reintentar sin
  volver a empezar.

Solo hay diseño para el error de tipo de fichero. Faltan al menos el de tamaño excedido y el
de fallo de subida (`F-6`).

## Quién aplica el recorte

Es la decisión de fondo de esta pantalla.

| Opción | Cómo | Valoración |
|---|---|---|
| **A. El cliente recorta** | El navegador aplica zoom y giro y sube la imagen final, ya cuadrada | **Recomendada.** El servidor recibe algo pequeño y predecible. El usuario ve exactamente lo que va a quedar |
| B. El servidor recorta | Se sube el original más los parámetros de zoom y giro | El servidor hereda la responsabilidad de reproducir la vista previa con exactitud, y cualquier diferencia de redondeo se ve |

**Con cualquiera de las dos, el servidor vuelve a procesar la imagen**: normaliza formato y
tamaño y **elimina los metadatos EXIF**, que pueden incluir geolocalización. Que el cliente ya
haya recortado no exime de eso.

Un matiz de la opción B: si el servidor gira la imagen, tiene que interpretar además la
orientación EXIF del original, o las fotos de móvil aparecerán tumbadas. Con la opción A ese
problema no existe porque el recorte del navegador ya la resuelve.

## Requisitos de backend derivados

| # | Requisito | Nota |
|---|---|---|
| 1 | Aceptar la subida del avatar | `PUT /me/profile/avatar` |
| 2 | Límite de **2 MB**, impuesto en servidor | El cliente lo anuncia; el servidor lo aplica |
| 3 | Validar que es una imagen **por su contenido**, no por la extensión | Ver `file-uploads.md` |
| 4 | Normalizar a imagen cuadrada y redimensionar | 180 × 180 px es la referencia |
| 5 | **Eliminar los metadatos EXIF** | Pueden contener geolocalización |
| 6 | Devolver la URL del avatar ya procesado | Para refrescar las tres vistas |
| 7 | Distinguir los errores por código | Tipo no admitido, tamaño excedido, fallo de subida |

## Contradicciones y anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | En los modales reaparece la insignia «0» junto al nombre | Es la insignia de nivel ya descartada como error de diseño. **No se implementa** |
| A-2 | El texto llama «foto de perfil» a lo que el resto de la documentación llama avatar | Sin impacto técnico: son el mismo campo |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **F-1** | **¿Recorta el cliente o el servidor?** | Determina qué recibe el endpoint: una imagen final o el original más parámetros |
| F-2 | ¿Se conserva el original para poder reencuadrar más tarde sin volver a subir? | Hoy el lápiz reabre «Añadir foto», lo que sugiere que no |
| F-3 | ¿Qué formatos se aceptan? «Usar la cámara» en iOS produce **HEIC**, que los navegadores no muestran bien | Si no se contempla, las fotos hechas desde iPhone fallarán |
| F-4 | ¿Se puede **eliminar** la foto y volver al avatar por defecto? | No hay diseño para ello |
| F-5 | ¿El mismo flujo sirve para la **imagen de portada**? Tiene su propio lápiz pero otras proporciones | Probablemente sí, con otras recomendaciones |
| F-6 | ¿Qué avisos faltan: tamaño excedido, fallo de red, imagen corrupta? | Solo hay diseño para el tipo no admitido |
| F-7 | ¿Hay límite de cambios de foto por periodo? | Un avatar es un vector de contenido inapropiado y hoy no hay moderación (`V-1`) |
| F-8 | ¿Se refresca el contexto de sesión tras subirla? | Si no, la cabecera seguirá mostrando el avatar anterior |

## Erratas del diseño

- La insignia «0» de los modales debe retirarse: no hay sistema de niveles.
