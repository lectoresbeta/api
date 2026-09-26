# 0015 — `Work` y `Reading` se preguntan el uno al otro

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** `Work`, `Reading`, [`0014`](0014-published-contracts-between-contexts.md)

## Contexto

Las cuatro funcionalidades que abren los caminos de acceso `ON_REQUEST` y `PRIVATE`
—[`FEAT-RDG-002`](../features/reading/FEAT-RDG-002-request-beta-reader-access.md) a
[`FEAT-RDG-005`](../features/reading/FEAT-RDG-005-resolve-invitation.md)— necesitan que
`Reading` sepa tres cosas de una obra antes de dejar que nadie la pida o la ofrezca:

- que **existe y no es un borrador ajeno ni una obra bloqueada** —pedir acceso a algo que no
  se puede ni ver debe responder lo mismo que pedirlo a algo que no existe—;
- **de quién es**, porque una solicitud se guarda con su destinatario y solo él la resuelve;
- **qué modalidad tiene**, porque una obra `PRIVATE` no admite solicitudes
  (`bounded-contexts/reading.md` `RN-3`).

`Reading` no sabe nada de eso hoy, y es deliberado:
[`FEAT-RDG-001`](../features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md) `RN-7`
demuestra que para el camino `PUBLIC` no hace ninguna falta. Ahí el resultado es correcto sin
preguntar, y evitarlo ahorró una proyección entera.

Para estos cuatro no hay ese atajo: la pregunta se hace **antes** de crear nada, y la
respuesta decide si se crea.

El problema es la dirección. `Work` ya pregunta a `Reading` si alguien es lector beta de una
obra (`BetaReaderAccessCheck`), y esa consulta nació con `FEAT-RDG-001`. Si ahora `Reading`
pregunta a `Work`, los dos contextos se apuntan mutuamente: **el primer ciclo del sistema**.

## Decisión

**`Work` publica `WorkAccessBrief`, y se acepta el ciclo con una regla que lo mantiene
inofensivo.**

```text
src/Work/Manuscript/Application/Contract/
    WorkAccessBriefs.php     la interfaz: ofWork(workId): ?WorkAccessBrief
    WorkAccessBrief.php      workId, authorId, accessMode, adultsOnly, visibleToOthers
```

Es el hermano por obra de `CorrectionBriefs`, que responde lo mismo por capítulo, y devuelve
**hechos, no un veredicto**: quién es el autor, cómo está abierta la obra, si es para adultos
y si existe para alguien que no sea su autor. Quién puede pedir qué lo decide `Reading` con
esos datos, igual que `Feedback` decide con los suyos.

La regla nueva, que se añade a las tres de [`0014`](0014-published-contracts-between-contexts.md):

> **4. Un contrato no llama al contrato de otro contexto mientras responde.**

Es lo único que separa un ciclo de referencias de un ciclo de llamadas. `WorkAccessBrief` lee
el agregado `Work` y devuelve; **no** consulta `BetaReaderAccessCheck`, aunque
`WorkReadPolicy` sí lo haga para su propia pregunta. Los cinco contratos que ya existen
cumplen la regla sin haberla tenido escrita, y conviene que lo siga siendo por decisión y no
por casualidad.

### Por qué un contrato y no una proyección

La ficha del contexto preveía lo contrario: `Reading` consumiendo `WorkAccessModeChanged` para
mantener «qué caminos de acceso admite la obra». **Esta decisión sustituye ese plan**, y hay
tres razones, en orden de peso.

**La primera es de autorización, y es la que decide.** Rechazar una solicitud sobre el
borrador de otra persona o sobre una obra bloqueada no es un filtro: es la misma regla que
hace que `GET /works/{id}` responda `404`. Reconstruirla dentro de `Reading` a partir de una
proyección sería **duplicar lógica de autorización en dos contextos**, que es lo que
`AGENTS.md` prohíbe con más insistencia —«no disperses lógica de autorización duplicada»— y
lo que garantiza que un día las dos copias no digan lo mismo. Con el contrato, el juicio sobre
qué es visible se queda donde vive la obra.

