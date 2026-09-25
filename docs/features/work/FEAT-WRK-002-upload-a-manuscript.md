---
id: FEAT-WRK-002
title: Crear una obra subiendo un fichero
context: Work
concept: Ingest
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/api/conventions/file-uploads.md
  - conversation:2026-09-25
endpoints:
  - uploadManuscript
  - confirmManuscript
events: []
depends_on: [FEAT-WRK-001]
updated: 2026-09-25
---

# FEAT-WRK-002 — Crear una obra subiendo un fichero

## Resumen

El autor sube un manuscrito, el servidor le propone dónde acaba cada capítulo, él lo revisa y
confirma. Lo que nace es **una obra igual que la del editor**: con sus capítulos, sus
temáticas y sus hechos publicados.

Hasta aquí, la única forma de traer una novela a la plataforma era pegarla capítulo a capítulo
en el editor.

## `W-4`, resuelta: el servidor propone y el autor confirma

La pregunta abierta era si los ficheros se trocean automáticamente o a mano. **Ninguna de las
dos por separado.**

Automático y sin confirmar es rápido hasta el día que adivina mal, y entonces alguien recoloca
treinta capítulos a mano. Un fichero por capítulo no adivina nada y obliga a trocear una
novela antes de poder empezar. El flujo de dos pasos —el que ya describía
[`file-uploads.md`](../../api/conventions/file-uploads.md)— es el único que no traslada el
problema al autor.

Y tiene una consecuencia que vale la pena decir: **como hay alguien revisando, el troceador
puede permitirse ser simple**. Se equivoca sin consecuencias.

## `W-3`, resuelta: `.txt` y `.docx`

| Formato | ¿Entra? | Por qué |
|---|---|---|
| `.docx` | **Sí** | Es lo que Word produce hoy. Es un zip con XML dentro: se lee con lo que el servidor ya tiene |
| `.txt` | **Sí** | Trivial, y alguna gente escribe así |
| `.doc` | No | Formato binario OLE de los noventa; leerlo a mano no es razonable y hoy casi nadie lo produce |
| `.pdf` | No | Describe dónde va cada letra, no dónde acaba una idea: los párrafos salen mal. Y es un formato con capacidad de ejecución que `file-uploads.md` ya dice que no se trate como texto inofensivo |

Los dos que faltan **no están cerrados**: `DocumentTextExtractor` es un puerto, y añadirlos es
escribir otro adaptador detrás. Lo que no se hace es meter dos dependencias de parseo —una de
ellas leyendo PDF ajeno en PHP— antes de que alguien las pida.

## El fichero original no se guarda

Decidido con esta ficha, cerrando el pendiente de `file-uploads.md`. Se extrae el texto y el
fichero se descarta.

Conservarlo tenía valor probatorio para el registro de autoría, pero ese registro
(`FEAT-WRK-009`) sigue bloqueado por `W-1` y, cuando se desbloquee, podrá pedir lo que
necesite. Mientras tanto, guardar una copia más de obra inédita duplica el almacenamiento y la
superficie que hay que proteger a cambio de algo que hoy nadie usa.

## Reglas de negocio

- `RN-1` El tipo se decide por **el contenido**, nunca por la extensión ni por el
  `Content-Type` declarado. Renombrar algo a `.docx` es lo primero que prueba quien quiere
  colar otra cosa.
- `RN-2` El troceado sale de **los estilos de título que el autor ya había puesto**
  (`w:pStyle`), no de una heurística sobre líneas en blanco o mayúsculas. Un `.txt` no tiene
  ninguno: se propone un solo capítulo.
- `RN-3` **No se pierde texto.** Lo que hay antes del primer título es el primer capítulo —
  suele ser un prólogo—, y un documento que es solo títulos se devuelve entero como capítulo
  único.
- `RN-4` Un título **sin nada debajo** no abre capítulo. Pasa con una portada o una
  dedicatoria marcadas como título, y crear un capítulo vacío por cada una dejaría al autor
  borrando basura antes de empezar.
- `RN-5` El texto pasa por **el mismo saneador que el editor** (`FEAT-WRK-001`). Un `.docx` es
  literalmente lo que se pega desde Word: no merece más confianza por venir en un fichero.
- `RN-6` El autor puede **corregir los títulos** propuestos y nada más. Reordenar o partir se
  hace después con `FEAT-WRK-003` y `FEAT-WRK-005`; reimplementarlos aquí sería tener dos
  editores.
