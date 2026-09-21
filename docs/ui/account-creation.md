---
screen: Flujo de creación de cuenta y onboarding
figma: https://www.figma.com/design/egiA5ltTOBRXqwerrqC1jW/WebApp_LectoresBeta?node-id=1800-13778
features: [FEAT-USR-001, FEAT-USR-002, FEAT-USR-020, FEAT-USR-021, FEAT-USR-022, FEAT-USR-023, FEAT-USR-024, FEAT-USR-025, FEAT-COM-016, FEAT-NOT-008, FEAT-CRD-002]
actors: [Guest, User]
updated: 2026-09-21
---

# Flujo de creación de cuenta y onboarding

Sección de Figma: **🟢 Onboarding de creación de cuenta** (`1800:13778`).

## Propósito

Llevar a una persona sin cuenta desde el formulario de registro hasta el Home, pasando por
un onboarding de tres pasos que recoge los datos mínimos de perfil y los intereses
necesarios para poder recomendarle contenido.

## Mapa del flujo

```text
  Crea una cuenta                 (1470:9482)
  email + contraseña, o Google  (Facebook y LinkedIn diferidos)
        │
        ├──────────────▶ Email «Activa tu cuenta»      (1470:9560)
        │                 se envía al registrarse
        ▼
  Onboarding 1/3  «Casi lo tienes, {alias}!»           (1470:9456, 1679:8998)
  Nombre + fecha de nacimiento
        │                 ┌─────────────────────────────────┐
        ▼                 │ Aviso persistente en todos los  │
  Onboarding 2/3  «Vamos a│ pasos: «Te hemos enviado un     │
  conocerte mejor»        │ enlace a tu correo…» +          │
  ≥3 géneros   (1470:9581)│ «Reenviar enlace»               │
        │                 └─────────────────────────────────┘
        ▼
  Onboarding 3/3  «Autores a los que seguir»           (1470:9640)
  opcional · SE OMITE si no hay autores suficientes
        │
        ▼
  Home                                                 (2035:13949)
  en modo vacío si no sigue a nadie
```

**La cuenta nace bloqueada.** Hasta activar el correo no tiene créditos y no puede escribir
nada: ni comentar, ni recibir comentarios, ni publicar en el muro. Solo leer y completar el
onboarding. Ver [`decision:0003`](../decisions/0003-write-operations-require-activated-account.md)
y [`FEAT-USR-025`](../features/user/FEAT-USR-025-block-writes-until-activation.md).

**El onboarding no espera a la activación del correo.** El aviso de verificación aparece en
los tres pasos como panel lateral informativo, no como bloqueo. Es una decisión de producto
importante y tiene consecuencias de seguridad: ver `OB-3`.

## Pantallas

---

### 1. Crea una cuenta (`1470:9482`)

