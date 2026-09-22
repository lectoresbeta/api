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
| **Biografía** | Área de texto | Contador **`0 / 100`** |
| **Foto de perfil** | Avatar actual + zona de arrastre | Ver abajo |

### Falta el nombre de usuario

La pestaña permite editar el nombre, pero **no el nombre de usuario**.

Eso contradice [`FEAT-USR-034`](../features/user/FEAT-USR-034-change-username.md), que
especifica cambiarlo con todas sus consecuencias: una vez cada 30 días, el anterior queda
como alias, los enlaces antiguos siguen funcionando.

El endpoint existe y está especificado —`PUT /me/username`—, así que lo que falta es
**dónde se llama desde la interfaz**. Si no es aquí, no es en ninguna pantalla conocida. Ver
`S-3`.

No es un olvido menor: el nombre de usuario gobierna la URL pública del perfil
(`/profile/{username}`), y toda la maquinaria de alias de
[`decision:0005`](../decisions/0005-username-with-temporary-aliases.md) existe precisamente
para que ese cambio sea posible sin romper enlaces.

### La biografía cabe en 100 caracteres

Cien caracteres son unas quince palabras. Para una plataforma de escritores, donde la
biografía es la carta de presentación del autor, es **muy poco**: no llega ni para una
frase sobre qué escribe y qué busca.

Conviene confirmar que el límite es deliberado y no un valor de maqueta (`S-4`). También si
se cuenta en caracteres o en palabras: el resto del producto mide **en palabras** (`R-5`).

Y hay una segunda pregunta detrás: `FEAT-USR-015` describe una «página de autor» con bio,
foto y referencias. **¿Es esta biografía la misma?** Si lo es, cien caracteres la vacían de
sentido; si no lo es, hay dos biografías y hay que decir cuál se muestra dónde (`S-5`).

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

O son dos caminos a la misma funcionalidad —y esta pantalla abre el mismo modal tras soltar
el fichero—, o el diseño ha cambiado. **`S-6`, conviene resolverlo**: si el cliente deja de
recortar, el servidor tiene que hacerlo, y eso cambia `RN-4b` de `FEAT-USR-037`.

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

Y hay un caso que la pantalla no contempla: **quien se registró con Google no tiene
contraseña** (`FEAT-USR-002`). Enseñarle «Contraseña actual» es pedirle algo que no existe.
Lo razonable es ofrecerle *establecer* una, no *cambiarla* (`S-8`).

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

`FEAT-USR-013` está `BLOCKED` exactamente por esto (`V-4`, `U-3`), y la pantalla no lo
resuelve: lo agrava, porque **promete al usuario un borrado total que el sistema no puede
hacer**.

Hay una salida conocida y conviene nombrarla: **anonimizar en vez de borrar**. Se elimina
todo lo que identifica a la persona —nombre, correo, foto, biografía— y se conserva lo que
pertenece a otros —la corrección que un autor pagó, el movimiento de créditos—, atribuido a
un usuario eliminado. Cumple el derecho de supresión sin destruir lo ajeno.

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

Son correos **transaccionales**, no notificaciones: responden a algo que el usuario acaba de
pedir o que afecta a la seguridad de su cuenta. La distinción tiene que existir en el modelo,
o el interruptor dejará a alguien sin poder recuperar su cuenta.

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

Cuando dos ajustes responden a lo mismo con distinto alcance hay que decir **cuál manda**. La
regla habitual, y la más segura, es que gane **el más restrictivo**: si el usuario ha dicho
«solo seguidores», una obra `PUBLIC` no debería abrir la puerta a cualquiera.

Y hay una consecuencia económica que no se ve a simple vista: con la reserva previa
([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md)), restringir
quién puede comentar **puede dejar retenciones sin uso**. Si alguien tenía acceso concedido y
el ajuste global se lo quita, hay una retención que liberar. Ver `S-14`.

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
| **S-3** | ¿Dónde se cambia el nombre de usuario? No está en esta pantalla | `FEAT-USR-034` se queda sin interfaz |
| **S-11** | ¿Se separan «corrección recibida» y «comentario en un capítulo» como avisos distintos? | Silenciar comentarios dejaría al autor sin enterarse de lo que ha pagado |
| **S-14** | Entre el ajuste global «quién puede comentar» y la modalidad de cada obra, ¿cuál manda? | Dos ajustes para la misma pregunta. Afecta a autorización y a retenciones |
| **S-13** | ¿Qué opciones tienen los tres desplegables? | Definen enums usados en autorización |
| **S-16** | ¿Qué es «actividad»? | Cambia qué se oculta y a quién |
| **S-15** | Con el perfil restringido, ¿qué devuelve `/profile/{username}`: `403` o `404`? | Un `403` confirma que la cuenta existe |
| **S-10** | El interruptor general, ¿apaga las preferencias o las deja en suspenso? | Si las sobrescribe, el usuario pierde su configuración |
| **S-12** | ¿Por qué no se pueden configurar los demás avisos del catálogo de eventos? | O la lista es parcial, o hay avisos obligatorios |
| **S-8** | Una cuenta creada con Google, ¿ve «cambiar contraseña» o «establecer contraseña»? | Hoy se le pide algo que no tiene |
| **S-7** | ¿Cambiar el correo exige la contraseña actual? | Es una operación sensible |
| **S-4** | ¿100 caracteres de biografía es deliberado? ¿Caracteres o palabras? | Para una plataforma de escritores es muy poco |
| **S-5** | ¿Esta biografía es la de la «página de autor» de `FEAT-USR-015`? | O hay dos biografías |
| **S-6** | ¿Esta zona de arrastre abre el modal de recorte, o el recorte desaparece? | Si el cliente deja de recortar, el servidor tiene que hacerlo |
| S-1 | ¿Cada pestaña guarda por separado? ¿Cambiar de pestaña con cambios sin guardar los pierde? | Comportamiento del formulario |
| S-2 | ¿Desde dónde se llega a Configuración? | No hay entrada en el menú lateral |
| S-9 | ¿La asimetría entre canales es deliberada? | El modelo no debe asumir una matriz completa |
| S-17 | ¿Las preferencias de apariencia se guardan en el servidor? | Decide si hay endpoint |
| S-18 | ¿Dónde se editan las preferencias literarias (`FEAT-USR-009`)? | Tampoco están aquí |
| S-19 | ¿Y la configuración de propuestas de LB y *writing buddy* (`FEAT-USR-011`)? | No es una preferencia de aviso sino de recepción |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | La zona de subida muestra texto por defecto de Ant Design, en inglés, hablando de «bulk upload» y con la errata «band files» | Texto de maqueta sin sustituir |
| A-2 | «¿Quien puede comentar mis textos?» y «¿Quien puede mandarme mensajes?» sin tilde en «Quién» | Corregir |
| A-3 | «Terms & Conditions» sigue en inglés en el menú lateral | Ya anotado como `L-5` en el layout |
| A-4 | La advertencia de eliminar cuenta promete un borrado total que el sistema no puede hacer | No es cosmético: ver `FEAT-USR-013` |
