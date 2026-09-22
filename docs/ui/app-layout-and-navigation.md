---
screen: Layout general y navegación
figma: https://www.figma.com/design/egiA5ltTOBRXqwerrqC1jW/WebApp_LectoresBeta?node-id=1800-14717
features: [FEAT-USR-014, FEAT-CRD-001, FEAT-CRD-014, FEAT-NOT-009, FEAT-COM-025]
actors: [User]
updated: 2026-09-22
---

# Layout general y navegación

Estructura común a todas las pantallas de la aplicación autenticada. Se documenta aparte
porque condiciona qué datos necesita **cualquier** página, no solo la Home.

> ## ⚠ Hay una segunda iteración del layout
>
> Las capturas de [post-interactions.md](post-interactions.md) muestran un layout distinto
> del que describe este documento: menú lateral **estrecho** con icono sobre etiqueta,
> **«Avisos» en el menú** en lugar de la campana de la cabecera, **créditos y buscador en la
> cabecera**, y el nombre del usuario junto al avatar.
>
> **No consta cuál de las dos es la vigente** (`I-6`), así que este documento conserva la
> primera y registra la segunda sin reescribirse.
>
> Lo que **no** cambia con ninguna de las dos es lo importante para el backend: los mismos
> cinco datos por carga y el mismo `GET /me/context` (`FEAT-USR-027`). Solo cambia dónde se
> pintan.
>
> La segunda iteración **resuelve `L-1`** a favor de la nota de diseño: «Avisos» va en el
> menú lateral.

## Anatomía

```text
┌────────────┬──────────────────────────────────────────────────────┐
│            │                          🔍  🔔•  (avatar)  ← header │
│  LOGO      ├──────────────────────────────────────────────────────┤
│            │                                                      │
│  Inicio    │                                                      │
│  Leer      │                    CONTENIDO                         │
│  Escribir  │                   (ancho ~731)                       │
│  Mensajes  │                                                      │
│  Recursos  │                                                      │
│  Ayuda     │                                                      │
│            │                                                      │
│ ┌────────┐ │                                                      │
│ │12 Créd.│ │                                                      │
│ │      ⓘ │ │                                                      │
│ └────────┘ │                                                      │
│ Terms & C. │                                                      │
└────────────┴──────────────────────────────────────────────────────┘
   241 px                        resto (1440 total)
```

**Sin footer.** La nota de diseño lo dice de forma explícita: *«SIN FOOTER»*.

## Menú lateral

Ancho fijo de 241 px, siempre visible.

| Sección | Destino | Estado |
|---|---|---|
| **Inicio** | Home: recomendaciones y muro | [home.md](home.md) |
| **Leer** | Catálogo de obras para leer y comentar | Sin diseñar |
| **Escribir** | Gestión de obras propias | Sin diseñar |
| **Mensajes** | Mensajes directos | `FEAT-COM-011`, `FEAT-COM-012` |
| **Recursos** | Blog de la plataforma | **Nuevo.** Ver `L-4` |
| **Ayuda** | Soporte | **Nuevo.** Ver `L-4` |

El elemento activo se resalta. En las capturas, «Inicio» aparece seleccionado.

> **Contradicción.** La nota de diseño enumera el menú lateral como *«"Leer" "Escribir"
> "Mensajes" "Avisos (Notificaciones)" "Recursos (Blog)" "Ayuda"»*, pero el diseño **no
> incluye «Avisos»**: las notificaciones están en la campana de la cabecera. Ver `L-1`.

### Bloque de créditos

Al pie del menú, sobre el enlace legal:

| Elemento | Detalle |
|---|---|
| Icono | Cartera |
| Texto | «12 Créditos» — el saldo real del usuario (`FEAT-CRD-001`) |
| Icono ⓘ | Abre el modal informativo del sistema de créditos (`FEAT-CRD-014`) |

El saldo está presente en **todas** las pantallas, así que debe poder obtenerse de forma
barata y mantenerse fresco tras una operación que lo cambie.

> El «12» del diseño es dato de maqueta. Una cuenta recién activada tiene **20**
> (`FEAT-CRD-002`), y una sin activar tiene **0** (`FEAT-USR-025`).
>
> **El número que se muestra es el saldo disponible**, no el total: es el que determina si el
> autor puede admitir más lectores beta. Con retenciones vigentes ambos difieren, y el diseño
> no contempla todavía cómo explicarlo (`R-5`).

