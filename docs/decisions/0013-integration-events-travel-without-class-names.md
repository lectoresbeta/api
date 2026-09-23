# 0013 — Los eventos de integración viajan sin nombre de clase

- **Estado:** Aceptada
- **Fecha:** 2026-09-23
- **Afecta a:** todos los contextos, `Shared/Infrastructure/Messenger`, RabbitMQ

## Contexto

`AGENTS.md` prohíbe que un bounded context dependa de las clases internas de otro, y Deptrac
lo comprueba. La comunicación es por eventos de integración sobre Symfony Messenger y RabbitMQ.

El problema aparece al elegir el serializador del transporte, y no es evidente hasta que se
mira el formato de cable:

- el serializador **nativo de PHP** escribe el objeto serializado, con su clase dentro;
- el serializador **de Symfony** escribe el nombre de la clase en la cabecera `type`.

Los dos exigen que el consumidor **tenga esa misma clase**. Es decir: `Credits`, para poder
reaccionar a `AccountActivated`, tendría que importar la clase de `User`. Deptrac lo
rechazaría, y con razón.

La salida habitual —mover los eventos a `Shared`— es peor de lo que parece: convierte cada
evento en un modelo compartido entre contextos, que es exactamente lo que
[`0002`](0002-credits-as-isolated-bounded-context.md) evita. Un consumidor quedaría atado a
la forma que el publicador le dé a su clase, y cambiar un campo privado en `User` rompería a
`Credits`.

## Decisión

**El formato de cable no lleva ningún nombre de clase.** Lleva el nombre del hecho, su
identificador, su momento y un payload plano de escalares.

```text
headers: X-Event-Name: AccountActivated
         X-Event-Id:   0199c7f2-...
         X-Occurred-At: 2026-09-23T10:00:00+00:00
body:    {"userId":"0199...","activatedAt":"2026-09-23T10:00:00+00:00"}
```

**Cada contexto declara su propia clase para el mismo hecho.** El publicador implementa
`IntegrationEvent`; el consumidor implementa `IncomingIntegrationEvent`, que dice a qué nombre
se suscribe y cómo reconstruirse desde el payload.

Lo que comparten los dos lados es **el nombre del hecho y la forma de su payload**, que es lo
que el [catálogo de eventos](../events/README.md) documenta. Nada más.

Las suscripciones se declaran a mano en `config/services.yaml`. Suscribirse a un hecho ajeno
es una decisión de integración, y una decisión que vive en un fichero se puede revisar; con
descubrimiento automático, una suscripción nueva aparecería porque alguien añadió una clase.

### El payload es plano

Escalares, o **listas de escalares**. Nunca un objeto anidado: el serializador lo rechaza en
lugar de aceptarlo.

Es una restricción incómoda a propósito: un objeto anidado es donde acaba colándose un
agregado entero, y un payload plano se lee en un navegador de colas y se compara en una
revisión.

> **Matiz (2026-09-23):** la regla original decía «solo escalares». Se amplía a listas de
> escalares al aparecer el primer caso legítimo —los géneros que alguien elige
> ([`FEAT-USR-023`](../features/user/FEAT-USR-023-onboarding-select-genres.md))—. Una lista de
> códigos no esconde nada y se lee igual de bien; la alternativa era unirlos en una cadena, que
> solo sirve para inventar un separador que algún día estará dentro de un valor. Lo que sigue
> prohibido, que es lo que la regla protegía, es el objeto anidado.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Serializador de Symfony con el nombre de clase | Es el de serie, cero código | El consumidor necesita la clase del publicador | Rompe el aislamiento, que es una regla dura |
| Eventos compartidos en `Shared` | Funciona con el serializador de serie, y comprueba tipos | `Shared` pasa a contener modelo de negocio compartido | Contradice `0002` y `AGENTS.md`: el acoplamiento no desaparece, se muda |
| Mapa de clase a clase en el serializador | Sigue comprobando tipos en ambos lados | Un fichero central que conoce las clases internas de todos | Traslada el acoplamiento al wiring en vez de eliminarlo |

## Consecuencias

**Positivas**

- Ningún contexto conoce las clases de otro, y Deptrac lo sigue comprobando.
- Renombrar la clase de un evento es un refactor, no un cambio incompatible.
- El consumidor declara **solo los campos que usa**. Que el publicador añada uno no le afecta.
- El mensaje es legible en RabbitMQ sin deserializar nada.

**Negativas**

- **Nada comprueba en tiempo de compilación que los dos lados se entienden.** Un consumidor
  que lea un campo que el publicador dejó de enviar lo descubre en ejecución. El catálogo de
  eventos es el contrato, y mantenerlo deja de ser opcional.
- Un hecho nuevo exige escribir dos clases, una por lado.
- El payload plano obliga a aplanar lo que naturalmente no lo es.

La primera es el coste real de la decisión y conviene no disimularlo: se cambia una garantía
del compilador por independencia entre contextos. Se acepta porque la garantía era falsa —solo
existía mientras los dos contextos viviesen en el mismo despliegue— y la independencia es la
que permite que el modelo de créditos cambie sin tocar a nadie.

**Qué coste tendría revertirla**

Bajo mientras no haya mensajes en vuelo con este formato.

## Cumplimiento

`tests/Unit/Shared/Messenger/IntegrationEventSerializerTest.php` comprueba que el formato de
cable no contiene el nombre de la clase publicada y que un consumidor lo reconstruye en **otra**
clase distinta. `deptrac.contexts.yaml` comprueba lo demás.
