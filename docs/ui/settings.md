---
screen: Configuración del usuario
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-USR-008, FEAT-USR-013, FEAT-USR-037, FEAT-USR-038, FEAT-USR-039, FEAT-USR-040, FEAT-USR-041, FEAT-USR-042]
actors: [User]
updated: 2026-09-22
---

# Configuración del usuario

Pantalla única con **cinco pestañas**: Perfil, Cuenta, Notificaciones, Privacidad y
Apariencia. Concentra todo lo que el usuario puede cambiar sobre sí mismo.

Es la pantalla con más reglas de autorización y de seguridad de las documentadas hasta ahora:
aquí se cambia la contraseña, el correo y quién puede ver qué.

## Estructura

```text
Configuración
[ Perfil ] [ Cuenta ] [ Notificaciones ] [ Privacidad ] [ Apariencia ]
…contenido de la pestaña…
                                            [ Cancelar ]  [ Guardar ]
```

Cada pestaña tiene **su propio par Cancelar / Guardar**. Ver `S-1`.

No hay entrada «Configuración» en el menú lateral: se llega desde el **avatar** de la cabecera
(`S-2`).

---

## Pestaña «Perfil»

| Campo | Control | Notas |
|---|---|---|
| **Nombre** | Texto | Es el nombre **público** (`OB-2`) |
| **Nombre de usuario** | Texto | **No está en la maqueta, pero sí en la pestaña** (`S-3`, resuelta) |
| **Biografía** | Área de texto | Contador `0 / 100` en la maqueta, **ampliado a 300** (`S-4`) |
| **Preferencias literarias** | Selección de géneros | **No está en la maqueta, pero sí en la pestaña** (`S-18`, resuelta) |
| **Foto de perfil** | Avatar actual + zona de arrastre | Ver abajo |

Las capturas muestran solo tres campos; producto confirma que la pestaña incluye también el
**nombre de usuario** y las **preferencias literarias**. Eso cierra `S-3` y `S-18`, y da
interfaz a `FEAT-USR-034` y `FEAT-USR-009`, que hasta ahora no la tenían.

### El nombre de usuario no es un campo de texto más

Cambiarlo arrastra todo lo de
[`FEAT-USR-034`](../features/user/FEAT-USR-034-change-username.md): solo se puede **una vez
cada 30 días**, el anterior queda reservado como alias durante ese mes y las URL antiguas
siguen resolviendo.

Eso choca con el **«Guardar» único de la pestaña**. Guardar una biografía es inocuo; guardar
un nombre de usuario **deja al usuario bloqueado 30 días**, y no hay nada en la pantalla que
lo advierta.

Dos consecuencias para el backend:

- el cambio de nombre de usuario **mantiene su propio endpoint** y sus propias validaciones,
  aunque la pantalla lo presente junto a los demás campos;
- si el nombre de usuario pedido no está libre, **no debe caerse el resto del guardado**: el
  usuario espera que su biografía se haya guardado igualmente (`S-37`).

Conviene además que la interfaz avise antes de confirmar, y que el backend diga **cuándo
podrá volver a cambiarlo** (`FEAT-USR-034` ya lo contempla).

### La biografía se amplía a 300 caracteres

Cien caracteres son unas quince palabras: para una plataforma de escritores no llega ni para
decir qué escribe uno y qué busca. **Se amplía a 300** (`S-4`, resuelta).

Trescientos caracteres son unas cincuenta palabras: tres o cuatro líneas bajo la foto. Da
para una presentación real sin convertir la cabecera del perfil en un texto largo que haya
que truncar siempre.

**Se cuenta en caracteres, no en palabras**, y aquí la excepción está justificada: el resto
del producto mide en palabras porque mide **esfuerzo** —lo que cuesta escribir una
corrección—. Este límite no mide esfuerzo, mide **espacio**: es el texto que tiene que caber
bajo una foto de perfil. Para eso, el carácter es la unidad correcta.

### Y es el mismo texto que la descripción del perfil

Producto lo confirma: la biografía es **el texto que aparece bajo la foto en «Mi perfil»**,
es decir la «Descripción» que [`my-profile.md`](my-profile.md) documenta como editable en
línea.

