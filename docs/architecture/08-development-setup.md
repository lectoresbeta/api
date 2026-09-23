# Puesta en marcha del entorno de desarrollo

## Qué hace falta

| | Versión | Por qué |
|---|---|---|
| PHP | **8.4.1 o superior** | `composer.json` lo exige, y no es arbitrario: ver abajo |
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

Para que eso funcione, `deptrac.layers.yaml` tiene dos capas que no son nuestras: `Php`
(las clases del propio lenguaje, sin *namespace*) y `Vendor` (todo lo demás de terceros).
`Php` la puede usar cualquier capa; `Vendor` **solo `Infrastructure`**. Así, «el dominio no
depende de ninguna librería externa» deja de ser un buen propósito y pasa a ser algo que
falla en CI.

## Decisiones del andamiaje que no son obvias

**El kernel vive en `src/Shared/Infrastructure/Symfony/Kernel.php`**, no en `src/Kernel.php`.
Dejarlo en la raíz lo colocaría por encima de los bounded contexts, como si el framework fuese
el centro del sistema. Symfony es infraestructura y se nota en dónde está su clase principal.

**El mapeo de Doctrine es XML, no atributos.** Un `#[ORM\Entity]` sobre un agregado es
exactamente la dependencia que `AGENTS.md` prohíbe. El mapeo vive en `Infrastructure`, junto a
la persistencia que describe.

**El dominio no se registra en el contenedor.** `config/services.yaml` autoregistra `src/`
entero pero **excluye todos los `Domain/`**: los agregados se construyen con `new`. Un agregado
que el contenedor sabe crear acaba teniendo dependencias de infraestructura.

**No hay baseline de PHPStan.** `AGENTS.md` lo dice explícitamente, y además un baseline
creado el primer día es un permiso indefinido para no arreglar nada.

**`framework.session: false`.** La sesión es JWT ([`decision:0007`](../decisions/0007-jwt-sessions.md)),
así que no hay estado de sesión en el servidor. Dejar la sesión de Symfony activada invitaría
a usarla sin querer.

**PHP 8.4, no 8.3.** Doctrine ORM 3 sobre PHP 8.4 exige los objetos perezosos nativos del
lenguaje (`enable_native_lazy_objects`) en lugar de los *lazy ghosts* de `symfony/var-exporter`,
y varios componentes de Symfony del bloqueo ya piden `>=8.4.1`. La versión está fijada además
en `config.platform` de `composer.json`, para que quien resuelva dependencias en una máquina
con otra versión obtenga exactamente el mismo bloqueo.

**No hay Symfony Flex.** Se quitó a propósito. Flex configura cada paquete nuevo con la
*recipe* del esqueleto estándar: crea `src/Kernel.php`, `src/Controller/`, `src/Entity/`,
`src/Repository/` y reescribe `.env`, `.gitignore` y `docker-compose.yaml`. Todo eso es
justamente la estructura que este proyecto **no** tiene. Sin Flex, `config/bundles.php` se
mantiene a mano —son cuatro líneas— y nada reorganiza el árbol por sorpresa.

**Los controladores llevan `#[AsController]`.** No se registran aparte en
`config/services.yaml`: el atributo de Symfony ya les pone la etiqueta
`controller.service_arguments`. El atributo se queda donde debe, en `Infrastructure`.

**Los eventos de integración viajan en JSON**, no en la serialización nativa de PHP
(`serializer: messenger.transport.symfony_serializer`). Un evento de integración es un contrato
público entre contextos: si su formato en el cable depende del nombre de una clase y de sus
propiedades privadas, renombrar una clase rompe a los consumidores.

## Lo que todavía no existe

- No hay entidades ni migraciones: `migrations/` está vacío.
- No hay controladores: las rutas de `openapi/` describen lo que habrá.
- El proveedor de usuarios de Symfony Security es un `memory: ~` provisional. Se sustituye por
  `LectoresBeta\User\Authentication\Infrastructure\Security\UserProvider` cuando exista
  `FEAT-USR-001`. Hasta entonces el contenedor arranca, pero nadie puede autenticarse.
- No hay proveedor de correo elegido para producción (`N-2` de `FEAT-NOT-008`).
- No hay entorno de producción definido (`docs/architecture/07-observability-and-operations.md`).
