<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who Symfony thinks is asking. It is **not** the `User` aggregate.
 *
 * The aggregate must not implement `UserInterface`: that would put a Symfony
 * contract on a domain entity, which `AGENTS.md` forbids, and would make the
 * domain answer questions like «what roles do you have» that it has no
 * opinion about.
 *
 * So this holds one thing, the identifier, and that is also all the access
 * token carries (`decision:0007` `RN-4`). Everything else — status, name,
 * permissions — is looked up when it is needed, which is what stops a
 * privilege that was just revoked from surviving for fifteen minutes inside a
 * token.
 */
final readonly class AuthenticatedUser implements UserInterface
{
    /**
     * @param non-empty-string $userId
     * @param list<string>     $roles  lo que esta persona puede hacer **ahora
     *                                 mismo**, resuelto contra la base de
     *                                 datos en cada petición y nunca leído
     *                                 del token
     */
    public function __construct(
        private string $userId,
        private array $roles = ['ROLE_USER'],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->userId;
    }

    /**
     * Everyone is just a user here. Roles are not in the token and are not
     * decided by this class; an operation that needs more asks the policy
     * that owns the question.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // Nothing held: there are no credentials in this object to erase.
    }
}
