# 0005 — El nombre de usuario existe, es editable y deja un alias temporal al cambiarlo

- **Estado:** Aceptada
- **Fecha:** 2026-09-22
- **Afecta a:** `User`, `Community`, y toda URL pública de perfil
- **Resuelve:** `P-1`
- **Sustituye a:** la decisión de 2026-09-21 de no usar nombre de usuario

## Contexto

El 21 de septiembre se decidió que la plataforma **no manejaría nombre de usuario**: el
registro no lo pedía y el saludo del onboarding usaba un alias derivado del email, sin
almacenarlo.

El diseño de «Mi perfil» mostró después un identificador con arroba, `@bealonso`, que es la
forma canónica de un nombre de usuario: corto, único y pensado para compartirse. Quedó
registrado como `P-1`, contradicción bloqueante.

Se resuelve a favor del diseño: **el nombre de usuario existe**.

Con él llega un problema que no existía antes. Si el nombre de usuario forma la URL pública
del perfil —`lectoresbeta.com/profile/pabloblanco1`— cambiarlo rompe todos los enlaces que
alguien haya compartido. Y si el nombre queda libre de inmediato, otro usuario puede
ocuparlo y **heredar el tráfico dirigido a una persona distinta**, que es un problema de
suplantación, no solo de enlaces rotos.

## Decisión

### El nombre de usuario

1. Toda cuenta tiene un `username` **único**.
2. Al registrarse se asigna automáticamente a partir de **la parte del email anterior a la
   `@`**, normalizada.
3. Si ese nombre está ocupado, se añade `_` y un número hasta encontrar uno libre:
   `pabloblanco`, `pabloblanco_1`, `pabloblanco_2`…
4. El usuario puede cambiarlo cuando quiera, siempre que el nuevo esté libre.

### El cambio y su alias

5. Solo se permite **un cambio cada 30 días**.
6. Al cambiarlo, el nombre anterior **no queda libre**: se convierte en un **alias** de esa
   cuenta, con **30 días de caducidad**.
7. Durante ese mes el alias sigue resolviendo al perfil, de modo que los enlaces compartidos
   siguen funcionando.
8. Un alias vigente **ocupa el nombre**: nadie más puede registrarlo.
9. Un alias caducado no resuelve y no ocupa nada, **aunque todavía no se haya borrado**.

### La resolución

10. Un perfil se busca primero por `username`. Si no aparece, se busca entre los **alias
    vigentes**.
11. La respuesta indica siempre el nombre canónico actual, para que el cliente pueda
    corregir la URL.

### La limpieza

12. Un **comando de consola** borra los alias caducados, programado para ejecutarse **a
    diario**. Es housekeeping: libera filas, no libera nombres, porque eso ya lo hace la
    caducidad.

## Por qué los dos plazos coinciden

El límite de cambio y la caducidad del alias son ambos de 30 días, y no por casualidad: hacen
que un usuario tenga **como máximo un alias vigente**. Cuando puede volver a cambiar de
nombre, el alias anterior ya ha caducado.

No es una invariante que el modelo deba asumir —basta con que los plazos se desacoplen para
que deje de cumplirse—, así que `username_alias` admite varias filas por usuario. Pero
conviene saber que **si alguien cambia uno de los dos plazos, los usuarios empezarán a
acumular alias**, y con ellos nombres bloqueados.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Sin nombre de usuario (la decisión anterior) | Nada que validar, reservar ni cambiar | El diseño lo muestra y las URLs de perfil lo necesitan | El diseño es posterior y más concreto |
| Nombre de usuario inmutable | Los enlaces nunca se rompen | Un nombre elegido mal, o generado del email, acompaña para siempre | Demasiado rígido para un identificador que además se genera solo |
| Cambio libre, nombre liberado al instante | Simple | Rompe enlaces y permite heredar el tráfico de otra persona | **Suplantación**: el problema real, no los enlaces |
| URL de perfil por `UserId` | Inmutable y sin colisiones | Un UUID no se comparte ni se recuerda | El perfil es material de promoción del autor |
| Alias permanentes | Ningún enlace se rompe jamás | Cada cambio bloquea un nombre para siempre | El espacio de nombres se agota con el uso |

## Consecuencias

**Positivas**

- Los enlaces compartidos sobreviven un mes a un cambio de nombre.
- Nadie puede ocupar el nombre recién liberado por otro y quedarse con su tráfico.
- El espacio de nombres se recicla: los alias caducan.
- El nombre por defecto sale del email, así que nadie tiene que elegirlo para empezar.

**Negativas**

- Aparece un agregado nuevo con caducidad, y con él un proceso programado. La plataforma pasa
  a tener trabajo de fondo que alguien debe vigilar.
- La unicidad se comprueba contra **dos** tablas, y una de ellas con condición temporal. Es el
  punto donde es fácil equivocarse.
- Un alias caducado sigue ocupando fila hasta que pase el comando. La comprobación de
  disponibilidad **no puede depender de que el comando haya corrido**.
- La generación del nombre por defecto depende del email, que puede contener caracteres no
  admitidos o quedarse demasiado corto al normalizarlo.
- Dos peticiones simultáneas pueden intentar el mismo nombre. La unicidad tiene que estar
  garantizada por la base de datos, no solo por una comprobación previa.

**Coste de revertirla**

Alto una vez haya URLs públicas circulando. Quitar el nombre de usuario significaría romper
todos los enlaces de perfil existentes.

## Cumplimiento

- Restricción de unicidad en base de datos sobre el nombre normalizado, tanto en `user` como
  en `username_alias`. La comprobación previa es una comodidad; la garantía es la restricción.
- Test: un nombre cuyo alias ha caducado está disponible **aunque la fila siga existiendo**.
- Test: un nombre cuyo alias sigue vigente no está disponible.
- Test: dos altas simultáneas con el mismo email local no producen nombres duplicados.
- Test: cambiar de nombre dos veces en 30 días se rechaza.
- El comando de purga es idempotente y se puede ejecutar varias veces sin efecto adicional.