**La segunda es de frescura.** Solicitar es una acción síncrona con respuesta inmediata. No es
grave que la proyección vaya retrasada —lo peor que pasa es que se acepte una solicitud que el
autor rechazará, y una solicitud no concede nada— pero tampoco hay motivo para aceptar el
retraso cuando la alternativa no cuesta más.

**La tercera es de tamaño.** La proyección necesitaría `WorkCreated`, `WorkPublished`,
`WorkAccessModeChanged` y `WorkDeleted`, una tabla y su reconstrucción, para un dato que se
consulta una vez por acción del usuario. No es el caso de la insignia del catálogo
([`0008`](0008-catalogue-ordering.md)), que se pinta decenas de veces por pantalla y por eso
sí se proyecta.

### Por qué el ciclo es tolerable

No lo es por definición, y conviene decir qué lo hace soportable aquí:

- **las dos direcciones preguntan**, ninguna ordena (regla 1 de `0014`);
- **cada una responde sobre estado que posee**: `Work` sobre la obra, `Reading` sobre el
  acceso. Ninguna necesita la otra para saber lo suyo;
- **ninguna llama a la otra mientras responde** (la regla 4 de arriba), así que no hay cadena
  que seguir ni orden de arranque que respetar.

El ciclo existe porque el control de acceso es, de verdad, una responsabilidad conjunta: «de
quién es esto y cómo está abierto» es de `Work`, y «a quién se ha dejado entrar» es de
`Reading`. Partirlo de otra forma significaría mover un agregado de contexto, y ninguno de los
dos está en el sitio equivocado.

**Deptrac no lo ve.** `deptrac.contexts.yaml` mete todos los contratos en una capa `Contracts`
que cualquiera puede usar, así que un ciclo entre contratos pasa sin avisar. Esa ceguera es la
razón de que esto sea una ADR y no un comentario: la única barrera contra un segundo ciclo
—este sí, innecesario— es que quien lo proponga tenga que argumentar contra este documento.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Proyección en `Reading` alimentada por eventos | Sin ciclo; era el plan escrito | Duplica la regla de visibilidad en dos contextos; cuatro eventos y una tabla | La duplicación de autorización es peor que el ciclo |
| Mover `AccessRequest` e `AccessInvitation` a `Work` | Sin ciclo y sin contrato | `Reading` se queda sin la mitad de su razón de ser, y `Work` gana el ciclo de vida del acceso | Rompe el reparto de contextos por conveniencia de una dependencia |
| Que el cliente mande `authorId` y `accessMode` | Trivial | El cliente diría quién es el autor de la obra | Un dato de autorización no se pide a quien quiere pasar |
| Ampliar `CorrectionBriefs` para responder por obra | Un contrato menos | Mezcla «qué se pregunta en un capítulo» con «cómo se entra en una obra» | Dos preguntas distintas con dos ciclos de vida distintos |

## Consecuencias

**Positivas**

- La regla de visibilidad de una obra vive en un sitio, y `Reading` la hereda entera.
- `Reading` sigue sin tener ni una tabla que hable de obras.
- La regla 4 queda escrita antes de que haga falta, que es cuando sirve de algo.

**Negativas**

- **Hay un ciclo, y los ciclos invitan a más.** Extraer `Reading` o `Work` por separado
  obligaría a llevarse el contrato del otro.
- Deptrac no lo vigila. La única defensa es esta ADR y la revisión.
- Un tercer contrato entre los mismos dos contextos sería la señal de que el reparto está mal;
  conviene tratarlo como tal y no como «uno más».

**Qué coste tendría revertirla**

Medio. Volver a la proyección significa cuatro consumidores, una tabla y reescribir la regla
de visibilidad dentro de `Reading` — que es justamente lo que esta decisión evita.
