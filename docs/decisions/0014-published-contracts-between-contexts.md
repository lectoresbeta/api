# 0014 — Un contexto expone contratos publicados, y son su única puerta

- **Estado:** Aceptada
- **Fecha:** 2026-09-23
- **Afecta a:** todos los contextos, `deptrac.contexts.yaml`

## Contexto

El correo de activación destapó un caso que la comunicación asíncrona no resuelve.

`Notification` tiene que enviarlo: es suyo el proveedor de correo, las plantillas y la
entregabilidad. Pero el **token** es de `User`, y no puede viajar en el evento: es una
credencial viva, y la cola la persiste, la reintenta y la aparca en un transporte de fallos
que la gente consulta. La propia interfaz `IntegrationEvent` lo prohíbe por escrito.

Las tres salidas tenían precio:

- que `User` mande el correo: el token no sale de su contexto, pero se lleva a `User` el
  proveedor de correo y las plantillas, y contradice `FEAT-NOT-008`;
- que el evento lleve el token: simple, y pone una credencial en RabbitMQ;
- que `Notification` lo pida: exige una dependencia síncrona entre contextos, que es
  justamente lo que `deptrac.contexts.yaml` impide.

`AGENTS.md` ya contemplaba la tercera —«una interfaz de integración explícita cuando la
comunicación síncrona sea genuinamente necesaria»— pero no decía **dónde vive** ni qué
distingue una interfaz de integración de una grieta en el aislamiento.

## Decisión

**Un contexto puede publicar contratos en `src/<Contexto>/<Concepto>/Application/Contract/`,
y eso es lo único suyo que otro contexto puede mirar.**

```text
src/User/Account/Application/Contract/
    ActivationLinkProvider.php   la interfaz
    ActivationLink.php           los datos que devuelve
```

Los publicados hasta ahora:

| Contrato | Contexto | Responde |
|---|---|---|
| `ActivationLinkProvider` | `User` | El enlace de activación, en el momento de enviar el correo |
| `ReaderMaturity` | `User` | Un booleano: ¿tiene edad? |
| `GenreCatalogue` | `User` | Cuáles de estos códigos de temática **no** existen |
| `CorrectionBriefs` | `Work` | Qué se pregunta en un capítulo, de quién es la obra y si admite correcciones |
| `BetaReaderAccessCheck` | `Reading` | Un booleano: ¿es lector beta de esta obra? |
| `WorkAccessBriefs` | `Work` | De quién es una obra, cómo está abierta, qué declara contener y si existe para quien pregunta |
| `RegisteredUsers` | `User` | Un booleano: ¿existe este usuario? |
| `ReaderDirectory` | `User` | Las personas cuyo nombre o `@usuario` encajan con un texto |
| `AuthorAudience` | `User` | Un booleano: ¿acepta este autor comentarios de esta persona? |
| `ReaderContentPreferences` | `User` | Qué etiquetas de contenido ha excluido esta persona |

Casi todos devuelven **un booleano o poco más**, y no es casualidad: un contrato que devuelve
mucho suele ser un modelo compartido con otro nombre.

`ReaderContentPreferences` responde solo hacia un lado, y es lo que lo hace seguro: se puede
preguntar qué ha excluido **una** persona, nunca cuánta gente excluye una etiqueta. Con ese
segundo número un autor sabría cuánta audiencia pierde por etiquetar bien, y con él tendría un
incentivo directo para etiquetar mal. La regla vive en la forma del contrato, no en una
advertencia.

`GenreCatalogue` pregunta al revés de como parecería natural —qué códigos *no* existen, en vez
de cuáles sí— y eso también es deliberado: es la respuesta que hace falta, porque un código
desconocido se rechaza nombrándolo, y evita entregar el catálogo entero para validar tres
valores.

Tres reglas, y las tres importan:

1. **Un contrato pregunta, no ordena.** Devuelve información. Un contrato que dejase a otro
   contexto cambiar algo aquí sería una llamada a método remota con pasos de más, y el
   aislamiento habría durado lo que tarda alguien en necesitarlo.
2. **Un contrato entrega datos, nunca entidades.** Quien recibe un agregado acaba navegando
   por él, y entonces el modelo interno vuelve a estar compartido.
3. **Un contrato no depende de su propio contexto.** Solo de `Shared` y de tipos propios. Si
   necesitase algo de dentro, dejaría de ser una puerta y pasaría a ser una rendija.
4. **Un contrato no llama al contrato de otro contexto mientras responde.** La añadió
   [`0015`](0015-work-and-reading-ask-each-other.md), cuando `Work` y `Reading` pasaron a
   preguntarse mutuamente: es lo único que separa un ciclo de referencias —tolerable— de un
   ciclo de llamadas, que es el que encadena dos contextos en tiempo de ejecución.

La carpeta va bajo `Application/` y no en un cuarto nivel nuevo: la estructura
`<Contexto>/<Concepto>/{Domain,Application,Infrastructure}` se mantiene intacta, y un contrato
publicado es, efectivamente, API de aplicación del contexto.

**`Credits` no publica ninguno**, y esa ausencia es la regla dura de
[`0002`](0002-credits-as-isolated-bounded-context.md): aplicar un efecto de crédito desde
fuera está prohibido, y un contrato de lectura del saldo tampoco hace falta mientras nadie lo
necesite. Si algún día lo necesita, será de lectura.

**Lo asíncrono sigue siendo lo primero.** Un contrato no es una alternativa a los eventos: es
lo que se usa cuando la respuesta hace falta **ahora** y el hecho ya ocurrió. En el correo de
activación conviven los dos: el hecho viaja por la cola y el secreto se pide en el momento de
enviar.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| El token en el evento | Sin código nuevo | Una credencial viva en una cola persistida, reintentada y aparcada en fallos | Es el tipo de decisión que no se nota hasta que se nota |
| Que `User` envíe el correo | El secreto no sale de su contexto | Plantillas, proveedor y entregabilidad duplicados en dos contextos | Contradice `FEAT-NOT-008` y reparte una capacidad que es una |
| El contrato en `Shared` | Sin tocar Deptrac | `Shared` pasaría a contener conceptos de negocio | `AGENTS.md`: «Shared es genérico: no conoce ningún contexto de negocio» |

## Consecuencias

**Positivas**

- El token nunca toca la cola, y vive menos: se emite **al enviar el correo**, así que un
  envío que solo prospera tras horas de reintentos sigue llevando un enlace utilizable.
- La superficie que un contexto expone es una carpeta, no una suposición.
- Deptrac sigue vigilando lo demás: sin contrato, el acceso sigue prohibido.

**Negativas**

- **Es una puerta, y las puertas se usan.** La tentación de resolver con un contrato lo que
  debería ser un evento existirá en cada rodaja, y la regla 1 es lo único que la frena.
- Un contrato es contrato: cambiarlo afecta a otro contexto, aunque compile.
- Los contratos se enlazan a mano en `config/services.yaml`, y es a propósito: abrir una
  puerta debe ser un cambio visible en ese fichero y no el efecto lateral de crear una clase.

**Qué coste tendría revertirla**

Bajo hoy: hay un contrato. Crece con cada uno que se añada, que es otra razón para la regla 1.

## Cumplimiento

`deptrac.contexts.yaml` define una capa `Contracts` con las carpetas `Application/Contract/`
de todos los contextos, y cada capa de contexto se define **excluyéndola**. Todos pueden
depender de `Contracts`; nadie puede depender de otro contexto. `Contracts` solo puede
depender de `Shared`, lo que hace cumplir la regla 3.
