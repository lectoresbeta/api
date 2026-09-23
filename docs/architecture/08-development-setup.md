# Puesta en marcha del entorno de desarrollo

## Qué hace falta

| | Versión | Por qué |
|---|---|---|
| PHP | **8.3 o superior** | `composer.json` lo exige |
| Extensiones | `ctype`, `iconv`, `intl`, `pdo_pgsql`, `sodium`, **`amqp`** | `amqp` es la que más falta: sin ella `symfony/amqp-messenger` no instala |
| Composer | 2.x | |
| Docker | Cualquiera reciente | Solo para PostgreSQL, RabbitMQ y Mailpit |

PHP se ejecuta **en la máquina**, no en un contenedor. Docker levanta únicamente los
servicios. Es más rápido para desarrollar y evita el problema clásico de depurar dentro de un
contenedor.

## Arrancar

```bash
make install
```

Ese comando instala dependencias, levanta los servicios, genera las claves JWT y aplica las
migraciones. Cuando termina:

| Servicio | Dónde |
|---|---|
| PostgreSQL | `localhost:5432` |
| RabbitMQ | `localhost:5672`, consola en <http://localhost:15672> |
| **Mailpit** | <http://localhost:8025> |

**Mailpit importa más de lo que parece.** El primer corte de implementación es
registro → activación → créditos, y toda la activación pasa por un correo. Sin un buzón local,
probarla implicaría mandar correos de verdad a direcciones reales.

## Comandos

```bash
make check      # lo mismo que ejecuta CI: estilo, PHPStan, Deptrac y tests
make fix        # corrige el estilo
make test-unit  # solo dominio: sin Symfony, sin base de datos, en milisegundos
make consume    # consume los eventos de integración de RabbitMQ
make docs       # valida docs/
```

`make consume` hace falta en cuanto se prueba algo que cruce contextos: sin un consumidor
corriendo, `AccountActivated` se queda en la cola y los créditos de bienvenida no se abonan.
Es la primera confusión de cualquiera que arranque el proyecto.

## Secretos

`.env` lleva **valores de desarrollo**, nunca secretos reales. Lo que cambie cada uno va en
`.env.local`, que está en `.gitignore`.

Las claves JWT se generan con `make jwt-keys` y viven en `config/jwt/`, también ignorado.
**No se comparten entre entornos**: cada uno genera las suyas.

## Cómo se verifica la arquitectura

Las reglas de `AGENTS.md` no son un acuerdo de caballeros: se comprueban en cada ejecución.

| Comprobación | Qué impide |
|---|---|
| `deptrac.layers.yaml` | Que el dominio toque Symfony, Doctrine o infraestructura |
| `deptrac.contexts.yaml` | Que un bounded context conozca las clases internas de otro |
| `phpstan.neon` (nivel 7) | Tipos laxos y `mixed` gratuito |
| `.php-cs-fixer.dist.php` | Estilo divergente y `declare(strict_types=1)` olvidado |

Las dos primeras son las que más valor tienen **ahora mismo**, con el código aún por escribir:
cumplir estas reglas es trivial con cero clases e imposible de recuperar con doscientas.

Ambas corren con `--fail-on-uncovered`: una clase que no encaje en ninguna capa **rompe la
compilación**. Es deliberado — así, un fichero colocado en un sitio que no toca se detecta el
mismo día, no seis meses después.

## Decisiones del andamiaje que no son obvias

**El kernel vive en `src/Shared/Infrastructure/Symfony/Kernel.php`**, no en `src/Kernel.php`.
Dejarlo en la raíz lo colocaría por encima de los bounded contexts, como si el framework fuese
el centro del sistema. Symfony es infraestructura y se nota en dónde está su clase principal.

**El mapeo de Doctrine es XML, no atributos.** Un `#[ORM\Entity]` sobre un agregado es
exactamente la dependencia que `AGENTS.md` prohíbe. El mapeo vive en `Infrastructure`, junto a
la persistencia que describe.

**El dominio no se registra en el contenedor.** `config/services.yaml` autoregistra
`Application` e `Infrastructure` y **excluye `Domain`**: los agregados se construyen con `new`.
Un agregado que el contenedor sabe crear acaba teniendo dependencias de infraestructura.

**No hay baseline de PHPStan.** `AGENTS.md` lo dice explícitamente, y además un baseline
creado el primer día es un permiso indefinido para no arreglar nada.

**`framework.session: false`.** La sesión es JWT ([`decision:0007`](../decisions/0007-jwt-sessions.md)),
así que no hay estado de sesión en el servidor. Dejar la sesión de Symfony activada invitaría
a usarla sin querer.

## Lo que todavía no existe

- No hay entidades ni migraciones: `migrations/` está vacío.
- No hay controladores: las rutas de `openapi/` describen lo que habrá.
- No hay proveedor de correo elegido para producción (`N-2` de `FEAT-NOT-008`).
- No hay entorno de producción definido (`docs/architecture/07-observability-and-operations.md`).