Es un solo campo con **dos puntos de edición**:

| Dónde | Cómo |
|---|---|
| Mi perfil | Edición en línea, un campo |
| Configuración › Perfil | Junto al resto del formulario |

Los dos deben escribir en el mismo sitio y compartir el mismo límite. Si la edición en línea
admitiera más texto que el formulario, el usuario podría escribir algo que después no puede
guardar desde Configuración.

Queda un resto de `S-5`: `FEAT-USR-015` describe una «página de autor» con «bio, foto y
referencias». Si esa bio es esta, ya está resuelto; si la página de autor tiene un texto
largo aparte, son dos campos y hay que nombrarlos distinto.

### La foto de perfil no coincide con lo documentado

Aquí la foto se sube con una **zona de arrastre genérica**:

> Click or drag file to this area to upload
> Support for a single or bulk upload. Strictly prohibit from uploading company data or other
> band files

Tres cosas saltan a la vista:

1. **Está en inglés**, en una interfaz íntegramente en español.
2. Habla de **subida masiva** («bulk upload»), que no tiene sentido para un avatar.
3. «other band files» es una errata de «banned files». Es el texto por defecto del componente
   de subida de Ant Design, sin sustituir.

Es texto de maqueta, no una decisión de producto (`A-1`).

Lo relevante para el backend es otra cosa: [`profile-photo.md`](profile-photo.md) documenta
un **modal con zoom, giro y desplazamiento** donde el recorte lo hace el cliente
([`FEAT-USR-037`](../features/user/FEAT-USR-037-upload-profile-photo.md)). Aquí no hay
modal ni recorte.

| | `profile-photo.md` | Esta pantalla |
|---|---|---|
| Entrada | Modal de subida | Zona de arrastre |
| Ajuste | Zoom, giro y desplazamiento | Ninguno |
| Quién recorta | El cliente | Nadie |
| Eliminar | Opción explícita | No aparece |

**Resuelto (`S-6`): es un error de maqueta.** Aquí aplica lo mismo que en
[`profile-photo.md`](profile-photo.md) — modal con zoom, giro y desplazamiento, y **el
recorte lo hace el cliente**. `RN-4b` de `FEAT-USR-037` sigue en pie, y la zona de arrastre
de esta pantalla es simplemente otra forma de entrar al mismo flujo.

---

## Pestaña «Cuenta»

| Campo | Control |
|---|---|
| **Email** | Texto |
| **Contraseña actual** | Contraseña |
| **Nueva contraseña** | Contraseña |
| **Eliminar cuenta** | Enlace “Eliminar cuenta”, con advertencia |

### Cambiar el email no es editar un campo

El correo es **la identidad de la cuenta**: sirve para iniciar sesión, para recuperar la
contraseña y fue el origen del nombre de usuario por defecto
([`FEAT-USR-033`](../features/user/FEAT-USR-033-username-assignment.md)).

Cambiarlo arrastra decisiones que la pantalla no muestra:

- **El correo nuevo hay que verificarlo.** Si no, cualquiera con la sesión abierta podría
  apuntar la cuenta a un buzón ajeno y quedarse con ella vía «he olvidado mi contraseña».
- **Mientras no se verifique, el correo válido sigue siendo el anterior.**
- **Conviene avisar al correo antiguo** de que se ha pedido el cambio. Es la única defensa de
  quien ha perdido el control de su sesión.
- **El cambio no debe re-derivar el nombre de usuario.** Ya está asignado y es estable.
- ¿Exige la contraseña actual? La pantalla tiene ese campo, pero parece destinado al cambio
  de contraseña (`S-7`).

Ver [`FEAT-USR-040`](../features/user/FEAT-USR-040-change-email.md).

### El formulario de contraseña asume que hay contraseña

Dos campos: actual y nueva. Faltan dos cosas:

- **Confirmación de la nueva contraseña.** Sin ella, una errata deja al usuario fuera.
- **Los requisitos de la contraseña**, que `FEAT-USR-001` sí define.

Y hay un caso que la pantalla no distingue: **quien se registró con Google no tiene
contraseña** (`FEAT-USR-002`).

