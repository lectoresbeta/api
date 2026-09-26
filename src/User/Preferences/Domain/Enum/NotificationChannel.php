<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

/**
 * Por dónde llega un aviso (`FEAT-USR-039`).
 *
 * Dos, y **no ofrecen lo mismo**: nadie quiere un correo por cada mensaje
 * directo, y un consejo de uso no es un aviso de actividad. Por eso el modelo
 * es una lista de pares `(tipo, canal)` admitidos y no el producto cartesiano
 * de tipos por canales: **no hay matriz completa**.
 */
enum NotificationChannel: string
{
    case EMAIL = 'EMAIL';
    case PLATFORM = 'PLATFORM';
}