| Elemento | Detalle |
|---|---|
| Título | «Crea una cuenta» |
| Campo | «Tu correo» — email, con icono |
| Campo | «Contraseña» — con enlace «Mostrar» para alternar visibilidad |
| Requisitos de contraseña | Dos indicadores con check, visibles bajo el campo: «Al menos 8 caracteres.» y «Una mayúscula, un número y un carácter especial (!@#$%^&*).» |
| Checkbox | «Aceptas nuestras **condiciones de uso** y nuestra **política de privacidad**.» Ambos textos son enlaces. Aparece marcado en el diseño |
| Botón primario | «Registrarse» |
| Separador | «o continúa» |
| Acceso social | El diseño muestra Google, Facebook y LinkedIn. **En esta fase solo se implementa Google**; los otros dos botones no se muestran todavía |

> **Hueco de diseño:** la casilla de condiciones y privacidad está bajo el formulario de
> email, y el botón de Google queda fuera de su alcance. Tal como está, quien entra con
> Google no acepta nada.
>
> **Regla aplicada:** el alta con Google exige la misma aceptación (`FEAT-USR-002`). El
> backend no crea la cuenta sin ella. Falta decidir cómo se recoge en la interfaz: casilla
> previa que gobierne también los botones sociales, o pantalla intermedia al volver de Google
> (recomendada, porque no depende de conservar estado durante la redirección). Ver `T-5`.
| Pie | «Ya eres usuario **inicia sesion**» |

**Lo que no hay, y es significativo:**

- **No hay campo de nombre de usuario**, y no lo habrá: la plataforma no usa ese concepto.
  El PDF de casos de uso sí lo pedía.
- **No hay confirmación de contraseña.** El enlace «Mostrar» la sustituye.
- **No hay campos de perfil.** Todo lo demás se recoge en el onboarding.

Los dos indicadores de contraseña con icono de check sugieren **validación en vivo en
cliente**. El backend debe aplicar la misma política igualmente: la validación de cliente no
es una garantía.

---

### 2. Email de activación (`1470:9560`)

Nota del diseño (`1679:9091`): *«Tras darle a "Registrarse" el usuario recibe el siguiente
mail para completar el registro en la plataforma.»*

| Elemento | Contenido |
|---|---|
| Asunto | Sin definir en el diseño |
| Encabezado | «🌟 ¡Gracias por unirte a lectoresbeta!» |
| Cuerpo | Presentación de la comunidad e invitación a activar la cuenta |
| CTA | **«ACTIVAR MI CUENTA»** — enlace con token |
| Soporte | Enlace a la página de soporte |
| Firma | «El equipo de lectoresbeta» + cita de George R. R. Martin |
| Pie | «Ver en navegador» e iconos de redes sociales |

> **Aplicado:** se retira «Cancelar suscripción» del pie. Es un correo transaccional, y un
> usuario que se diera de baja ahí no podría activar su cuenta ni, por tanto, usar la
> plataforma. La baja sigue existiendo en los correos de aviso y novedades, que sí son
> opcionales.

Este correo es el único camino para desbloquear la cuenta: de él dependen los 20 créditos de
bienvenida y todas las operaciones de escritura.

---

### 3. Onboarding 1/3 — Datos personales (`1470:9456`, error en `1679:8998`)

| Elemento | Detalle |
|---|---|
| Stepper | 3 pasos, primero activo |
| Título | «Casi lo tienes, **{alias}**!» — en el diseño, «beatrizalonso» |
| Subtítulo | «Vamos a comenzar a crear tu perfil, necesitamos saber tus datos y tu intereses» |
| Campo | «Nombre». **Dato público.** El diseño le pone tooltip de privacidad: hay que retirarlo |
| Campo | «Fecha de nacimiento», formato `XX/XX/XXXX`, con tooltip |
| Tooltip | «Esta información solo será visible para ti y el equipo de LectoresBeta.» **Solo corresponde a la fecha de nacimiento** |
| Error | «Formato incorrecto de fecha» bajo el campo, en rojo |
| Panel lateral | Aviso de verificación de email con «Reenviar enlace» |

**Dos consecuencias directas para el backend:**

1. **Los dos campos tienen visibilidad opuesta.** El **Nombre es público**: al no existir
   nombre de usuario, es el referente con el que se identifica a una persona en perfiles,
   catálogo, muro, comentarios, rankings y sugerencias. La **fecha de nacimiento es
   privada** y no se devuelve en ninguna respuesta dirigida a terceros.

   > **Corrección de diseño:** el tooltip de privacidad aparece en ambos campos y solo
   > corresponde a la fecha de nacimiento. En el campo Nombre dice lo contrario de lo que
   > ocurre y debe retirarse.
2. **El saludo usa un alias derivado del email**: la parte anterior a la `@`. No se
   almacena, no es único y no identifica al usuario. Es solo presentación.

---

### 4. Onboarding 2/3 — Géneros de interés (`1470:9581`)

| Elemento | Detalle |
|---|---|
| Stepper | 3 pasos, dos completados |
| Título | «Vamos a conocerte mejor» |
| Instrucción | «Marca por lo menos tres géneros que te interesen.» |
| Selección | Chips multiselección; los seleccionados se marcan y muestran una «×» para quitarlos |
| Lista | Desplazable verticalmente |
| Botón | «Siguiente» |

Nota del diseño (`1679:9226`): *«El botón de "Continuar" sólo se activará en el momento en
que se hayas seleccionado 3 o más temáticas»*.

Géneros visibles en el diseño:

`Aventura` · `Ciencia Ficción` · `Comedia` · `Drama` · `Fantasía` · `Histórico` ·
`Infantil` · `Misterio` · `Poesía` · `Policíaco` · `Romance` · `Terror` · `Thriller`

> **Aplicado:** el diseño escribe «Poeta» y se corrige a «Poesía». Era el único chip que
> nombraba a la persona y no al género.

Hay además cuatro chips de relleno (`Sample 1` a `Sample 4`) y la lista tiene scroll, así
que **el catálogo no está cerrado**.

> «Poeta» es casi con seguridad una errata de «Poesía»: es el único chip que nombra a la
> persona y no al género. Ver `OB-5`.

> **Aplicado:** el botón se llama **«Siguiente»** en todo el onboarding. La nota del flujo lo
> llamaba «Continuar».

---

### 5. Onboarding 3/3 — Autores a los que seguir (`1470:9640`)

| Elemento | Detalle |
|---|---|
| Stepper | 3 pasos, los tres completados |
| Título | «Autores a los que seguir» |
| Subtítulo | «Algunos autores a los que seguir en base a los intereses que has elegido. **Puedes hacerlo más tarde.**» |
| Tarjeta | Avatar, nombre, «2K Seguidores | 48 Publicaciones» |
| Acción | Botón «Seguir» que pasa a «Siguiendo» (estado alterno visible en la segunda tarjeta) |
| Lista | Desplazable, al menos 5 sugerencias |
| Botones | «Atrás», **«Saltar»** y «Siguiente» |

> **Aplicado:** se añade el botón «Saltar» que pedía la nota del flujo («CTA para saltar») y
> que el diseño no incluía.

Los contadores «Seguidores» y «Publicaciones» son agregados: necesitan un read model, no un
recuento en vivo por cada tarjeta.

### Cuando no hay autores suficientes

Es el caso normal durante los primeros meses: si no hay autores, no hay nada que sugerir.

El servidor construye la lista en tres tramos y **decide él si la pantalla se muestra**:

1. autores que publican en los géneros elegidos;
2. si no se llega a **tres**, se completa con los autores más seguidos de la plataforma, sin
   filtrar por género;
3. si aun así no se llega a tres, **el paso se omite entero**: el onboarding termina en dos
   pasos, el stepper muestra dos, y el usuario llega directamente al Home en modo vacío.

La respuesta lleva `shouldDisplay` y `reason` para que el frontend no tenga que replicar la
regla. Detalle completo en
[`FEAT-COM-016`](../features/community/FEAT-COM-016-onboarding-author-suggestions.md).

---

### 6. Destino: Home (`2035:13949`)

La nota del flujo deja el destino explícitamente abierto: *«Definir a dónde llega?»*, con dos
candidatos —enlazar con el onboarding de creación de perfil, o ir al Home— y una precisión:
*«si se salta el seguir usuarios sería en modo empty screen»*.

En el Home se ve un menú lateral con un contador de créditos («12 Créditos»). Queda fuera del
alcance de este documento.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Registro con email y contraseña, sin nombre de usuario | `FEAT-USR-001` |
| 2 | Política de contraseña: ≥8 caracteres, una mayúscula, un número y un carácter especial | `FEAT-USR-001` |
| 3 | Registro y login con LinkedIn, además de Google y Facebook | `FEAT-USR-019` |
| 4 | Registrar la aceptación de condiciones de uso y política de privacidad, con versión y fecha, **sea cual sea el método de alta** | `FEAT-USR-024`, `FEAT-USR-002` |
| 5 | Estado de cuenta `PENDING_ACTIVATION` que no bloquea el onboarding | `FEAT-USR-020` |
| 6 | Email de activación con token de un solo uso | `FEAT-NOT-008` |
| 7 | Activación de la cuenta mediante el token | `FEAT-USR-020` |
| 8 | Reenvío del email de activación, con límite de frecuencia | `FEAT-USR-021` |
| 9 | Guardar el nombre como **dato público** y la fecha de nacimiento como **dato privado** | `FEAT-USR-022` |
| 10 | Validar el formato y la coherencia de la fecha de nacimiento | `FEAT-USR-022` |
| 11 | Catálogo de géneros consultable, no cerrado en código | `FEAT-USR-023` |
| 12 | Guardar los géneros de interés, mínimo tres, validado en servidor | `FEAT-USR-023` |
| 13 | Sugerir autores según los géneros elegidos | `FEAT-COM-016` |
| 14 | Seguir y dejar de seguir a un autor | `FEAT-COM-010` |
| 15 | Contadores de seguidores y publicaciones por autor | `FEAT-COM-016` |
| 16 | Estado del onboarding persistido, para poder retomarlo | `FEAT-USR-022` |

## Contradicciones detectadas

| # | Qué dice el diseño | Qué dice la documentación | Resolución |
|---|---|---|---|
| C-1 | Google, Facebook y LinkedIn | `use-cases.pdf` solo contempla Google y Facebook | **Resuelta:** en esta fase **solo Google**. Facebook y LinkedIn quedan `DEFERRED` |
| C-2 | No se pide nombre de usuario | `use-cases.pdf` lo pedía; la ficha original exigía unicidad | **Resuelta:** el nombre de usuario **no existe** en la plataforma. El saludo usa un alias derivado del email |
| C-3 | Registro en una sola pantalla | Nota del propio Figma: «Login con email: 01 → Introducir email, 02 → Contraseña» | Probablemente la nota describe el **login**, no el registro. Confirmar |
| C-4 | Paso 3 sin botón de saltar | Nota del propio Figma: «(opcional, CTA para saltar)» | **Resuelta:** se añade «Saltar» |
| C-5 | Chip «Poeta» | El resto de chips nombran géneros | **Resuelta:** se corrige a «Poesía» |
| C-6 | Home muestra «12 Créditos» | `credit-system.pdf`: la cuenta nueva recibe **+20** | Dato de maqueta. Además, una cuenta sin activar tiene **saldo 0**: el Home debe reflejar el saldo real |

Queda abierta `C-3`; el resto se han resuelto.

## Funcionalidades afectadas

| ID | Acción | Estado tras esta revisión |
|---|---|---|
| FEAT-USR-001 | Ficha ampliada: política de contraseña, términos, sin nombre de usuario, créditos al activar | `DRAFT` |
| FEAT-USR-025 | **Nueva** — bloquear escritura hasta activar la cuenta | `DRAFT` |
| FEAT-USR-002 | **Ficha nueva** — registro con Google, con aceptación legal obligatoria | `DRAFT` |
| FEAT-USR-003, FEAT-USR-006 | Facebook | `DEFERRED` |
| FEAT-USR-019 | **Nueva** — registro y login con LinkedIn | `DEFERRED` |
| FEAT-USR-020 | **Nueva** — activar la cuenta desde el email | `DRAFT` |
| FEAT-USR-021 | **Nueva** — reenviar el email de activación | `DRAFT` |
| FEAT-USR-022 | **Nueva** — onboarding: nombre y fecha de nacimiento | `DRAFT` |
| FEAT-USR-023 | **Nueva** — onboarding: elegir géneros, mínimo tres | `DRAFT` |
| FEAT-USR-024 | **Nueva** — aceptación de condiciones y privacidad | `DRAFT` |
| FEAT-COM-016 | **Nueva** — sugerencias de autores a seguir | `DRAFT` |
| FEAT-NOT-008 | **Nueva** — email de activación de cuenta | `DRAFT` |
| FEAT-USR-009 | Editar preferencias literarias: comparte catálogo y reglas con `FEAT-USR-023` | `PENDING` |
| FEAT-COM-010 | Suscribirse a un autor: es la acción del botón «Seguir» del paso 3 | `PENDING` |

## Preguntas que la pantalla no responde

| # | Pregunta | Impacto |
|---|---|---|
| **OB-11** | **¿El alta con Google crea la cuenta ya activada?** Google verifica el correo antes de emitir el token, así que pedir una segunda verificación sería redundante | Si no, un usuario de Google tendría que activar un correo ya verificado para poder escribir |
| **T-5** | **¿Cómo se recoge la aceptación legal en el alta con Google: casilla previa o pantalla intermedia?** | La regla de backend ya impide crear la cuenta sin ella; falta la pieza de interfaz |
| N-1, N-2 | ¿Qué reglas sigue el nombre y debe ser único? | Sin unicidad, dos homónimos son indistinguibles en comentarios y rankings |
| OB-7 | ¿Hay edad mínima para registrarse? Se pide la fecha de nacimiento pero no se dice para qué | **Legal.** En España el consentimiento digital del menor tiene un umbral de edad |
| OB-9 | ¿Caduca el enlace de activación? ¿Cada cuánto se puede reenviar? | `FEAT-USR-020`, `FEAT-USR-021`. Propuesta: 24 h y 60 s |
| OB-10 | ¿El onboarding se puede abandonar y retomar? | Asumido que sí, con estado persistido paso a paso |
| OB-12 | ¿A dónde se llega al terminar: Home o un segundo onboarding de perfil? | La nota del propio Figma lo deja abierto |
| OB-14 | ¿Cuál es el catálogo completo de géneros? ¿Es administrable? | Los chips «Sample» indican que está sin cerrar |
| A-2 | ¿Qué ve una cuenta sin activar al intentar escribir: aviso persistente o error al enviar? | `FEAT-USR-025` necesita diseño |
| C-3 | ¿La nota «01 → email, 02 → contraseña» describe el login y no el registro? | Coherencia |

**Resueltas:** `OB-1` (alias del email), `OB-2` (el nombre es público), `OB-3` (ver
`decision:0003`), `OB-4` («Saltar»), `OB-5` («Poesía»), `OB-6` (autores insuficientes),
`OB-8` (sin baja de suscripción), `OB-13` («Siguiente») y `T-4` (sin aceptación legal no hay
cuenta, tampoco con Google).

## Correcciones de diseño pendientes

| # | Qué corregir | Motivo |
|---|---|---|
| 1 | Retirar el tooltip de privacidad del campo «Nombre» | El nombre es público; el tooltip dice lo contrario |
| 2 | Recoger la aceptación legal en el alta con Google | Hoy quien entra por ahí no acepta nada |
| 3 | Ocultar los botones de Facebook y LinkedIn | Diferidos en esta fase |
| 4 | Corregir el chip «Poeta» por «Poesía» | Es el género, no la persona |
| 5 | Retirar «Cancelar suscripción» del correo de activación | Es transaccional: sin él no se puede usar la cuenta |
| 6 | Añadir el botón «Saltar» al paso 3 | El paso es opcional y la nota del flujo ya lo pedía |
| 7 | Etiquetar el botón del onboarding como «Siguiente» | La nota lo llamaba «Continuar» |
| 8 | Prever el stepper de dos pasos | El paso 3 se omite si no hay autores suficientes |
