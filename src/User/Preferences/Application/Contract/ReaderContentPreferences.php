<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Contract;

/**
 * Qué ha decidido no ver esta persona (`FEAT-USR-043` `RN-2`).
 *
 * El filtrado ocurre **en el servidor**, así que quien construye una lista de
 * obras necesita esta respuesta antes de consultarla. Un filtro de cliente
 * significaría que el contenido viaja hasta el navegador de quien pidió no
 * verlo, y para material sensible eso no sirve de nada.
 *
 * Responde **una lista de códigos y nada más**. En particular, nadie puede
 * preguntar al revés —cuánta gente excluye una etiqueta— porque eso le daría
 * a un autor la forma de saber cuánta audiencia pierde por etiquetar bien, y
 * con ella el incentivo para etiquetar mal (`RN-7`).
 */
interface ReaderContentPreferences
{
    /**
     * @return list<string> vacía si nunca ha excluido nada, que es lo normal
     */
    public function excludedBy(string $userId): array;
}
