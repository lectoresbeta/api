# Inventario de las fichas en `PARTIAL`

**Fecha:** 2026-09-26 · **Alcance:** las 42 fichas que el registro marca `PARTIAL`.
**Método:** cada hueco que la ficha declara, contrastado contra el código. No se ha tocado nada.

Esto no es una especificación: es una foto para decidir qué merece trabajo. Cuando una entrada
se cierre, el sitio donde se escribe es la ficha, no aquí.

## El titular

De los 42, **la mayoría de los huecos declarados ya no existen**. Las fichas se escribieron
cuando faltaba media plataforma y la nota de «falta X» se quedó puesta después de que X
llegara por otra ficha.

| | Cuántas |
|---|---|
| Huecos **reales** y accionables hoy | 14 |
| Notas **caducadas**: el hueco ya está cerrado | 17 |
| Bloqueadas por una pregunta **abierta propia** | 8 |
| `PARTIAL` que es en realidad **`DONE`** | 3 |

**La consecuencia práctica:** `PARTIAL` en este registro no significa «a medias», significa
«alguien anotó algo alguna vez». Como etiqueta de estado ha dejado de informar, y merece una
pasada de saneamiento aparte del trabajo de producto.

---

## 1. Huecos reales

### 1a. ~~`Notification` no escucha cuatro hechos~~ — **hecho** (2026-09-26)

> Los cuatro avisos están entregados, en una sola tanda como decía el inventario. Y al
> escribirlos apareció **un defecto que este inventario no vio**: `Credits` no publicaba
> `ChapterPriceChanged` al editar un capítulo —`updateContent()` repreciaba la fila antes de
> que `reprice()` mirase, así que comparaba el precio nuevo consigo mismo—, de modo que la
> insignia del catálogo se quedaba con la cifra vieja. Arreglado, y el hecho lleva ahora el
> precio anterior, que es lo que permite avisar solo cuando **sube**.
>
> Queda lo que dice el último párrafo de esta sección: el aviso del mensaje del moderador,
> cuyo hecho todavía no se publica.

### 1a. `Notification` no escucha cuatro hechos — **una sola tarea, no cuatro**

Es el hallazgo con más peso del inventario. Cuatro fichas distintas dicen «falta el aviso» y
las cuatro son el mismo trabajo: `Notification` ya consume 27 hechos, y a estos cuatro no
llega. El hecho se publica; nadie lo escucha.

| Hecho | Ficha que lo echa en falta | Quién se queda sin enterarse |
|---|---|---|
| `PostCommented` | FEAT-COM-006 | Quien publicó, de que le han comentado |
| `UserMentioned` | FEAT-COM-032 | Quien ha sido mencionado |
| `SanctionImposed` | FEAT-MOD-006 `RN-4` | **El sancionado, de que lo está** |
| `ChapterPriceChanged` | FEAT-CRD-016 `RN-9` | El autor, de que ampliar el capítulo lo ha encarecido |

El de la sanción es el que más duele: hoy a alguien se le restringe la cuenta y lo descubre
chocándose. La ficha lo dice con precisión — «es la pieza que hace que una sanción corrija en
vez de solo castigar».

Los cuatro se resuelven igual y en una tanda: modelar el hecho entrante en `Notification`,
registrarlo a mano en `IncomingEventRegistry`, y una plantilla por aviso.

Falta además el **aviso al destinatario de un mensaje del moderador** (FEAT-MOD-009 `RN-7`),
que es el mismo patrón sobre un hecho que todavía no se publica.

### 1b. ~~La tabla de deduplicación crece sin límite~~ — **hecho** (2026-09-26)

FEAT-CRD-011 `RN-7`. `purgeOlderThan` existía en el repositorio y **nadie lo llamaba**: no
había comando ni programación. Cada evento que `Credits` procesaba dejaba una fila para
siempre.