**Resuelto (`S-8`): deja «Contraseña actual» en blanco.** El mismo formulario sirve para
cambiarla y para establecerla por primera vez; el backend acepta el campo vacío **solo** si
la cuenta no tiene contraseña.

Conviene saber lo que eso implica, porque es un cambio real en el nivel de protección: en una
cuenta de Google, **cualquiera que tenga la sesión abierta puede ponerle contraseña sin
demostrar nada**, y a partir de ahí entrar sin Google. Las dos defensas que quedan son el
**aviso por correo** y el **cierre de las demás sesiones**, y por eso ninguna de las dos es
opcional ([`FEAT-USR-041`](../features/user/FEAT-USR-041-change-password.md) `RN-3`, `RN-4`).

### «Eliminar cuenta» promete algo que no se puede cumplir

> Si eliminas esta cuenta eliminarás todos los datos asociados a ella. Esta acción es
> irreversible.

**Esa frase no es cierta**, y conviene arreglarla antes de que sea una promesa legal:

| Dato | Qué dice la documentación |
|---|---|
| Nombre de usuario | **Queda bloqueado 30 días** como alias, para evitar suplantaciones ([`decision:0005`](../decisions/0005-username-with-temporary-aliases.md)) |
| Correcciones entregadas a otros autores | El autor **ya las pagó**. Borrarlas sería quitarle algo que compró |
| Créditos gastados por terceros | Los movimientos son **inmutables y auditables** (`RN-2` de `Credits`) |
| Comentarios en obras ajenas | Borrarlos deja conversaciones incoherentes |
| Obras propias | ¿Se borran? ¿Y el feedback que otros dedicaron a ellas? |

**Decidido (`S-32`): eliminar una cuenta la anonimiza.** Se borra todo lo que identifica a
la persona —nombre, nombre de usuario, correo, foto, biografía, fecha de nacimiento,
credenciales— y se conserva lo que pertenece a otros —la corrección que un autor pagó, el
movimiento de créditos, el comentario en una conversación ajena—, atribuido a un usuario
eliminado.

Cumple el derecho de supresión sin destruir lo ajeno.

**El texto de la advertencia tiene que cambiar.** Prometer que se borrarán «todos los datos
asociados» describe algo que no va a ocurrir, y esa clase de promesa es la que después no se
puede justificar. Ver [`FEAT-USR-013`](../features/user/FEAT-USR-013-delete-account.md).

`U-3` y `V-4` siguen abiertas: la anonimización dice qué pasa con **la persona**, no qué pasa
con **sus obras** ni con los mensajes directos que forman parte de la conversación de otro.

---

## Pestaña «Notificaciones»

Dos bloques y un interruptor general.

### Notificaciones por correo

*«Recibe correos sobre tu actividad cuando no estás conectado.»*

| Preferencia | Estado en la maqueta |
|---|---|
| Actualizaciones de la plataforma | **Desactivada** |
| Consejos de uso | Activada |
| Comentarios en mis textos | Activada |
| Seguidores nuevos | Activada |

### Notificaciones en la plataforma

*«Recibe notificaciones mientras estás usando LectoresBeta.»*

| Preferencia | Estado en la maqueta |
|---|---|
| Comentarios en mis textos | Activada |
| Mensajes nuevos | Activada |
| Seguidores nuevos | Activada |

### Los dos canales no ofrecen lo mismo

| Preferencia | Correo | En la plataforma |
|---|---|---|
| Actualizaciones de la plataforma | Sí | **No** |
| Consejos de uso | Sí | **No** |
| Comentarios en mis textos | Sí | Sí |
| Seguidores nuevos | Sí | Sí |
| Mensajes nuevos | **No** | Sí |

La asimetría tiene lógica —nadie quiere un correo por cada mensaje directo, y los consejos de
uso no son un aviso— pero conviene confirmarla en vez de deducirla (`S-9`). El modelo debe
admitir que **una preferencia exista en un canal y no en el otro**, no una matriz completa.

### Hay correos que no se pueden desactivar

**«Desactivar todas las notificaciones»** dice: *«No recibirás ningún tipo de notificación, ni
en el correo ni en la plataforma.»*

Eso **no puede aplicarse literalmente**. Siguen teniendo que salir:

