<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Http;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\Exception\AccountNotActivated;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * No writing until the account is activated
 * ([`decision:0003`](../../../../../docs/decisions/0003-write-operations-require-activated-account.md),
 * [`FEAT-USR-025`](../../../../../docs/features/user/FEAT-USR-025-block-writes-until-activation.md)).
 *
 * **One policy, at the boundary, and not repeated in any controller**
 * (`RN-2`). A check copied into each write operation is a check that will be
 * missing from the next one somebody adds, and nobody notices until an
 * unverified account has published something.
 *
 * So it is **deny by default**: every unsafe HTTP method requires an active
 * account, and the exceptions are listed below, by name, with the reason.
 * A new write endpoint is protected the moment it exists, without its author
 * having to know this class is here.
 *
 * It reads the account **from the database** on every write, which is also
 * what [`decision:0007`](../../../../../docs/decisions/0007-jwt-sessions.md)
 * `RN-5` requires: the signature of a token is not enough to write, because a
 * token stays valid for fifteen minutes after the account behind it stops
 * being allowed to.
 *
 * It lives in `User` because the rule is `User`'s. No other context knows it
 * exists, and none has to.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class RequireActivatedAccountListener
{
    /**
     * Writes that an unactivated account is still allowed to make.
     *
     * Adding a name here is a deliberate, visible act, and each one states
     * why. Three families, and no fourth:
     *
     * - **the onboarding**, which is the whole point of letting somebody in
     *   before activating (`RN-5`);
     * - **managing your own account**, including the way out of this very
     *   state (`RN-6`). An account that could not ask for the activation
     *   email again would be locked out by the rule meant to protect it.
     * - **reading your own notices**, for the same reason as the family
     *   above: **the notice telling somebody to activate is in that inbox**,
     *   and a rule that stops them clearing it would be the rule getting in
     *   its own way (`FEAT-NOT-009`). What these two write is a `readAt` on a
     *   row that belongs to whoever calls them, so nothing leaves the
     *   account — which is what `decision:0003` is there to prevent.
     *
     * Public routes are not listed: with nobody signed in there is nothing to
     * check. `logout` and `refreshSession` are, because they may well arrive
     * with a token.
     */
    private const ALLOWED_WITHOUT_ACTIVATION = [
        // The onboarding, which is why somebody is let in before activating.
        'submitOnboardingProfile',
        'submitOnboardingGenres',
        'completeOnboarding',

        // Managing the session you are already in.
        'login',
        'logout',
        'refreshSession',

        // Getting in, and getting out of `PENDING_ACTIVATION`.
        'registerUser',
        'activateAccount',
        'resendActivationEmail',

        // Your own inbox, where the notice about activating is.
        'markNotificationRead',
        'markAllNotificationsRead',

        // Dismissing the welcome tour (`FEAT-USR-026` `RN-6`). It is not
        // publishing anything: it is saying you have already seen something,
        // and a rule meant to keep unverified accounts from writing content
        // should not condemn them to the same four bubbles on every visit.
        'completeTour',

        // El tema (`FEAT-USR-042` `RN-6`). Es una preferencia de pantalla, no
        // una operación sobre contenido, y obligar a activar la cuenta para
        // poner el modo oscuro sería absurdo.
        'updateMyAppearanceSettings',
    ];

    public function __construct(
        private Security $security,
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->isMethodSafe()) {
            return;
        }

        if (\in_array($request->attributes->get('_route'), self::ALLOWED_WITHOUT_ACTIVATION, true)) {
            return;
        }

        $user = $this->security->getUser();

        if (null === $user) {
            // Nobody signed in. Either the route is public and answers for
            // itself, or the firewall has already refused.
            return;
        }

        $account = $this->users->ofId(UserId::fromString($user->getUserIdentifier()));

        // Dos cosas impiden escribir y son distintas: no haber activado la
        // cuenta y estar cumpliendo una suspensión parcial (`FEAT-MOD-006`).
        // La comprobación es una sola porque el sitio donde se hace es uno
        // solo, que es lo que impide que a la próxima escritura se le olvide.
        if (null === $account || !$account->canWriteAt($this->clock->now())) {
            throw AccountNotActivated::create();
        }
    }

    /**
     * @return list<string>
     */
    public static function allowedWithoutActivation(): array
    {
        return self::ALLOWED_WITHOUT_ACTIVATION;
    }
}