Ya hay comando, `lectoresbeta:credits:purge-processed-events`, con la purga de alias de
FEAT-USR-036 de molde. Un matiz que este inventario no vio: `purgeOlderThan` borraba **sin
tope**, y esa es la tabla que cada consumidor lee antes de mover un crédito. Ahora va acotada
y el servicio repite hasta agotar.

### 1c. Lo que las decisiones de ayer acaban de desbloquear

| Ficha | Hueco | Lo desbloquea |
|---|---|---|
| FEAT-WRK-001 `RN-7` | El registro de autoría al crear la obra | `W-1`, decidida |
| FEAT-MOD-005 `RN-7` | Eliminar una cuenta desde el backoffice | `V-4`, decidida |
| ~~FEAT-WRK-015~~ | ~~Orden «más valorados»~~ — **hecho** (2026-09-26). «Más leídos» sigue esperando a `H-3` | `W-12`, que se respondió sola |
| ~~FEAT-COM-006~~ | ~~Orden «más relevantes», que es **el que la pantalla enseña por defecto**~~ — **hecho** (2026-09-26) | Los apoyos de `FEAT-COM-030` |

Los dos últimos eran el mismo caso y conviene verlo: el desplegable ofrecía un orden por
defecto que el servidor respondía `422`. Era la única categoría de hueco que el usuario final
veía, y ya no está.

Dos cosas que este inventario no había visto y aparecieron al hacerlo:

- **`W-12` no necesitaba respuesta.** Preguntaba si «más valorados» mide `WorkRating` o los
  «me gusta», y en esta plataforma **no hay «me gusta» sobre una obra**: los apoyos son de
  publicaciones y comentarios. El corazón de la maqueta no tiene nada detrás. Es el mismo
  patrón que `V-1`, resuelta por construcción;
- **ninguno de los dos era solo una consulta.** «Más relevantes» necesitaba un cursor que
  llevara la puntuación dentro —es el primer listado de la API que ordena por algo calculado—
  y «más valorados» necesitaba que `Work` mantuviera un agregado que nadie mantenía, porque
  `WorkRated` no lo consumía nadie.

### 1d. Pequeños y sueltos

| Ficha | Hueco | Tamaño |
|---|---|---|
| FEAT-CRD-002 `RN-7` | `GrantWelcomeCredits` no publica `CreditsAdded`. La clase existe, nadie la emite | Pequeño |
| FEAT-COM-016 `RN-7` | Las sugerencias de autor no filtran cuentas sin activar | Pequeño |
| FEAT-WRK-016 | `Credits` no consume la apertura a corrección para recalcular corregibilidad | Medio |
| FEAT-WRK-008 `RN-6` | Las palabras de un capítulo oculto siguen contando en el total de la obra | Pequeño |
| FEAT-WRK-012 `RN-8` | El catálogo no esconde las obras de quien te ha bloqueado | Medio |
| FEAT-MOD-005 `MOD-47` | Desestimar un correo que no aporta nada reclamable no deja traza | Pequeño |
| FEAT-MOD-006 `MOD-25` | No hay cola de asuntos vivos: una suspensión indefinida se vuelve expulsión sin que nadie lo decida | Medio |
| ~~FEAT-MOD-006 `RN-9`~~ | ~~`Credits` no congela la deuda durante una suspensión parcial~~ — **hecho** (2026-09-26) | **Ver abajo** |
| FEAT-CRD-006 | `CreditDebtCleared` — el evento existe y `Notification` lo escucha, pero **nada lo publica** | Pequeño |

**`MOD-006 RN-9` merece un párrafo**, porque no es un hueco cosmético sino una trampa: quien
tiene saldo negativo y recibe una suspensión parcial queda atrapado. Corregir es la única forma
de saldar la deuda, y la sanción se lo impide.

**Hecho** (2026-09-26), y aquí este inventario se equivocó al medirlo: lo llamé «pequeño» y no
lo es. Al abrirlo aparecieron tres cosas que no se ven desde fuera:

