# Puesta en marcha del entorno de desarrollo

## Qué hace falta

**Docker y nada más.** La aplicación se ejecuta dentro de un contenedor, así que no hace
falta PHP en la máquina ni acertar con las extensiones.

| | Versión |
|---|---|
| Docker | Cualquiera reciente, con `docker compose` |
| GNU Make | El que traiga el sistema |

Si prefieres ejecutar PHP en la máquina —es más cómodo para depurar—, necesitas **PHP 8.4.1 o
superior** con `amqp`, `ctype`, `iconv`, `intl`, `pdo_pgsql` y `sodium`, y Composer 2.x. Todos
los comandos de calidad aceptan salirse del contenedor vaciando `EXEC`:

```bash
make stan EXEC=      # ejecuta vendor/bin/phpstan en la máquina
```

`amqp` es la extensión que más falta: sin ella `symfony/amqp-messenger` ni siquiera instala.

## Arrancar

```bash
make build && make up
```

`make up` levanta los servicios, espera a que la aplicación responda, genera las claves JWT y
aplica las migraciones. Cuando termina:

| Servicio | Dónde |
|---|---|
| **API** | <http://localhost:8000> |
| **Mailpit** | <http://localhost:8025> |
| RabbitMQ | `localhost:5672`, consola en <http://localhost:15672> |
| PostgreSQL | `localhost:5432` |

Las credenciales de desarrollo son `lectoresbeta` / `lectoresbeta` en todo.

**Mailpit importa más de lo que parece.** El primer corte de implementación es
registro → activación → créditos, y toda la activación pasa por un correo. Sin un buzón local,
probarla implicaría mandar correos de verdad a direcciones reales.

## Qué levanta `make up`

| Servicio | Qué es |
|---|---|
| `deps` | Instala las dependencias y termina. Los demás esperan a que acabe |
| `app` | PHP-FPM con el código montado por volumen: editar un fichero se ve en la siguiente petición |
| `web` | nginx, que sirve `public/` y habla con `app` |
| `worker` | `messenger:consume integration`, en marcha permanente |
| `postgres`, `rabbitmq`, `mailpit` | Los servicios |

**El `worker` va aparte a propósito.** Sin un consumidor corriendo, `AccountActivated` se
queda en la cola y los créditos de bienvenida no se abonan nunca. Es la primera confusión de
cualquiera que arranque el proyecto, y por eso el worker se levanta solo con `make up` en vez
de dejarlo a que alguien se acuerde.

**Y `deps` también va aparte, por un motivo que costó un rato encontrar.** Instalar las
dependencias desde el *entrypoint* parecía lo natural, pero `app` y `worker` montan el mismo
`vendor/` y arrancan a la vez: los dos lanzaban `composer install` sobre el mismo directorio
y el resultado era un autoloader que apuntaba a paquetes que todavía no estaban. Un servicio
de un solo uso, del que los demás dependen con `service_completed_successfully`, lo hace una
vez y en un orden garantizado.

Es idempotente: si `vendor/` está al día no hace nada, así que levantar el entorno por segunda
vez es instantáneo. Si alguna vez `vendor/` queda en mal estado, `make deps-reset` lo borra y
lo reinstala desde cero.

## Los ficheros que escribe el contenedor son tuyos

El contenedor escribe en el mismo directorio en el que trabajas: `vendor/`, `var/` y lo que
corrija `php-cs-fixer`. Si los escribiera `root` —que es lo que hacen las imágenes de PHP por
defecto—, en Linux acabarías con un checkout que no puedes borrar sin `sudo`.

El Makefile le pasa tu UID a la imagen y `www-data` pasa a ser tú dentro del contenedor. Los
comandos de calidad se ejecutan con ese usuario. Si alguna vez hace falta root —instalar algo,
mirar permisos—, `make sh-root`.

## Comandos

```bash
make help            # la lista entera

make up / down       # levantar y parar
make destroy         # parar y BORRAR los volúmenes, base de datos incluida
make logs            # seguir los registros
make sh              # una shell dentro del contenedor
make console CMD="debug:router"

make deps            # instalar o actualizar dependencias
make deps-reset      # borrar vendor/ y reinstalar desde cero

make migrate         # aplicar migraciones pendientes
make migration       # generar una a partir del mapeo
make schema-validate # ¿el mapeo y el esquema dicen lo mismo?
make db-reset        # rehacer la base de datos desde cero
make psql            # psql contra la base de datos de desarrollo

make check           # lo que ejecuta CI: estilo, PHPStan, Deptrac, tests y docs
make fix             # corregir el estilo
make test-unit       # solo dominio: sin Symfony, sin base de datos, en milisegundos
make docs            # validar docs/
```

