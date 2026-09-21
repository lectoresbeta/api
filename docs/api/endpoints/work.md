# Endpoints — `Work`

> Estado: **esqueleto**. Las rutas son una propuesta.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /works` | `createWork` | Crear obra | FEAT-WRK-001 | DRAFT |
| `POST /works/uploads` | `uploadManuscript` | Crear obra desde fichero | FEAT-WRK-002 | PENDING |
| `GET /works/{workId}` | `getWork` | Metadatos de la obra | FEAT-WRK-004 | PENDING |
| `GET /works/{workId}/content` | `getWorkContent` | Contenido completo | FEAT-WRK-004 | PENDING |
| `PATCH /works/{workId}` | `updateWork` | Editar metadatos | FEAT-WRK-005 | PENDING |
| `DELETE /works/{workId}` | `deleteWork` | Eliminar obra | FEAT-WRK-006 | PENDING |
| `GET /works` | `searchWorks` | Buscar en el catálogo | FEAT-WRK-012 | PENDING |
| `GET /me/works` | `listMyWorks` | Obras propias | FEAT-WRK-004 | PENDING |
| `POST /works/{workId}/chapters` | `addChapter` | Añadir fragmento | FEAT-WRK-003 | PENDING |
| `GET /works/{workId}/chapters` | `listChapters` | Listar fragmentos | FEAT-WRK-003 | PENDING |
| `GET /chapters/{chapterId}` | `getChapter` | Leer un fragmento | FEAT-WRK-004 | PENDING |
| `PUT /chapters/{chapterId}` | `updateChapter` | Editar fragmento | FEAT-WRK-005 | PENDING |
| `DELETE /chapters/{chapterId}` | `deleteChapter` | Eliminar fragmento | FEAT-WRK-005 | PENDING |
| `PUT /chapters/{chapterId}/visibility` | `setChapterVisibility` | Visibilidad del fragmento | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/visibility` | `setWorkVisibility` | Visibilidad de la obra | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/access-mode` | `setAccessMode` | Modalidad de acceso de LB | FEAT-WRK-007 | PENDING |
| `GET /works/{workId}/questionnaire` | `getQuestionnaire` | Ver cuestionario | FEAT-WRK-014 | PENDING |
| `PUT /works/{workId}/questionnaire` | `updateQuestionnaire` | Definir cuestionario | FEAT-WRK-014 | PENDING |
| `POST /works/{workId}/public-link` | `createPublicLink` | Crear enlace público | FEAT-WRK-010 | PENDING |
| `DELETE /public-links/{linkId}` | `revokePublicLink` | Revocar enlace público | FEAT-WRK-010 | PENDING |
| `GET /public-links/{token}` | `readByPublicLink` | Leer sin sesión | FEAT-WRK-010 | PENDING |
| `GET /works/{workId}/share-link` | `getShareLink` | Enlace para redes sociales | FEAT-WRK-011 | PENDING |
| `GET /works/{workId}/authorship-records` | `listAuthorshipRecords` | Registros de autoría | FEAT-WRK-009 | BLOCKED |

---

## `POST /works`

**`operationId`:** `createWork` · **Funcionalidad:** [`FEAT-WRK-001`](../../features/work/FEAT-WRK-001-create-work-with-editor.md)

### Propósito

Crear una obra con el editor. El autor es siempre el usuario autenticado.

### Autorización

Requiere sesión. Cualquier usuario autenticado puede crear obras propias.

### Reglas aplicadas

`RN-1` a `RN-8` de `FEAT-WRK-001`.

### Entrada

Título, contenido (uno o varios fragmentos) y metadatos opcionales.

**No se aceptan:** `authorId`, `wordCount` ni `textTier`. Los tres los determina el servidor;
aceptarlos permitiría falsear la autoría o el coste en créditos de la obra.

Pendiente (`Q-2`): si los fragmentos se envían en esta misma petición o se añaden después.

### Respuesta

`201 Created` con la obra, incluyendo `wordCount` y `textTier` calculados.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Título vacío o sin contenido |

### Efectos

Publica `WorkCreated`. Genera un `AuthorshipRecord`. **No mueve créditos.**

---

## Nota sobre autorización de lectura

`GET /works/{workId}/content` y `GET /chapters/{chapterId}` son los endpoints más sensibles
de la API: devuelven obra inédita.

- Solo el autor y quien tenga `BetaReaderAccess` vigente.
- Obra o fragmento `HIDDEN` para un tercero: `404`, nunca `403`. Confirmar que existe una
  obra oculta ya es una fuga.
- El acceso se comprueba **en cada petición**, no solo al conceder el acceso.
