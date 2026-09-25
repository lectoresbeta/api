<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Contract;

/**
 * Si alguien quiere recibir ese aviso por ese canal
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Lo pregunta `Notification` **en el momento de entregar** y no quien publica
 * el hecho (`FEAT-USR-039` `RN-6`). Que `Feedback` tuviera que saber si el
 * autor quiere correos sería acoplar el emisor a una preferencia que no es
 * suya, y además a una que puede haber cambiado entre el hecho y la entrega.
 *
 * **Un booleano y nada más.** No devuelve la configuración de nadie: quien
 * pregunta está decidiendo si manda un aviso, y cualquier campo de más sería
 * un ajuste de una persona cruzando una frontera sin motivo.
 *
 * **No sabe de mensajes operativos.** La activación, el restablecimiento de
 * contraseña y los avisos de seguridad no se preguntan aquí porque no son
 * notificaciones (`RN-3`): quien entrega los distingue por su propio
 * catálogo, antes de llegar a esto. Si se preguntaran, el interruptor general
 * dejaría a alguien sin poder recuperar su cuenta.
 */
interface NotificationChoices
{
    public function allows(string $userId, string $topic, string $channel): bool;
}