- el correo de **activación** de la cuenta;
- el de **restablecer contraseña**;
- los avisos de **seguridad**, como el cambio de correo o de contraseña;
- las comunicaciones **legales** obligatorias.

**Confirmado por producto:** el interruptor **no afecta a las notificaciones operativas**.

Son correos **transaccionales**, no notificaciones: responden a algo que el usuario acaba de
pedir o que afecta a la seguridad de su cuenta. La distinción tiene que existir en el modelo,
o el interruptor dejará a alguien sin poder recuperar su cuenta.

Conviene que el texto de la pantalla lo diga. Tal como está —*«No recibirás ningún tipo de
notificación»*— describe algo que no va a pasar, y quien lo active seguirá recibiendo
correos creyendo que los había desactivado (`S-38`).

El mismo interruptor plantea otra pregunta de diseño: al activarlo, ¿se **apagan** las
preferencias individuales o solo quedan **en suspenso**? Debe ser lo segundo. Si las
sobrescribe, quien lo active y lo desactive vuelve con todo apagado y habrá perdido su
configuración sin saberlo (`S-10`).

### «Comentarios en mis textos» ya no es una sola cosa

Desde que **comentar un capítulo no es corregirlo** (`R-2`, `FEAT-FBK-003`), esa etiqueta
cubre dos hechos muy distintos:

| | Comentario de capítulo | Corrección |
|---|---|---|
| Qué es | Reacción social | El cuestionario respondido |
| Créditos | Ninguno | **El autor los ha pagado** |
| Cuánto importa | Poco | Es el producto |

Meterlos en la misma casilla significa que quien silencie los comentarios **dejará de
enterarse de las correcciones que ha pagado**. Conviene separarlos (`S-11`).

### Faltan la mitad de los avisos

El [catálogo de eventos](../events/README.md) prevé notificaciones que esta pantalla no
permite configurar: solicitudes e invitaciones de lector beta, propuestas de *writing buddy*,
menciones, respuestas a comentarios, valoraciones de una corrección y **movimientos de
créditos**.

O la lista de la maqueta es parcial, o esos avisos no se pueden desactivar. Lo segundo sería
defendible para los créditos y difícil de sostener para el resto (`S-12`).

`FEAT-USR-011` —configurar la recepción de propuestas de LB y *writing buddy*— tampoco tiene
sitio en esta pantalla, y no es una preferencia de notificación sino de **recepción**: no es
lo mismo no querer enterarse que no querer recibirlas.

---

## Pestaña «Privacidad»

| Ajuste | Control | Valor en la maqueta |
|---|---|---|
| ¿Quién puede ver mi perfil? | Desplegable | Todos |
| ¿Quién puede comentar mis textos? | Desplegable | Todos |
| ¿Quién puede mandarme mensajes? | Desplegable | Todos |
| Visibilidad de actividad | Interruptor | Activada |

Solo se ve el valor «Todos»; **las demás opciones no aparecen** (`S-13`). Lo previsible es
«Todos / Seguidores / Nadie», pero hay que confirmarlo: define enums que después se usan en
autorización.

### «¿Quién puede comentar mis textos?» choca con la modalidad de cada obra

Este es el conflicto serio de la pantalla.

Ya existe un eje **por obra**: `BetaReaderAccessMode` —`PUBLIC`, `ON_REQUEST`, `PRIVATE`—,
que decide quién puede acceder a una obra concreta para corregirla. Ahora aparece un eje
**global del usuario** que responde casi a la misma pregunta.

| | Ajuste global | Modalidad de la obra |
|---|---|---|
| Alcance | Todo lo que escribe el usuario | Una obra |
| Lo decide | El perfil | El autor, obra a obra |
| Pregunta | ¿Quién puede comentar mis textos? | ¿Quién puede ser lector beta de esta obra? |

**Decidido (`S-14`): el ajuste global es un techo.**

| Ajuste global | Modalidad de la obra | Resultado |
|---|---|---|
| `Todos` | `PUBLIC` | Cualquiera |
| `Todos` | `PRIVATE` | Solo los invitados — **la obra puede ser más restrictiva** |
| Restrictivo | `PRIVATE` | El de la obra |
| **Restrictivo** | **`PUBLIC`** | **Manda el global: la restricción alcanza a todas las obras** |