Y los del proyecto, que se lanzan a mano o desde un programador:

```bash
bin/console lectoresbeta:admin:grant <correo>                      # ver «El primer administrador»
bin/console lectoresbeta:user:purge-expired-username-aliases       # --dry-run para mirar antes
bin/console credits:check-invariant                                # ¿cuadra la economía?
bin/console lectoresbeta:credits:grant-overdrafts                  # el cupo de la semana; --dry-run
```

`make schema-validate` merece un sitio en la cabeza: si alguna vez responde que el esquema y
el mapeo no coinciden, o falta una migración, o alguien ha tocado la base de datos a mano.

## Variables de entorno

Las que el código exige. `.env` trae un valor de desarrollo para cada una.

| Variable | Qué es |
|---|---|
| `DATABASE_URL` | PostgreSQL |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ. En tests, `in-memory://` |
| `MAILER_DSN` | Proveedor de correo. Sin decidir (`FEAT-NOT-008` `N-3`) |
| `MAILER_SENDER` | Remitente de los correos transaccionales |
| `GOOGLE_OAUTH_CLIENT_ID` | Entrar con Google (`FEAT-USR-002`). **Vacía en `.env`**: en desarrollo va en `.env.local`, que no se versiona; en producción, en el entorno |
| `GOOGLE_OAUTH_CLIENT_SECRET` | Lo mismo, y es un secreto: **no se commitea nunca** |
| `GOOGLE_OAUTH_REDIRECT_URI` | La página **del frontend** que recoge el código. Tiene que estar declarada igual en la consola de Google: el proveedor exige que sea la misma en los dos pasos |
| `ACTIVATION_URL_TEMPLATE` | Página **del frontend** que recoge el token de activación. `{token}` se sustituye al generar el correo |
| `PASSWORD_RESET_URL_TEMPLATE` | Lo mismo para el enlace de «he olvidado mi contraseña» (`FEAT-USR-007`) |
| `EMAIL_CHANGE_URL_TEMPLATE` | Lo mismo para confirmar un cambio de correo (`FEAT-USR-040`) |
| `INVITATION_URL_TEMPLATE` | Lo mismo para el enlace de invitación a la plataforma (`FEAT-USR-018`, `FEAT-NOT-007`). La página de alta extrae el token y lo manda **en el cuerpo**, para que no quede en los logs ni en el historial |
| `WORK_SHARE_URL_TEMPLATE` | La página de una obra en el frontend (`FEAT-WRK-011`). **Sin token**: es la dirección canónica, y solo se sirve tarjeta de lo que ya es público |
| `POST_SHARE_URL_TEMPLATE` | Lo mismo para una publicación (`FEAT-COM-020`) |
| `APP_STORAGE_DIR` | Dónde se guardan los ficheros subidos (`FEAT-USR-037`) |
| `MODERATION_APPEALS_EMAIL` | Dirección a la que el autor recurre un bloqueo por reclamación (`FEAT-MOD-003` `RN-8b`). Va en el correo de bloqueo, que es la única vía por la que se entera |

Las dos plantillas de URL apuntan al frontend y no a la API a propósito: el enlace abre una
página que extrae el token y lo envía en el cuerpo de la petición, de modo que no quede en los
logs del servidor ni en el historial del navegador.

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

**Del dominio solo se registra lo que no tiene estado.** `config/services.yaml` autoregistra
`src/` entero y excluye `Domain/{Entity,ValueObject,Enum,Exception,Event}`: los agregados se
construyen con `new`, porque uno que el contenedor sabe crear acaba teniendo dependencias de
infraestructura.

Lo que sí se registra son los **contratos de repositorio** y los servicios de dominio sin
estado. Registrar la interfaz junto a su implementación es además lo que hace que Symfony las
enlace sola, sin una lista de alias que mantener a mano.

**Los repositorios nunca hacen `flush()`.** Registran el cambio y nada más; quien decide
cuándo se cierra una transacción es el caso de uso, a través del puerto
`Shared\Domain\Persistence\TransactionalSession`. Importa sobre todo en `Credits`: anotar
que un evento se ha procesado y aplicar el movimiento que provoca tienen que confirmarse
juntos, o una caída entre ambos pierde el movimiento o deja aplicar el evento dos veces.

