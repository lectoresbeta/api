---
screen: Gestionar la foto de perfil
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-037, FEAT-USR-028]
actors: [User]
updated: 2026-09-22
---

# Gestionar la foto de perfil

Flujo que se abre desde el lápiz del avatar en [Mi perfil](my-profile.md). Tiene dos entradas
distintas según el usuario tenga foto o no.

## Recorrido

```text
  SIN FOTO                              CON FOTO
    │ lápiz del avatar                    │ lápiz del avatar
    ▼                                     ▼
  ┌─ «Añadir foto» ────┐          ┌─ «Foto de perfil» ──────────┐
  │ recomendaciones    │          │  vista previa circular      │
  │ [Cámara] [Cargar]  │          │  [Eliminar] [Editar] [Cambiar foto]
  └────────────────────┘          └──┬────────────┬──────────┬──┘
    │                                │            │          │
    │ tipo no admitido                │            │          │
    ├──▶ aviso rojo, modal abierto    │            │          │
    │                                 │            │          │
    ▼                                 │            ▼          │
  ┌─ «Editar foto» ────────────┐◀─────┘     ┌─ «Añadir foto» ─┘
  │ vista previa + recorte     │            └─ (vuelve al inicio)
  │ Zoom · Girar · Desplazar   │
  │ [Cancelar] [Guardar]       │
  └────────────────────────────┘
    │                                  ┌─ «Eliminar foto de perfil» ─┐
    ▼                                  │  confirmación                │
  Avatar actualizado + aviso verde     │  [Cancelar] [Eliminar]       │
                                       └──────────┬───────────────────┘
                                                  ▼
                                        Avatar por defecto
```

## 1. Modal «Añadir foto»

Se abre cuando **no hay foto**, y también al pulsar «Cambiar foto».

| Elemento | Contenido |
|---|---|
| Título | «Añadir foto» y «×» |
| Icono | Nube de subida |
| Texto | «No es necesario que sea una foto de estudio, pero sí que siga unas recomendaciones» |
| Recomendación | «Dimensiones óptimas 180 x 180px» |
| Recomendación | «Tamaño máximo del archivo 2MB» |
| Acciones | «Usar la cámara» · «Cargar foto» (principal) |

Las dimensiones son **recomendación** —el texto dice «óptimas»—; el tamaño máximo es un
límite real que el servidor debe imponer.

## 2. Modal «Editar foto»

| Elemento | Contenido |
|---|---|
| Título | «Editar foto» y «×» |
| Vista previa | La imagen con **máscara circular** de recorte |
| Controles | **Zoom** y **Girar**, deslizadores, y **desplazar** la imagen arrastrándola |
| Acciones | «Cancelar» · «Guardar cambios» (principal) |

Tres transformaciones: escala, rotación y desplazamiento. Las tres las aplica el cliente
antes de subir.

## 3. Modal «Foto de perfil»

Se abre desde el lápiz **cuando ya hay foto**. Es el concentrador de las tres acciones.

| Elemento | Contenido |
|---|---|
| Título | «Foto de perfil» y «×» |
| Vista previa | El avatar actual, circular, con borde de acento |
| Acción destructiva | **«Eliminar»**, a la izquierda, con icono de papelera |
| Acción | **«Editar»** — reabre «Editar foto» para reencuadrar |
| Acción principal | **«Cambiar foto»** — abre «Añadir foto» para subir otra |

La separación visual de «Eliminar» —alineada al otro extremo, sin relleno— es deliberada:
es la única acción destructiva de las tres.

## 4. Modal «Eliminar foto de perfil»

| Elemento | Contenido |
|---|---|
| Título | «Eliminar foto de perfil» y «×» |
| Texto | «¿Estás seguro? Tener foto de perfil ayuda a que los demás te reconozcan.» |
| Acciones | «Cancelar» · «Eliminar» (principal) |

Se superpone al modal anterior, que permanece visible detrás. Al confirmar, el usuario vuelve
al **avatar por defecto**.

## «Editar» obliga a conservar el original

Es la consecuencia importante de estas pantallas, y **corrige lo que se dio por supuesto al
decidir que el recorte lo hace el cliente**.

Entonces se asumió que no haría falta conservar el original: el navegador recorta, sube el
resultado y basta. Con «Editar» eso deja de sostenerse.