En una frase: **una obra puede ser más restrictiva que el perfil, nunca más permisiva.** Si
el ajuste global se endurece, la restricción se aplica a todas las obras, incluidas las que
tengan una modalidad más abierta.

Es la única lectura que mantiene el ajuste como ajuste de privacidad: si la obra pudiera
ganarle, el ajuste global sería una recomendación.

### La consecuencia en créditos

Con la reserva previa
([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md)), conceder acceso
a un lector beta **retiene créditos del autor**. Si el autor endurece después el ajuste
global, hay lectores con acceso concedido que dejarían de poder corregir, y **retenciones que
ya no se van a usar**.

Queda por decidir qué ocurre con ellos (`S-36`). La opción que no destruye trabajo ajeno es
**respetar los accesos ya concedidos** y aplicar la restricción solo a los nuevos: quien
estaba a medio corregir termina, y la retención se consume como estaba previsto. La
alternativa —revocar— obliga además a liberar la retención y a avisar al lector de que su
trabajo ya no sirve.

### «¿Quién puede ver mi perfil?» tiene más alcance del que parece

El perfil no es solo una pantalla: es una **URL pública** (`/profile/{username}`), es el
nombre del autor en cada tarjeta del catálogo y es el avatar junto a cada comentario.

Restringirlo obliga a decidir qué pasa con todo eso:

- ¿Desaparece el autor de las tarjetas del catálogo?
- ¿Deja de resolver `/profile/{username}`, y con qué código —`404` o `403`?
- ¿Sigue apareciendo su nombre junto a los comentarios que ya escribió?

Un «404» y un «403» dicen cosas distintas a quien pregunta: el segundo **confirma que la
cuenta existe**. Para un ajuste de privacidad, eso importa (`S-15`).

### «Visibilidad de actividad» no dice qué es actividad

¿Las publicaciones del muro? ¿Las obras que lee? ¿Las correcciones que da? ¿A quién sigue?

Cada una tiene consecuencias distintas, y una de ellas es delicada: si «actividad» incluye
**qué obras lee**, ocultarlo es razonable; si incluye **qué correcciones ha hecho**, afecta a
`FEAT-FBK-010` y al contador público de correcciones del perfil (`U-17`). Ver `S-16`.

### Estos ajustes se aplican en el backend, no en la interfaz

Conviene dejarlo escrito porque es la clase de ajuste que se implementa filtrando en el
cliente: **cada uno de los cuatro es una regla de autorización**. Un perfil restringido tiene
que devolver `403` o `404` aunque alguien llame al endpoint directamente, y un usuario que no
puede mandar mensajes tiene que ser rechazado en el `POST`, no solo perder el botón.

---

## Pestaña «Apariencia»

No hay captura. Previsiblemente tema claro/oscuro y quizá tamaño de texto.

La pregunta para el backend es si esas preferencias **se guardan en el servidor** —y por tanto
siguen al usuario entre dispositivos— o viven en el navegador. Para una plataforma de lectura,
lo primero tiene valor real. Ver `S-17`.

