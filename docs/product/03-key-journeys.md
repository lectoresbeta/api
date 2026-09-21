# Recorridos principales

> Estado: `DRAFT` — se completará y corregirá con las páginas de Figma.

Estos recorridos describen el producto de extremo a extremo y sirven para detectar
funcionalidades que faltan. Cada paso enlaza (o enlazará) con su ficha en
[`../features/`](../features/).

---

## J-1. Un escritor consigue feedback de su relato

1. Se registra e inicia sesión → recibe **+20 créditos** de bienvenida (`FEAT-CRD-002`).
2. Crea la obra con el editor o subiendo un fichero (`FEAT-WRK-001`, `FEAT-WRK-002`).
3. El sistema clasifica el texto por extensión y calcula su nivel (`FEAT-WRK-013`).
4. Define el cuestionario: tres preguntas sin coste, las adicionales encarecen cada
   comentario recibido (`FEAT-WRK-014`, `FEAT-CRD-007`).
5. Configura la modalidad de acceso LB y la visibilidad (`FEAT-WRK-007`, `FEAT-WRK-008`).
6. Al crear la obra se genera el registro de autoría (`FEAT-WRK-009`).
7. Promociona la obra: publicación en el muro (`FEAT-COM-003`) o enlace para redes
   sociales (`FEAT-WRK-011`).
8. Recibe solicitudes de lectores beta y las acepta (`FEAT-RDG-003`).
9. Los lectores beta comentan. **Cada comentario recibido le descuenta créditos**
   (`FEAT-CRD-006`). Si no tiene saldo suficiente, el comentario no puede entregarse.
10. Lee, contesta y valora los comentarios (`FEAT-FBK-004`, `FEAT-FBK-005`, `FEAT-FBK-006`).
11. Valorar positivamente un comentario otorga **+5 créditos** a quien lo escribió
    (`FEAT-CRD-004`).

**Punto crítico del recorrido:** el paso 9. Qué ocurre exactamente cuando el autor no tiene
créditos suficientes es la decisión de producto más importante todavía sin resolver
(ver `C-1` en [`../bounded-contexts/credits.md`](../bounded-contexts/credits.md)).

---

## J-2. Un usuario gana créditos dando feedback

1. Busca obras por tipo, temática o valoración (`FEAT-WRK-012`), o responde a una
   publicación de búsqueda de LB en el muro (`FEAT-COM-003`).
2. Obtiene acceso según la modalidad de la obra: automático, solicitando o por invitación
   (`FEAT-RDG-001`, `FEAT-RDG-002`, `FEAT-RDG-005`).
3. Lee la obra (`FEAT-WRK-004`).
4. Deja su feedback y responde al cuestionario (`FEAT-FBK-001`, `FEAT-FBK-003`).
5. Recibe créditos según el nivel de extensión del texto comentado (`FEAT-CRD-003`).
6. Si el autor valora su comentario positivamente, recibe **+5 créditos** adicionales
   (`FEAT-CRD-004`).
7. Su actividad lo posiciona en el ranking de lectores (`FEAT-COM-015`).

---

## J-3. Un visitante comenta mediante enlace público

1. El autor genera un enlace público para la obra (`FEAT-WRK-010`).
2. Lo comparte fuera de la plataforma.
3. Un visitante sin cuenta abre el enlace y lee la obra.
4. Deja un comentario **sin iniciar sesión** (`FEAT-FBK-008`).

**Pendientes**: si ese comentario consume créditos del autor, si el visitante debe
identificarse de algún modo, y qué protege el enlace frente a abuso. Ver `A-3`.

---

## J-4. Crecimiento por invitación

1. Un usuario invita por email a alguien a la plataforma (`FEAT-USR-017`).
2. La persona invitada se registra usando el enlace (`FEAT-USR-001`) y recibe sus
   +20 créditos de bienvenida.
3. Cuando la persona invitada **deja su primer comentario**, el invitador recibe
   **+5 créditos** (`FEAT-CRD-005`).

El crédito se otorga por participación efectiva, no por registro: es una protección
deliberada contra el registro masivo de cuentas falsas.

---

## J-5. Dos autores se emparejan como writing buddies

1. Un autor publica en el muro buscando writing buddy (`FEAT-COM-004`) o propone
   directamente a un usuario (`FEAT-RDG-008`).
2. El destinatario, si tiene las propuestas habilitadas (`FEAT-USR-010`), la recibe como
   notificación (`FEAT-NOT-005`).
3. Acepta o rechaza (`FEAT-RDG-009`).
4. Se establece el vínculo recíproco.

**Pendiente**: qué habilita el vínculo. Ver `A-1`.

---

## J-6. Vida en la comunidad

1. El usuario ve el muro principal (`FEAT-COM-001`) y filtra por tipo, palabras, usuario
   o fecha (`FEAT-COM-009`).
2. Publica, comenta, reacciona y apoya (`FEAT-COM-002`, `FEAT-COM-006`, `FEAT-COM-007`,
   `FEAT-COM-008`).
3. Se suscribe a autores que le interesan (`FEAT-COM-010`) y recibe avisos de sus
   novedades (`FEAT-NOT-004`).
4. Envía mensajes directos a quien los tenga abiertos (`FEAT-COM-011`).
5. Consulta los rankings (`FEAT-COM-013`, `FEAT-COM-014`, `FEAT-COM-015`).

---

## Recorridos por especificar

Estos recorridos existen implícitamente pero el material de partida no los detalla:

| # | Recorrido | Pendiente de |
|---|---|---|
| J-7 | Eliminación de cuenta y qué ocurre con obras, comentarios y créditos | Decisión de producto (`V-4`) |
| J-8 | Un autor se queda sin créditos y necesita conseguir más | Decisión de producto (`C-1`) |
| J-9 | Moderación de contenido o comentarios abusivos | Decisión de producto (`V-1`) |
| J-10 | Publicación de una obra en varios fragmentos con lecturas parciales | Figma |