- `RN-7` Una subida **caduca a las 24 horas** y se borra **al confirmar**. Lo primero porque
  una subida abandonada es una novela ocupando sitio para siempre; lo segundo porque el texto
  ya vive en sus capítulos, y tenerlo dos veces es tenerlo mal una de las dos.
- `RN-8` La subida de otra persona, una caducada y una ya confirmada responden **igual que una
  que no existe**: que exista es información sobre lo que alguien está escribiendo.
- `RN-9` Máximo **10 MB**, comprobado antes de abrir nada. Una novela de 75.000 palabras en
  `.docx` ocupa uno o dos; el resto del margen es para quien trae imágenes pegadas.
- `RN-10` La obra nace en **borrador**, igual que con el editor. Publicarla es otra decisión
  (`FEAT-WRK-016`).

## Lo que se defiende de un fichero ajeno

Un manuscrito lo trae alguien de fuera, así que el adaptador se escribe contando con eso:

- **no se expanden entidades XML ni se abren conexiones** al leer el `.docx` (`LIBXML_NONET`,
  sin `LIBXML_NOENT`). Es la forma clásica de leer un fichero del servidor a través de un
  documento subido;
- **el XML descomprimido tiene su propio tope**. Un zip de un mega puede descomprimirse en
  gigabytes, y el límite del fichero subido se mide antes de descomprimir, así que no protege
  de eso;
- **el nombre original se sanea** y nunca se usa como ruta ni se sirve: solo vale para que el
  autor reconozca su subida;
- **el HTML se construye escapando** cada párrafo antes de envolverlo, aunque el saneador vaya
  detrás. Concatenar texto sin escapar funciona hasta el día que el orden de las dos cosas
  cambia y nadie se entera.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Subir y ver la propuesta | `POST /api/v1/manuscript-uploads` | `uploadManuscript` |
| Confirmar y crear la obra | `POST /api/v1/manuscript-uploads/{uploadId}/work` | `confirmManuscript` |

**Síncrono y no `202`.** Leer un `.docx` es abrir un zip y recorrer un XML, no un trabajo de
fondo, y el tope de 10 MB acota el peor caso. Montar una cola y un endpoint de estado para eso
sería complejidad sin nada al otro lado; el día que entre el `.pdf`, se revisa.

La propuesta viaja **con un extracto por capítulo y sin el texto completo**: devolver una
novela entera para pintar una lista de treinta filas sería mandar el manuscrito de vuelta por
el gusto de enseñar su primer renglón.

## Eventos

**Publica** `WorkCreated` y un `ChapterContentUpdated` **por capítulo**, exactamente como si
se hubieran añadido de uno en uno. Lo que cuesta corregir se decide en `Credits` a partir de
esos hechos, y una ingesta que no los publicara dejaría la obra sin precio.

## Efectos en créditos

Ninguno directo. Los capítulos que nacen aquí tienen precio como cualquier otro, y lo decide
`Credits`.

## Modelo de datos afectado

`work_ctx.manuscript_upload`: una **tabla de paso** con los capítulos propuestos en `jsonb`.
Vive horas y desaparece. Darle tabla propia y filas por capítulo sería montar un modelo
paralelo al de `Chapter` para algo que no llega a mañana.

El comando `lectoresbeta:work:purge-expired-manuscript-uploads` retira lo que nadie confirmó.
Saltarse una ejecución es inocuo —una subida caducada ya no se puede confirmar—, pero conviene
que corra: cada fila es una novela entera.

## Criterios de aceptación

- [x] Un `.docx` se trocea por los títulos que el autor marcó y se convierte en una obra.
- [x] El texto sobrevive el viaje: lo que se lee es lo que acaba en los capítulos.
- [x] Un `.txt` propone un solo capítulo.
- [x] El autor puede corregir los títulos, por posición.
- [x] El texto se sanea igual que el del editor.
- [x] Un formato que no se entiende lo dice, y un zip que no es un `.docx` también.
- [x] Un fichero sin texto no es una obra vacía: lo dice.
- [x] La subida de otra persona no se puede confirmar.
- [x] Confirmar dos veces no crea dos obras.
- [x] Sin título, se usa el nombre del fichero.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| — | ¿Hace falta `.pdf` o `.doc`? | Son un adaptador más detrás del puerto, con sus dependencias |

`W-3` y `W-4` quedan resueltas, y con ellas el pendiente de `file-uploads.md` sobre conservar
el original.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