### Enlace legal

«Terms & Conditions» al pie. Es el único texto de la interfaz en inglés: conviene unificarlo
al castellano, «Términos y condiciones», por coherencia con el resto (`L-5`).

Enlaza a los mismos documentos que se aceptan al registrarse (`FEAT-USR-024`).

## Cabecera

Alineada a la derecha, tres elementos:

| Elemento | Función | Estado |
|---|---|---|
| 🔍 Lupa | Búsqueda global | **Backlog** según la nota de diseño (`FEAT-COM-025`) |
| 🔔 Campana | Notificaciones, con punto indicador de no leídas | `FEAT-NOT-009` |
| Avatar | Menú desplegable de usuario | Ver abajo |

El punto sobre la campana (`Badge/Dot-On-Icon`) implica un contador de notificaciones sin
leer disponible en cada carga de página.

### Menú desplegable del avatar

Según la nota de diseño:

| Opción | Destino |
|---|---|
| Mi perfil | Perfil público propio (`FEAT-USR-014`) |
| Configurar cuenta | Ajustes (`FEAT-USR-008` a `FEAT-USR-013`) |
| Cerrar sesión | Fin de sesión (`FEAT-USR-004`) |

## Qué necesita el backend en cada carga

Estos datos los consume el layout, no la página concreta. Conviene resolverlos en una única
llamada de contexto de sesión en lugar de tres peticiones por navegación:

| Dato | Para qué | Funcionalidad |
|---|---|---|
| Nombre y avatar | Cabecera y saludo | `FEAT-USR-022` |
| Saldo de créditos | Bloque del menú lateral | `FEAT-CRD-001` |
| Créditos retenidos | Explicar por qué el disponible es menor | `FEAT-CRD-009` |
| Notificaciones sin leer | Punto de la campana | `FEAT-NOT-009` |
| Estado de la cuenta | Avisar si está sin activar (`FEAT-USR-025`) | `FEAT-USR-020` |
| Estado del tour | Mostrarlo o no (`FEAT-USR-026`) | `FEAT-USR-026` |

**Decidido:** se resuelven en una única llamada, `GET /me/context`, especificada en
[`FEAT-USR-027`](../features/user/FEAT-USR-027-session-context.md).

Compone los datos de cuatro bounded contexts mediante contratos de consulta explícitos, sin
que ninguno conozca a los demás, y **degrada en lugar de fallar**: si `Credits` no responde,
el resto del layout se pinta igual.

El saldo se devuelve con sus **tres** números —total, retenido y disponible— porque desde
[`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md) no son lo mismo.

## Estado sin activar

El layout es el sitio natural para avisar de que la cuenta está en `PENDING_ACTIVATION`:
el saldo será 0 y cualquier acción de escritura fallará con `ACCOUNT_NOT_ACTIVATED`.

El diseño **no contempla ese estado todavía** (`A-2` de `FEAT-USR-025`). Es un hueco real:
un usuario sin activar vería la interfaz completa y recibiría errores al usarla.

## Responsive

Todas las capturas son de 1440 px. **No hay diseño móvil ni tablet** de este layout. Cómo se
comporta el menú lateral por debajo de cierto ancho está sin definir (`L-3`).

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| L-1 | ¿«Avisos» va en el menú lateral o en la cabecera? | **Resuelta en la segunda iteración:** en el menú lateral, como decía la nota de diseño |
| **I-6** | **¿Cuál de las dos iteraciones del layout es la vigente?** | Afecta a la navegación entera, aunque no al contrato de `GET /me/context` |
| L-2 | ¿Qué datos exactos devuelve el contexto de sesión y cada cuánto se refresca el saldo? | Rendimiento; el saldo cambia por eventos asíncronos |
| L-3 | ¿Cómo se comporta el layout en móvil? | Sin diseño |
| L-4 | ¿«Recursos» y «Ayuda» son contenido de la plataforma o enlaces externos? | Si son internos, hacen falta funcionalidades de backend que hoy no existen |
| L-5 | ¿«Terms & Conditions» se traduce? | Es el único texto en inglés de la interfaz |
| L-6 | ¿Hay estado de carga o de error para el saldo si el servicio de créditos no responde? | El saldo llega de un contexto desacoplado y puede fallar de forma independiente |
| R-5 | ¿Cómo se muestra la diferencia entre saldo total y disponible? | Un autor con créditos retenidos verá menos de lo que tiene y hay que explicárselo |