| Si solo se guarda la imagen recortada | Si se guarda también el original |
|---|---|
| «Editar» solo puede recortar **dentro** de una imagen de 180 px | Se puede alejar, desplazar y girar con libertad |
| Lo que se recortó una vez no se recupera nunca | Se recupera todo el encuadre |
| Cada edición degrada la calidad | La calidad se mantiene: siempre se parte del original |

**Propuesta: guardar las dos imágenes.** El original queda como material de trabajo del editor
y la recortada es la que se muestra. El cliente sigue recortando, así que la decisión anterior
no cambia; lo que cambia es que sube **dos ficheros** en lugar de uno.

Conviene además guardar el encuadre aplicado —escala, rotación y desplazamiento— para que
«Editar» reabra el editor **donde el usuario lo dejó** y no en una posición por defecto.

Ver `F-2` y `F-10`.

## Requisitos de backend derivados

| # | Requisito | Nota |
|---|---|---|
| 1 | Subir el avatar ya recortado | `PUT /me/profile/avatar` |
| 2 | **Conservar el original** para poder reencuadrar | Consecuencia de «Editar» |
| 3 | Guardar el encuadre aplicado | Para reabrir el editor donde se dejó |
| 4 | Eliminar la foto y volver al avatar por defecto | `DELETE /me/profile/avatar` |
| 5 | Borrar también los ficheros al eliminar | Original y recortada |
| 6 | Límite de **2 MB**, impuesto en servidor | El cliente lo anuncia; el servidor lo aplica |
| 7 | Validar que es imagen **por su contenido** | Ver `file-uploads.md` |
| 8 | **Eliminar los metadatos EXIF** | Pueden contener geolocalización |
| 9 | Distinguir los errores por código | Tipo, tamaño, fallo de subida |

## Estados de aviso

| Caso | Aviso | Comportamiento |
|---|---|---|
| Subida correcta | Verde: «Tu foto de perfil se ha subido correctamente» | El avatar se actualiza en perfil, cabecera y caja de publicación |
| Tipo no admitido | Rojo: «No se acepta el tipo de archivo. Selecciona una imagen.» | El modal «Añadir foto» permanece abierto |
| Tamaño excedido | **Sin diseño** | `F-6` |
| Fallo de red o de almacenamiento | **Sin diseño** | `F-6` |
| Eliminación correcta | **Sin diseño** | `F-11` |

Que el avatar aparezca en tres sitios a la vez importa: la cabecera se pinta con el contexto
de sesión (`FEAT-USR-027`), así que **hay que refrescarlo** tras subir o eliminar la foto, o
el usuario verá una versión abajo y otra arriba.

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | Reaparece la insignia «0» junto al nombre | Insignia de nivel ya descartada como error de diseño. **No se implementa** |
| A-2 | El texto llama «foto de perfil» a lo que la documentación llama avatar | Mismo campo, sin impacto técnico |
| A-3 | En el segundo modal, los botones del de atrás aparecen reordenados | Detalle de maqueta |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **F-2** | **¿Se conserva el original?** «Editar» lo exige para poder alejar y desplazar | Sin él, editar solo recorta hacia dentro y degrada la imagen |
| **F-3** | ¿Se acepta HEIC? La cámara de iOS lo produce por defecto | Sin conversión, las fotos desde iPhone fallan |
| F-10 | ¿Se guarda el encuadre aplicado para reabrir el editor donde se dejó? | Sin él, «Editar» empieza desde una posición por defecto |
| F-11 | ¿Hay aviso de confirmación tras eliminar la foto? | Las demás acciones sí lo tienen |
| F-5 | ¿El mismo flujo sirve para la imagen de portada? | Otras proporciones y otras recomendaciones |
| F-6 | Faltan avisos: tamaño excedido, fallo de red, imagen corrupta | Solo hay diseño para el tipo no admitido |
| F-7 | ¿Hay límite de cambios de foto por periodo? | Un avatar es vector de contenido inapropiado y no hay moderación (`V-1`) |
| F-12 | ¿El avatar por defecto es único o se genera a partir del nombre? | El diseño muestra siempre la misma silueta |

## Erratas del diseño

- La insignia «0» debe retirarse de los modales: no hay sistema de niveles.
