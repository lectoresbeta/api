<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Infrastructure\Controller;

use LectoresBeta\Moderation\AuditLog\Application\DTO\AuditEntryView;
use LectoresBeta\Moderation\AuditLog\Application\Handler\ListAuditLogHandler;
use LectoresBeta\Moderation\AuditLog\Application\Query\ListAuditLog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/admin/audit-log` (`FEAT-MOD-007`).
 *
 * **Solo `ADMIN`**, y lo comprueba el firewall: la ruta lo exige, resuelto
 * contra la base de datos en cada petición porque el token no lleva roles.
 *
 * Un registro que nadie puede consultar no es un registro: es una
 * tranquilidad, e invita a confiar en que hay control sin que lo haya.
 */
#[AsController]
final readonly class ListAuditLogController
{
    public function __construct(
        private ListAuditLogHandler $entries,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->security->getUser()) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $entries = ($this->entries)(new ListAuditLog(
            $request->query->get('actorId'),
            $request->query->get('action'),
            $request->query->get('targetType'),
            $request->query->get('targetId'),
            $request->query->get('from'),
            $request->query->get('to'),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'entries' => array_map(
                static fn (AuditEntryView $entry): array => [
                    'entryId' => $entry->entryId,
                    'actorId' => $entry->actorId,
                    'action' => $entry->action,
                    'targetType' => $entry->targetType,
                    'targetId' => $entry->targetId,
                    'reason' => $entry->reason,
                    'payload' => $entry->payload,
                    'occurredAt' => $entry->occurredAt,
                ],
                $entries,
            ),
        ]);
    }
}
