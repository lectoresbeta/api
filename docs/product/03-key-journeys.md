# Recorridos principales

> Estado: `DRAFT` — se completará y corregirá con las páginas de Figma.

Estos recorridos describen el producto de extremo a extremo y sirven para detectar
funcionalidades que faltan. Cada paso enlaza (o enlazará) con su ficha en
[`../features/`](../features/).

---

## J-1. Un escritor consigue feedback de su relato

1. Se registra y completa el onboarding → recibe **+10 créditos** de bienvenida
   (`FEAT-CRD-002`). Recorrido detallado en `J-0`.
2. Crea la obra con el editor o subiendo un fichero (`FEAT-WRK-001`, `FEAT-WRK-002`).
3. El sistema clasifica el texto por extensión y calcula su nivel (`FEAT-WRK-013`).
4. Define el cuestionario: tres preguntas sin coste, las adicionales encarecen cada
   comentario recibido (`FEAT-WRK-014`, `FEAT-CRD-007`).
5. Configura la modalidad de acceso LB y la visibilidad (`FEAT-WRK-007`, `FEAT-WRK-008`).
6. Al crear la obra se genera el registro de autoría (`FEAT-WRK-009`).
7. Promociona la obra: publicación en el muro (`FEAT-COM-003`) o enlace para redes
   sociales (`FEAT-WRK-011`).
8. **Abre la obra a corrección** (`FEAT-WRK-016`). Es la puerta que cuesta dinero, y la abre
   a conciencia. Mientras su saldo cubra el precio de un capítulo, ese capítulo admite
   correcciones (`FEAT-CRD-009`); cuando deja de cubrirlo, desaparece de la vista sin que él
   tenga que hacer nada.
9. Los lectores beta corrigen. **Cada corrección entregada le carga los créditos**
   (`FEAT-CRD-006`) y abona lo mismo a quien la escribió.
10. Lee, contesta y valora las correcciones (`FEAT-FBK-004`, `FEAT-FBK-005`, `FEAT-FBK-006`).
11. Si una le resulta especialmente útil, puede **propinar** créditos de los suyos
    (`FEAT-CRD-017`).

**Punto crítico del recorrido:** el paso 9, no el 8. **No se retiene nada al abrir la obra**:
el compromiso económico se adquiere al recibir la corrección
([`decision:0006`](../decisions/0006-credit-system.md)). La consecuencia aceptada es que dos
lectores pueden coincidir sobre un saldo que cubre a uno, y entonces el autor queda en
negativo y una corrección le llega bloqueada hasta que reponga. A cambio, nadie ve dos cifras
de saldo ni pierde trabajo por una reserva caducada.

---

## J-0. Alta de una persona nueva

Recorrido completamente especificado a partir del diseño:
[`../ui/account-creation.md`](../ui/account-creation.md).

1. Rellena el formulario con email y contraseña, y acepta condiciones y privacidad
   (`FEAT-USR-001`, `FEAT-USR-024`). Alternativamente entra con **Google** (`FEAT-USR-002`),
   que exige la misma aceptación legal; Facebook y LinkedIn están diferidos.
2. La cuenta se crea en `PENDING_ACTIVATION`, **sin créditos y sin poder escribir**, y se le
   envía el correo de activación (`FEAT-NOT-008`).
3. **Sin esperar a ese correo**, entra en el onboarding.
4. Paso 1: nombre —**público**, y el referente con el que se le identificará en toda la
   plataforma— y fecha de nacimiento, que es privada (`FEAT-USR-022`).
5. Paso 2: elige al menos tres géneros de interés (`FEAT-USR-023`).
6. Paso 3, opcional: sigue a autores sugeridos según esos géneros (`FEAT-COM-016`). **Si la
   plataforma no tiene autores suficientes, este paso se omite** y el onboarding termina en
   dos pasos.
7. Llega al Home, en modo vacío si no sigue a nadie.
8. Activa la cuenta desde el correo (`FEAT-USR-020`), o pide que se lo reenvíen
   (`FEAT-USR-021`). **Hasta ese momento no tiene créditos ni puede escribir nada.**
9. Al activar se abonan los **+10 créditos** y se desbloquea la plataforma entera.

**Punto crítico:** los pasos 3 a 7 y el paso 8 transcurren en paralelo. La activación es la
frontera real del producto: sin ella el usuario ve la plataforma pero no participa en ella.
Ver [`decision:0003`](../decisions/0003-write-operations-require-activated-account.md).

---

## J-2. Un usuario gana créditos dando feedback

1. Busca obras por tipo, temática o valoración (`FEAT-WRK-012`), o responde a una
   publicación de búsqueda de LB en el muro (`FEAT-COM-003`).
2. Lee la obra (`FEAT-WRK-004`). Si es `PUBLIC` no hace falta nada más; si es `ON_REQUEST` o
   `PRIVATE`, necesita acceso concedido (`FEAT-RDG-002`, `FEAT-RDG-005`).
3. Pulsa «Empezar corrección». En una obra `PUBLIC`, **ese acto lo convierte en lector beta**
   (`FEAT-RDG-001`): no hay solicitud ni espera.
4. Responde el cuestionario y lo envía (`FEAT-FBK-003`).
5. Recibe créditos: **lo mismo que se le carga al autor**, calculado sobre las palabras del
   capítulo y las que exige el cuestionario (`FEAT-CRD-006`, `FEAT-CRD-016`).
6. Si el autor quiere agradecérselo, puede **propinarle** créditos de los suyos
   (`FEAT-CRD-017`).
7. Su actividad lo posiciona en el ranking de lectores (`FEAT-COM-015`).

**Nada queda retenido en ningún momento.** Lo que garantiza que su trabajo se pague no es una
reserva previa sino que **el corrector cobra siempre**, aunque el autor se quede en negativo
([`decision:0006`](../decisions/0006-credit-system.md)).

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
2. La persona invitada se registra usando el enlace (`FEAT-USR-001`) y, **al activar su
   cuenta**, recibe sus +10 créditos de bienvenida.
3. Cuando la persona invitada **deja su primer comentario**, el invitador recibe
   **+5 créditos** (`FEAT-CRD-005`).

El crédito se otorga por participación efectiva, no por registro: es una protección
deliberada contra el registro masivo de cuentas falsas. La segunda protección es que sin
activar la cuenta no se puede comentar, así que la cadena entera exige un correo real.

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
| J-8 | Un autor se queda sin saldo disponible y no puede admitir más lectores beta | Parcialmente resuelto por `decision:0004`; falta el diseño del aviso (`R-6`) |
| J-9 | Moderación de contenido o comentarios abusivos | Decisión de producto (`V-1`) |
| J-10 | Publicación de una obra en varios fragmentos con lecturas parciales | Figma |