---

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Editar nombre, biografía y foto | `FEAT-USR-008` |
| 2 | Subir y eliminar la foto de perfil | `FEAT-USR-037` |
| 3 | Cambiar el correo, con verificación del nuevo | `FEAT-USR-040` |
| 4 | Cambiar la contraseña, con la actual | `FEAT-USR-041` |
| 5 | Establecer contraseña en cuentas de Google | `FEAT-USR-041`, `S-8` |
| 6 | Eliminar la cuenta | `FEAT-USR-013` |
| 7 | Preferencias de notificación por canal y por tipo | `FEAT-USR-039` |
| 8 | Distinguir notificación de correo transaccional | `FEAT-USR-039` |
| 9 | Ajustes de privacidad aplicados como autorización | `FEAT-USR-038` |
| 10 | Preferencias de apariencia | `FEAT-USR-042` |
| 11 | Cambiar el nombre de usuario desde algún sitio | `FEAT-USR-034`, `S-3` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-11** | ¿Se separan «corrección recibida» y «comentario en un capítulo» como avisos distintos? | Silenciar comentarios dejaría al autor sin enterarse de lo que ha pagado |
| **S-36** | Al endurecer el ajuste global, ¿se respetan los accesos de lector beta ya concedidos? | Si se revocan, hay retenciones que liberar y trabajo a medias que se pierde |
| **S-13** | ¿Qué opciones tienen los tres desplegables? | Definen enums usados en autorización |
| **S-16** | ¿Qué es «actividad»? | Cambia qué se oculta y a quién |
| **S-15** | Con el perfil restringido, ¿qué devuelve `/profile/{username}`: `403` o `404`? | Un `403` confirma que la cuenta existe |
| **S-12** | ¿Por qué no se pueden configurar los demás avisos del catálogo de eventos? | O la lista es parcial, o hay avisos obligatorios |
| **U-3** | ¿Qué pasa con las obras propias al eliminar la cuenta? | La anonimización resuelve la persona, no su obra |
| S-7 | ¿Cambiar el correo exige la contraseña actual? | Es una operación sensible |
| S-37 | Si el nombre de usuario pedido está ocupado, ¿se pierde el resto del guardado? | Un «Guardar» único para campos con reglas muy distintas |
| S-38 | ¿Se corrige el texto del interruptor general, que promete silenciar todo? | Seguirán llegando correos operativos |
| S-1 | ¿Cada pestaña guarda por separado? ¿Cambiar de pestaña con cambios sin guardar los pierde? | Comportamiento del formulario |
| S-2 | ¿Desde dónde se llega a Configuración? | No hay entrada en el menú lateral |
| S-9 | ¿La asimetría entre canales es deliberada? | El modelo no debe asumir una matriz completa |
| S-10 | El interruptor general, ¿suspende o sobrescribe las preferencias? | Si las sobrescribe, el usuario pierde su configuración |
| S-17 | ¿Las preferencias de apariencia se guardan en el servidor? | Decide si hay endpoint |
| S-19 | ¿Y la configuración de propuestas de LB y *writing buddy* (`FEAT-USR-011`)? | No es una preferencia de aviso sino de recepción |
| S-27 | ¿Se puede corregir la fecha de nacimiento? | Se pide en el onboarding y no reaparece |
| S-5 | ¿La «página de autor» de `FEAT-USR-015` tiene un texto aparte de la biografía? | Si lo tiene, son dos campos y deben llamarse distinto |

**Resueltas el 2026-09-23:**

| # | Decisión |
|---|---|
| `S-3` | La pestaña «Perfil» **sí** permite cambiar el nombre de usuario |
| `S-18` | También las **preferencias literarias** |
| `S-4` | La biografía se amplía a **300 caracteres**, y es el texto que se ve bajo la foto en «Mi perfil» |
| `S-6` | La ausencia del recorte es un **error de maqueta**: aplica el modal de `FEAT-USR-037` |
| `S-8` | Una cuenta de Google cambia su contraseña **dejando la actual en blanco** |
| `S-14` | El ajuste global de privacidad es un **techo**: una obra puede ser más restrictiva, nunca más permisiva |
| `S-32` | Eliminar la cuenta la **anonimiza** |
| — | El interruptor general **no afecta a las notificaciones operativas** |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | La zona de subida muestra texto por defecto de Ant Design, en inglés, hablando de «bulk upload» y con la errata «band files» | Texto de maqueta sin sustituir |
| A-2 | «¿Quien puede comentar mis textos?» y «¿Quien puede mandarme mensajes?» sin tilde en «Quién» | Corregir |
| A-3 | «Terms & Conditions» sigue en inglés en el menú lateral | Ya anotado como `L-5` en el layout |
| A-4 | La advertencia de eliminar cuenta promete un borrado total | **Hay que reescribirla:** la cuenta se anonimiza (`S-32`) |
| A-5 | «Desactivar todas las notificaciones» dice que no llegará «ningún tipo de notificación» | No es cierto: los correos operativos siguen llegando (`S-38`) |
| A-6 | Las capturas no muestran el nombre de usuario ni las preferencias literarias | Están en la pestaña; la maqueta está incompleta |