**Un esquema de PostgreSQL por contexto**
([`decision:0009`](../decisions/0009-one-postgresql-schema-per-bounded-context.md)), sin
claves foráneas entre ellos. El esquema hace que un `JOIN` entre contextos deje de escribirse
sin querer, y los índices únicos parciales son la única garantía real de varias reglas bajo
concurrencia. El ADR explica los detalles que parecen errores y no lo son: por qué la tabla
de cuentas se llama `account`, y por qué los predicados de esos índices están escritos con la
grafía exacta de PostgreSQL.

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

**Los controladores llevan `#[AsController]`, y nada más.** Ese atributo es configuración de
servicio —le pone la etiqueta `controller.service_arguments`— y por eso no hay que
registrarlos aparte en `config/services.yaml`.

**Las rutas, en cambio, no van en el controlador.** Se declaran en YAML, un fichero por
bounded context y dentro del propio contexto, en `src/<Contexto>/Infrastructure/routes.yaml`
([`decision:0010`](../decisions/0010-routes-declared-in-yaml-per-context.md) y
[`decision:0011`](../decisions/0011-route-files-live-inside-their-context.md)). El nombre de
cada ruta es su `operationId` de OpenAPI, y hay un test que comprueba que ninguna se queda
sin documentar.

**Los eventos de integración viajan en JSON**, no en la serialización nativa de PHP
(`serializer: messenger.transport.symfony_serializer`). Un evento de integración es un contrato
público entre contextos: si su formato en el cable depende del nombre de una clase y de sus
propiedades privadas, renombrar una clase rompe a los consumidores.

## Lo que todavía no existe

La primera rodaja vertical **ya funciona**: alta, correo de activación, activación, abono de
los créditos de bienvenida, inicio de sesión y consulta de saldo. Lo que falta:

- **La mayor parte del producto.** Hay persistencia para 66 tablas y casos de uso para seis
  funcionalidades. `Work`, `Reading`, `Feedback`, `Community` y `Moderation` no tienen todavía
  ningún caso de uso.
- **Las escrituras no comprueban el estado de la cuenta** (`FEAT-USR-025`). Una cuenta sin
  activar podría escribir en cuanto exista algo donde escribir, así que esto va **antes** que
  el primer endpoint de escritura y no después.
- No hay proveedor de correo elegido para producción (`N-3` de `FEAT-NOT-008`).
- Entrar con Google funciona, pero **sin credenciales configuradas no hace nada**: hay que
  crear el cliente OAuth en la consola de Google Cloud y poner las tres variables. Vacías, el
  botón falla y el resto de la aplicación sigue igual.
- Cambiar contraseña o correo todavía no invalida los tokens de refresco
  (`decision:0007` `RN-3`): es trabajo de `FEAT-USR-041` y `FEAT-USR-040`.
- Nadie purga los alias caducados en un horario: el comando existe
  (`lectoresbeta:user:purge-expired-username-aliases`), la programación no (`FEAT-USR-036`
  `N-15`). Es inocuo — un alias caducado ya no resuelve ni ocupa su nombre, lo borre alguien
  o no; solo se acumulan filas.
- De `processed_event` no purga nadie, y ahí no hay comando todavía.
- El cupo de descubiertos (`FEAT-CRD-019`) tampoco tiene programación: el comando existe
  (`lectoresbeta:credits:grant-overdrafts`) y nadie lo llama. Nace con cupo 3 por semana y
  `app.overdraft.weekly_quota: 0` lo apaga entero.
- No hay entorno de producción definido (`docs/architecture/07-observability-and-operations.md`).

## El primer administrador

El backoffice no se abre solo. Quien vaya a administrar **se registra como todo el mundo** y
activa su cuenta; después, desde el servidor:

```bash
bin/console lectoresbeta:admin:grant tu@correo.com
```

Y `--revoke` para quitarlo, que es la salida si el único administrador pierde el acceso.

**No hay ninguna otra vía desde la API** (`FEAT-MOD-012` `RN-6`), y eso es lo que hace que el
registro de auditoría signifique algo: si existiera un endpoint para autoconcederse el rol,
cualquier fallo de autorización sería catastrófico.

Tampoco hay una semilla, a propósito: una semilla crea el administrador en todos los entornos
por igual y con credenciales conocidas, que es el origen clásico del `admin/admin` que
sobrevive hasta producción.