- **congelar no es un booleano.** La suspensión parcial tiene duración y al expirar **no se
  publica ningún hecho**, así que la congelación tiene que ser una fecha. Eso, a cambio, es lo
  que evita el proceso programado que yo temía: si es una fecha, se apaga sola;
- **se congela la retención, no la corregibilidad.** Si durante la sanción los capítulos
  volvieran a admitir correcciones, cada una cobrada haría **crecer** la deuda — y la regla
  dice «ni crece» en la misma frase en que dice «ni bloquea nada». Con eso claro, no hay
  ningún recálculo que programar;
- **hay que decírselo a `Feedback`**, y con dos hechos nuevos: `CreditDebtCleared` no valía,
  porque su carga lleva el saldo —que sigue en rojo— y su nombre mentiría. Son
  `CreditDebtFrozen` y `CreditDebtThawed`.

Y la implementación destapó un vaivén que no estaba en el guion: la cola reentrega, y una
reentrega de `SanctionImposed` volvía a congelar lo que `SanctionLifted` acababa de
descongelar, sin parar. Se arregla guardando **dos fechas** en lugar de borrar una.

---

## 2. Notas caducadas

El hueco que la ficha declara **ya está cerrado**. Verificado contra el código, una a una.

| Ficha | Lo que la nota dice que falta | Realidad |
|---|---|---|
| FEAT-USR-001 | El token de invitación «se acepta y se ignora» | `RegisterUserHandler` inyecta `ConsumeInvitation` y lo consume (FEAT-USR-018) |
| FEAT-USR-001 | El paso siguiente exige login, «`FEAT-USR-004`, `PENDING`» | El login existe |
| FEAT-USR-020 | El reenvío del correo de activación | FEAT-USR-021, implementada |
| FEAT-NOT-008 | Lo mismo, más `ActivationEmailRequested` | El hecho existe y se consume |
| FEAT-USR-025 | «No hay ningún endpoint de escritura no exento contra el que probar» | Hay decenas. El test de extremo a extremo ya se puede escribir, y sigue sin existir |
| FEAT-USR-039 | «El canal de correo no lo aplica nadie» | `Notify` inyecta `NotificationChoices` y las aplica |
| FEAT-USR-043 | Faltan muro, recomendaciones y perfil de autor | Recomendaciones **sí** (FEAT-COM-017). Quedan las otras dos, y en el muro probablemente no aplica: una publicación no lleva etiquetas de contenido |
| FEAT-WRK-004 | Las preferencias de contenido sensible del lector | Existen y se aplican en catálogo y recomendaciones; falta al abrir la obra |
| FEAT-WRK-007 | «No hay ajuste global de privacidad con el que contrastar el techo» | FEAT-USR-038 existe; RDG-003 y RDG-005 ya no están en `REVIEW` |
| FEAT-WRK-012 | Las preferencias de contenido sensible | Aplicadas: `ListCatalogueHandler` las usa |
| FEAT-FBK-003 | «`Notification`: el evento sale y nadie avisa» | `FeedbackSubmitted` se consume en `Notification` |
| FEAT-FBK-003 | Guardar el borrador a medias | FEAT-FBK-011, implementada |
| FEAT-CRD-009 | `CorrectionDraftDiscarded` «no lo publica nadie» | `DiscardCorrectionDraftHandler` lo publica |
| FEAT-CRD-009 | El descubierto deliberado de FEAT-CRD-019 | Implementado, con cupo 3 |
| FEAT-CRD-006 | La corrección bloqueada de FEAT-CRD-018 | Implementada |
| FEAT-CRD-006 | «Los avisos: los eventos salen, pero nadie los escucha» | `CreditBalanceWentNegative` y `CreditDebtCleared` se consumen |
| FEAT-CRD-016 | «`CorrectionPrice` existe como tabla y nadie la escribe» | Hay repositorio Doctrine que la escribe |

Ninguna de estas necesita código. Necesitan que alguien tache la línea, y ese alguien no las va
a mirar mientras el registro diga `PARTIAL` sin decir de qué.

