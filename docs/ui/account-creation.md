---
screen: Flujo de creación de cuenta y onboarding
figma: https://www.figma.com/design/egiA5ltTOBRXqwerrqC1jW/WebApp_LectoresBeta?node-id=1800-13778
features: [FEAT-USR-001, FEAT-USR-002, FEAT-USR-003, FEAT-USR-019, FEAT-USR-020, FEAT-USR-021, FEAT-USR-022, FEAT-USR-023, FEAT-USR-024, FEAT-COM-016, FEAT-NOT-008, FEAT-CRD-002]
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
  email + contraseña, o Google / Facebook / LinkedIn
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
  opcional
        │
        ▼
  Home                                                 (2035:13949)
```

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
| Botón primario | Etiqueta sin definir en el diseño (`Button Title`). La nota del flujo lo llama «Registrarse» |
| Separador | «o continúa» |
| Acceso social | **Google, Facebook y LinkedIn** |
| Pie | «Ya eres usuario **inicia sesion**» |

**Lo que no hay, y es significativo:**

- **No hay campo de nombre de usuario.** El PDF de casos de uso sí lo pide en el registro.
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
| Pie | «Ver en navegador», «Cancelar suscripción», iconos de redes sociales |

> **Revisar:** «Cancelar suscripción» en un correo transaccional de activación. Un usuario que
> se dé de baja ahí no podría recibir el correo que necesita para activar su cuenta. Los
> correos transaccionales no deberían llevar baja de suscripción. Ver `OB-8`.

---

### 3. Onboarding 1/3 — Datos personales (`1470:9456`, error en `1679:8998`)

| Elemento | Detalle |
|---|---|
| Stepper | 3 pasos, primero activo |
| Título | «Casi lo tienes, **{alias}**!» — en el diseño, «beatrizalonso» |
| Subtítulo | «Vamos a comenzar a crear tu perfil, necesitamos saber tus datos y tu intereses» |
| Campo | «Nombre» con tooltip informativo |
| Campo | «Fecha de nacimiento», formato `XX/XX/XXXX`, con tooltip |
| Tooltip (ambos) | «Esta información solo será visible para ti y el equipo de LectoresBeta.» |
| Error | «Formato incorrecto de fecha» bajo el campo, en rojo |
| Panel lateral | Aviso de verificación de email con «Reenviar enlace» |

**Dos consecuencias directas para el backend:**

1. **El tooltip es una regla de privacidad, no un texto decorativo.** Nombre y fecha de
   nacimiento **no son datos públicos**. `GET /users/{userId}` no debe devolverlos nunca.
2. **El saludo usa un alias que la pantalla no ha pedido todavía.** De dónde sale es una
   pregunta abierta (`OB-1`): puede venir de la parte local del email, del proveedor social,
   o ser un campo del registro que falta en el diseño.

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
`Infantil` · `Misterio` · `Poeta` · `Policíaco` · `Romance` · `Terror` · `Thriller`

Hay además cuatro chips de relleno (`Sample 1` a `Sample 4`) y la lista tiene scroll, así
que **el catálogo no está cerrado**.

> «Poeta» es casi con seguridad una errata de «Poesía»: es el único chip que nombra a la
> persona y no al género. Ver `OB-5`.

La nota del flujo llama al botón «Continuar» y el diseño lo etiqueta «Siguiente». Conviene
unificar.

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
| Botones | «Atrás» y «Siguiente» |

**El subtítulo confirma que el paso es opcional**, pero no hay botón explícito de «Saltar»
pese a que la nota del flujo pide un «CTA para saltar». Ver `OB-4`.

La nota del flujo también deja abierto el criterio de sugerencia: *«(más leídos o en función
de los géneros que ha seleccionado?)»*. El subtítulo de la pantalla ya afirma que es por
géneros elegidos, así que la pantalla resuelve la duda de la nota. Queda por definir el
desempate y el orden (`OB-6`).

Los contadores «Seguidores» y «Publicaciones» son agregados: necesitan un read model, no un
recuento en vivo por cada tarjeta.

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
| 4 | Registrar la aceptación de condiciones de uso y política de privacidad, con versión y fecha | `FEAT-USR-024` |
| 5 | Estado de cuenta `PENDING_ACTIVATION` que no bloquea el onboarding | `FEAT-USR-020` |
| 6 | Email de activación con token de un solo uso | `FEAT-NOT-008` |
| 7 | Activación de la cuenta mediante el token | `FEAT-USR-020` |
| 8 | Reenvío del email de activación, con límite de frecuencia | `FEAT-USR-021` |
| 9 | Guardar nombre y fecha de nacimiento como **datos privados** | `FEAT-USR-022` |
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
| C-1 | Google, Facebook **y LinkedIn** | `use-cases.pdf` solo contempla Google y Facebook | **El diseño manda.** Se añade `FEAT-USR-019`. Confirmar que LinkedIn entra en alcance |
| C-2 | No se pide nombre de usuario en el registro | `use-cases.pdf`: «Registrarse introduciendo datos de usuario como email, password, etc.»; `FEAT-USR-001` `RN-2` exige nombre de usuario único | **Sin resolver.** La pantalla siguiente saluda con un alias que nadie ha introducido. Ver `OB-1` |
| C-3 | Registro en una sola pantalla | Nota del propio Figma: «Login con email: 01 → Introducir email, 02 → Contraseña» | Probablemente la nota describe el **login**, no el registro. Confirmar |
| C-4 | Paso 3 sin botón de saltar | Nota del propio Figma: «Segunda parte → (opcional, CTA para saltar)» | El subtítulo ya dice «Puedes hacerlo más tarde». Falta el botón o sobra la nota. Ver `OB-4` |
| C-5 | Chip «Poeta» | El resto de chips nombran géneros | Errata muy probable de «Poesía». Ver `OB-5` |
| C-6 | Home muestra «12 Créditos» | `credit-system.pdf`: la cuenta nueva recibe **+20** | Casi seguro dato de maqueta. Confirmar que el Home refleja el saldo real |

Ninguna de estas contradicciones se ha resuelto por cuenta propia: `C-1` se ha incorporado
porque el diseño es posterior y más concreto; el resto quedan registradas como preguntas.

## Funcionalidades afectadas

| ID | Acción | Estado tras esta revisión |
|---|---|---|
| FEAT-USR-001 | Ficha ampliada con la política de contraseña, la aceptación de términos y la ausencia de nombre de usuario | `DRAFT` |
| FEAT-USR-019 | **Nueva** — registro y login con LinkedIn | `DRAFT` |
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
| **OB-1** | **¿De dónde sale el alias del saludo «Casi lo tienes, beatrizalonso!» si el registro no pide nombre de usuario?** ¿Se deriva del email, lo da el proveedor social, o falta un campo? | **Bloqueante** para `FEAT-USR-001` y `FEAT-USR-022`: define si `username` existe, cuándo se fija y cómo se garantiza su unicidad |
| **OB-2** | ¿El campo «Nombre» del paso 1 es el nombre real, el nombre público de autor, o el alias? | Determina si es dato privado (el tooltip dice que sí) o el nombre visible en el perfil, que no puede serlo |
| **OB-3** | ¿Qué puede hacer una cuenta sin activar? ¿Recibe los +20 créditos? ¿Puede comentar? | **Seguridad.** Si una cuenta sin verificar puede comentar, el crédito por invitación (`FEAT-CRD-005`) se puede explotar con emails falsos |
| OB-4 | ¿Falta el botón «Saltar» en el paso 3 o basta con «Siguiente»? | `C-4` |
| OB-5 | ¿«Poeta» es «Poesía»? | Catálogo de géneros |
| OB-6 | ¿Cómo se ordenan las sugerencias de autores y cuántas se devuelven? ¿Qué se muestra si ningún autor cubre esos géneros? | `FEAT-COM-016` necesita un criterio concreto y un estado vacío |
| OB-7 | ¿Hay edad mínima para registrarse? Se pide la fecha de nacimiento pero no se dice para qué | **Legal.** En España el consentimiento digital del menor tiene un umbral de edad; si hay verificación de edad, la regla debe estar en el dominio |
| OB-8 | ¿Por qué un correo transaccional de activación lleva «Cancelar suscripción»? | Un usuario dado de baja no podría activar su cuenta |
| OB-9 | ¿Caduca el enlace de activación? ¿Cada cuánto se puede reenviar? | `FEAT-USR-020`, `FEAT-USR-021` |
| OB-10 | ¿El onboarding se puede abandonar y retomar? ¿Qué ocurre si el usuario cierra el navegador en el paso 2? | Define si el estado del onboarding se persiste paso a paso |
| OB-11 | ¿El usuario que entra por Google, Facebook o LinkedIn hace el mismo onboarding? ¿Se da por verificado su email? | Flujo alternativo completo sin diseñar |
| OB-12 | ¿A dónde se llega al terminar: Home o un segundo onboarding de perfil? | La nota del propio Figma lo deja abierto |
| OB-13 | ¿Cuál es el texto del botón de registro y el del paso 2, «Siguiente» o «Continuar»? | Coherencia de interfaz |
| OB-14 | ¿El catálogo de géneros es fijo o se administra? Los chips «Sample» sugieren que está sin cerrar | `FEAT-USR-023` |
