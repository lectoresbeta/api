<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Contract;

/**
 * Si esta persona admite que le propongan cosas (`FEAT-USR-011`).
 *
 * **Dos booleanos, nunca el ajuste**, como el resto de puertas de `User`.
 *
 * Y **un solo contrato para los dos**, a diferencia de `AuthorAudience` y
 * `MessageAudience`, que sí se separaron. La razón es la misma en los dos
 * casos: se separa lo que son puertas distintas. Aquellas lo eran —los textos
 * de un autor y su buzón— y estas no: las dos responden a «¿se le puede
 * abordar con una propuesta?», las pregunta el mismo contexto y en el mismo
 * momento del flujo. Partirlas en dos interfaces que `Reading` inyectaría
 * juntas sería ceremonia.
 *
 * **Recepción, no aviso.** Silenciar una notificación deja la invitación
 * esperando respuesta en algún sitio; responder `false` aquí impide que
 * llegue a existir.
 *
 * **El bloqueo vence al ajuste**, y corta en los dos sentidos: da igual quién
 * bloqueó a quién, porque lo que se decide es si estas dos personas se
 * proponen cosas. Por eso las dos preguntas llevan **las dos partes** y no
 * solo el destinatario, igual que `MessageAudience` — un ajuste abierto no
 * sirve de nada si además hay un bloqueo, y comprobarlo en quien pregunta
 * dejaría esa regla escrita en tantos sitios como puertas haya.
 *
 * Quien nunca lo ha tocado admite las dos. La ausencia de fila se lee como
 * los valores por defecto y no como «entonces todo vale».
 */
interface ProposalRecipients
{
    /**
     * Si admite que le inviten a leer una obra como lector beta
     * (`FEAT-RDG-004`).
     */
    public function acceptsBetaReaderInvitations(string $recipientId, string $proposerId): bool;

    /**
     * Si admite que le propongan ser writing buddy (`FEAT-RDG-008`).
     */
    public function acceptsWritingBuddyProposals(string $recipientId, string $proposerId): bool;

    /**
     * Lo mismo, **preguntado sobre uno mismo**: ¿tiene esta persona abierta
     * esa puerta, para quien sea? (`FEAT-COM-004`, `FEAT-COM-005`).
     *
     * Es un método aparte y no los de arriba con el mismo identificador dos
     * veces, porque esos responderían `false`: nadie se propone nada a sí
     * mismo, y esa comprobación es correcta para lo que preguntan. Aquí la
     * pregunta es otra — no «¿puede este abordar a aquel?» sino «¿está esta
     * puerta abierta?»— y la hace quien está a punto de dejar que alguien
     * publique que la busca.
     *
     * No lleva bloqueo, y no es un olvido: un bloqueo es contra una persona
     * concreta y aquí no hay segunda persona.
     *
     * Las dos puertas juntas y no una consulta por puerta: se guardan en la
     * misma fila y quien pregunta solo necesita una de ellas, pero cuál
     * depende de lo que se esté publicando.
     *
     * @return array{betaReaderInvitations: bool, writingBuddyProposals: bool}
     */
    public function openDoorsOf(string $userId): array;
}