---

## 3. Bloqueadas por una pregunta abierta propia

No son deuda: son fichas esperando una decisión que nadie ha tomado. Las listo para que se vea
qué queda realmente abierto después de cerrar las ocho de ayer.

| Ficha | Pregunta | Qué hay que decidir |
|---|---|---|
| FEAT-WRK-012 | `L-1` | Qué rangos tiene el filtro de tiempo de lectura |
| FEAT-WRK-015 | `H-3` | Qué cuenta como «leída» una obra. Es la misma que dejó el carrusel sin contador de visitas |
| FEAT-WRK-001 | `C-6`, `Q-6` | Límite superior de extensión y metadatos obligatorios |
| FEAT-WRK-004 | `L-8` | Si leer exige sesión. Abrirlo después es compatible; cerrarlo no |
| FEAT-WRK-017 | `OB-7` | Sin edad verificable, `ADULTS_ONLY` solo retira la obra de quien no declaró fecha |
| FEAT-FBK-003 | `R-10` | Si hay tope de correcciones por obra |
| FEAT-CRD-006 | `C-4`, `C-9` | Corregir dos veces el mismo capítulo; devolver créditos al ocultar una corrección abusiva |
| FEAT-USR-033 | — | `GET /usernames/{username}/availability`. La propia ficha dice que hoy no hace falta: el nombre no se elige al registrarse |

`H-3` aparece por tercera vez en el proyecto. Cuando se decida, cierra el orden «más leídos» de
«Mis relatos», el contador de visitas del carrusel y la señal de lectura de `CM-4`.

---

## 4. Tres `PARTIAL` que son `DONE`, y un defecto de las herramientas

**FEAT-MOD-001, FEAT-MOD-004 y FEAT-MOD-010** están implementadas y probadas, y su registro
dice `PARTIAL`. Peor: el cuerpo de las tres fichas dice `**Implementación:** TODO`, que
contradice su propio *front matter* (`impl_status: PARTIAL`) y contradice la realidad.

~~**FEAT-CRD-018** tiene el mismo defecto al revés: `impl_status: DONE` en la cabecera y
`**Implementación:** TODO` tres párrafos más abajo.~~ — **arreglada** (2026-09-26) al cerrar
`RN-8b`. Quedan las tres de `Moderation`.

`check-docs.py` no lo detecta porque **solo lee el *front matter***, no la línea en prosa. Son
dos fuentes de verdad para el mismo dato, y la que la gente lee al abrir la ficha es la que
está mal.

Propuesta, barata: añadir a `check-docs.py` una comprobación de que la línea
`**Implementación:**` concuerda con `impl_status`. Sin eso, este inventario vuelve a
desactualizarse solo.

---

## Lo que yo haría, y en qué orden

1. **Los cuatro avisos que faltan** (§1a). Una tanda, y uno de ellos —la sanción— es una
   persona enterándose de que la han castigado por chocarse con un muro.
2. ~~**La congelación de deuda en suspensión parcial** (§1d). Es una trampa sin salida, y es
   pequeña.~~ — **hecho** (2026-09-26). Pequeña no era; el párrafo del §1d cuenta en qué me
   equivoqué.
3. ~~**Los dos órdenes por defecto que responden `422`** (§1c). Lo único de esta lista que un
   usuario ve hoy.~~ — **hecho** (2026-09-26).
4. ~~**La purga de la tabla de deduplicación** (§1b). Media tarde, y deja de crecer.~~ —
   **hecho** (2026-09-26). Con un matiz que no estaba en el guion: `purgeOlderThan` borraba
   sin tope, y esa es la tabla que cada consumidor lee antes de mover un crédito. Va acotada.
5. **Tachar las 17 notas caducadas y arreglar las cuatro fichas del §4**, con la comprobación
   nueva en `check-docs.py` para que no se repita.

Lo de §3 no lo tocaría: son preguntas tuyas, no trabajo mío.
