<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAuthorLinks;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorLinkView;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorLink;
use LectoresBeta\User\AuthorPage\Domain\Exception\AuthorLinkRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\AuthorLinkRepository;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkLabel;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkUrl;

/**
 * Las referencias de la página de autor (`FEAT-USR-015`).
 *
 * **La página de autor es el perfil**, no una pantalla aparte (`P-5`,
 * resuelta), así que esta ficha no trae una segunda biografía ni una segunda
 * foto: el nombre, la descripción, el avatar y la portada ya viven en
 * `User\Profile` y las obras publicadas en este mismo concepto. Dos perfiles
 * serían dos textos que envejecen por separado y dos sitios donde la misma
 * persona se describe de forma distinta.
 *
 * Lo que sí faltaba son las referencias, y esto es lo que las guarda.
 *
 * **Se sustituyen enteras**, que es lo que significa el formulario: «estas
 * son mis referencias», no «he añadido una».
 *
 * La etiqueta se sanea como cualquier texto que escribe un usuario y se
 * pinta a otro. La dirección no se sanea: se **valida**, y si no pasa se
 * rechaza — un enlace a medio arreglar es peor que ninguno.
 */
final readonly class UpdateAuthorLinksHandler
{
    /**
     * Un perfil con veinte enlaces deja de ser una página de autor y pasa a
     * ser un directorio de enlaces, que es otra cosa y atrae a quien quiere
     * justamente eso.
     */
    public const MAX_LINKS = 6;

    public function __construct(
        private AuthorLinkRepository $links,
        private TransactionalSession $session,
    ) {
    }

    /**
     * @return list<AuthorLinkView>
     */
    public function __invoke(UpdateAuthorLinks $command): array
    {
        if (\count($command->links) > self::MAX_LINKS) {
            throw AuthorLinkRefused::tooMany(self::MAX_LINKS);
        }

        $userId = UserId::fromString($command->userId);
        $links = [];
        $views = [];
        $position = 0;

        foreach ($command->links as $given) {
            $label = AuthorLinkLabel::fromString($given['label'] ?? null);
            $url = AuthorLinkUrl::fromString($given['url'] ?? '');

            $links[] = new AuthorLink(AuthorLinkId::generate(), $userId, $label, $url, $position++);
            $views[] = new AuthorLinkView($label->value(), $url->value());
        }

        $this->session->execute(function () use ($userId, $links): void {
            $this->links->replaceAllOf($userId, $links);
        });

        return $views;
    }
}
