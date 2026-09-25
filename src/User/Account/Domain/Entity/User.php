<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Entity;

use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Enum\OnboardingStatus;
use LectoresBeta\User\Account\Domain\Exception\AccountAlreadyActivated;
use LectoresBeta\User\Account\Domain\Exception\AccountIsDeleted;
use LectoresBeta\User\Account\Domain\Exception\UsernameChangedTooRecently;
use LectoresBeta\User\Account\Domain\ValueObject\BirthDate;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use LectoresBeta\User\Account\Domain\ValueObject\PersonName;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * A person on the platform.
 *
 * Identifiers and enums are held as scalars and handed out as value objects.
 * Doctrine needs a type it can put in a column, and the alternative — a
 * custom Doctrine type per identifier — would be around forty classes of
 * ceremony for no behaviour. The domain API never sees the scalar.
 *
 * Deleting is anonymising, not erasing: the row stays so that corrections and
 * credit movements keep pointing somewhere, with every personal field emptied
 * (`FEAT-USR-013`).
 */
class User
{
    private string $id;

    private string $email;

    private string $username;

    private ?\DateTimeImmutable $usernameChangedAt = null;

    /**
     * Cuándo se recuperó por última vez un nombre propio, o `null` si el
     * último cambio fue uno normal (`FEAT-USR-034` `RN-1b`).
     *
     * Existe para que la excepción al plazo **no se pueda encadenar**: sin
     * ella, cada recuperación deja como alias el nombre que se abandona, con
     * lo que la siguiente vuelta vuelve a ser una recuperación y el plazo no
     * llega a aplicarse nunca.
     */
    private ?\DateTimeImmutable $usernameReclaimedAt = null;

    private ?string $passwordHash = null;

    private AuthProvider $authProvider;

    private ?string $externalId = null;

    private AccountStatus $status;

    private OnboardingStatus $onboardingStatus;

    private ?string $name = null;

    private ?string $description = null;

    private ?\DateTimeImmutable $birthDate = null;

    /**
     * Hasta cuándo no puede escribir, si cumple una suspensión parcial.
     *
     * Fecha y no estado de cuenta a propósito: **caduca sola** (`RN-2`), y un
     * estado que hay que recordar apagar es un estado que alguien olvidará.
     */
    private ?\DateTimeImmutable $restrictedUntil = null;

    private ?string $avatarUrl = null;

    /**
     * The uncropped upload, kept so the editor can be reopened
     * (`FEAT-USR-037`). It is never part of the public profile.
     */
    private ?string $avatarOriginalUrl = null;

    /** @var array<string, float|int>|null */
    private ?array $avatarCrop = null;

    private ?string $coverUrl = null;

    /**
     * Who invited this person, if anyone. The reward is not granted here: it
     * is granted when the invited person delivers their first correction
     * (`decision:0006`, rule 6).
     */
    private ?string $invitedBy = null;

    private \DateTimeImmutable $registeredAt;

    private ?\DateTimeImmutable $activatedAt = null;

    /**
     * Cuándo entró por última vez (`FEAT-USR-005` `RN-5`).
     *
     * **Una fecha y nada más** (`RN-6`): ni dirección, ni navegador, ni
     * localización. Son datos personales que nadie necesita aquí y que
     * habría que custodiar, justificar y acabar borrando.
     *
     * Nulo significa que todavía no ha entrado, que es distinto de que no
     * entre desde hace mucho.
     */
    private ?\DateTimeImmutable $lastSignedInAt = null;

    private \DateTimeImmutable $updatedAt;

    private function __construct(
        UserId $id,
        Email $email,
        Username $username,
        AuthProvider $authProvider,
        AccountStatus $status,
        \DateTimeImmutable $now,
        ?UserId $invitedBy,
    ) {
        $this->id = $id->value();
        $this->email = $email->value();
        $this->username = $username->value();
        $this->authProvider = $authProvider;
        $this->status = $status;
        $this->onboardingStatus = OnboardingStatus::PROFILE_PENDING;
        $this->invitedBy = $invitedBy?->value();
        $this->registeredAt = $now;
        $this->updatedAt = $now;
        $this->activatedAt = AccountStatus::ACTIVE === $status ? $now : null;
    }

    /**
     * Signing up with email and password (`FEAT-USR-001`). The account starts
     * unverified and cannot write until the activation email is followed.
     */
    public static function register(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $password,
        \DateTimeImmutable $now,
        ?UserId $invitedBy = null,
    ): self {
        $user = new self(
            $id,
            $email,
            $username,
            AuthProvider::LOCAL,
            AccountStatus::PENDING_ACTIVATION,
            $now,
            $invitedBy,
        );
        $user->passwordHash = $password->value();

        return $user;
    }

    /**
     * Signing up with Google (`FEAT-USR-002`). Google has already verified the
     * address, so there is nothing left to activate (`OB-11`) and the account
     * starts `ACTIVE`.
     */
    public static function registerWithGoogle(
        UserId $id,
        Email $email,
        Username $username,
        string $externalId,
        \DateTimeImmutable $now,
        ?UserId $invitedBy = null,
    ): self {
        $user = new self(
            $id,
            $email,
            $username,
            AuthProvider::GOOGLE,
            AccountStatus::ACTIVE,
            $now,
            $invitedBy,
        );
        $user->externalId = $externalId;

        return $user;
    }

    /**
     * Enlazar una cuenta de Google a una cuenta que ya existía
     * (`FEAT-USR-002` `RN-6`, `U-2`).
     *
     * **La contraseña se queda.** Quitársela a quien ya entraba con ella
     * sería cerrarle la puerta que usa por haber probado otra, y no protege
     * nada: quien acaba de demostrar que controla ese correo podría
     * restablecerla en un minuto.
     *
     * Solo se enlaza si el proveedor afirma que **ese correo está
     * verificado**. Sin esa afirmación, «tengo una cuenta con tu dirección»
     * no demuestra nada, y enlazar sería entregar una cuenta ajena a quien
     * supiera el correo de su dueño.
     */
    public function linkExternalIdentity(AuthProvider $provider, string $externalId, \DateTimeImmutable $now): void
    {
        $this->authProvider = $provider;
        $this->externalId = $externalId;
        $this->updatedAt = $now;
    }

    /**
     * Ha abierto sesión (`FEAT-USR-005` `RN-5`).
     *
     * Renovar cuenta como entrar: quien tiene la aplicación abierta la está
     * usando, y para lo que esta fecha sirve —saber si una cuenta sigue
     * viva— la distinción no existe.
     *
     * **No toca `updatedAt`.** Entrar no modifica la cuenta, y contarlo como
     * modificación haría que cualquier consulta por «cambiadas desde» se
     * llenara de gente que solo pasaba por aquí.
     */
    public function recordSignIn(\DateTimeImmutable $now): void
    {
        $this->lastSignedInAt = $now;
    }

    public function lastSignedInAt(): ?\DateTimeImmutable
    {
        return $this->lastSignedInAt;
    }

    public function id(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function email(): Email
    {
        return Email::fromString($this->email);
    }

    public function username(): Username
    {
        return Username::fromString($this->username);
    }

    public function usernameChangedAt(): ?\DateTimeImmutable
    {
        return $this->usernameChangedAt;
    }

    public function passwordHash(): ?HashedPassword
    {
        return null === $this->passwordHash ? null : HashedPassword::fromHash($this->passwordHash);
    }

    public function authProvider(): AuthProvider
    {
        return $this->authProvider;
    }

    public function externalId(): ?string
    {
        return $this->externalId;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function onboardingStatus(): OnboardingStatus
    {
        return $this->onboardingStatus;
    }

    public function name(): ?PersonName
    {
        return null === $this->name ? null : PersonName::fromString($this->name);
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function birthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function avatarOriginalUrl(): ?string
    {
        return $this->avatarOriginalUrl;
    }

    /**
     * @return array<string, float|int>|null
     */
    public function avatarCrop(): ?array
    {
        return $this->avatarCrop;
    }

    public function coverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function invitedBy(): ?UserId
    {
        return null === $this->invitedBy ? null : UserId::fromString($this->invitedBy);
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function activatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    /**
     * Si esta cuenta puede escribir **ahora mismo**.
     *
     * Dos cosas lo impiden y son distintas: no haber activado la cuenta
     * ([`decision:0003`](../../../../../docs/decisions/0003-write-operations-require-activated-account.md))
     * y estar cumpliendo una **suspensión parcial** (`FEAT-MOD-006` `MOD-27`).
     * La segunda deja entrar y leer, que no es una concesión menor: es lo que
     * permite a la persona leer la sanción, entender por qué la tiene y ver
     * cuándo termina. Una suspensión que además cierra la puerta no corrige
     * nada, solo hace que se vaya.
     */
    public function canWriteAt(\DateTimeImmutable $moment): bool
    {
        if (!$this->status->canWrite()) {
            return false;
        }

        return null === $this->restrictedUntil || $moment >= $this->restrictedUntil;
    }

    public function restrictedUntil(): ?\DateTimeImmutable
    {
        return $this->restrictedUntil;
    }

    /**
     * Aplicar lo que `Moderation` ha decidido (`FEAT-MOD-006`).
     *
     * **`Moderation` registra la sanción y `User` la aplica.** Si `Moderation`
     * marcara la cuenta directamente habría dos dueños del estado del
     * usuario, y el día que discreparan no habría forma de saber cuál manda.
     */
    public function restrictWritingUntil(\DateTimeImmutable $until, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->restrictedUntil = $until;
        $this->touch($now);
    }

    public function suspend(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->status = AccountStatus::SUSPENDED;
        $this->touch($now);
    }

    /**
     * La expulsión **bloquea, no anonimiza** (`MOD-26`). Conservar el correo
     * es lo mínimo que la hace efectiva: sin él, la persona se registra otra
     * vez al minuto siguiente.
     */
    public function expel(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->status = AccountStatus::BLOCKED;
        $this->touch($now);
    }

    /**
     * Levantar lo que hubiera. Devuelve la cuenta a `ACTIVE` **solo si la
     * sanción era lo que la sacó de ahí**: una cuenta sin activar que cumple
     * una sanción sigue sin activar cuando termina.
     */
    public function liftSanctions(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->restrictedUntil = null;

        if (AccountStatus::SUSPENDED === $this->status || AccountStatus::BLOCKED === $this->status) {
            $this->status = AccountStatus::ACTIVE;
        }

        $this->touch($now);
    }

    public function canWrite(): bool
    {
        return $this->status->canWrite();
    }

    /**
     * Following the activation link (`FEAT-USR-020`). This is the moment the
     * welcome credits are earned — `Credits` decides how many, on its own
     * reading of `AccountActivated`.
     */
    public function activate(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        if (AccountStatus::PENDING_ACTIVATION !== $this->status) {
            throw AccountAlreadyActivated::forUser($this->id);
        }

        $this->status = AccountStatus::ACTIVE;
        $this->activatedAt = $now;
        $this->touch($now);
    }

    public function completeProfileStep(PersonName $name, BirthDate $birthDate, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->name = $name->value();
        $this->birthDate = $birthDate->value();
        $this->advanceOnboarding(OnboardingStatus::PROFILE_PENDING, $now);
    }

    public function completeGenresStep(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->advanceOnboarding(OnboardingStatus::GENRES_PENDING, $now);
    }

    public function completeOnboarding(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->onboardingStatus = OnboardingStatus::COMPLETED;
        $this->touch($now);
    }

    public function updateProfile(?PersonName $name, ?string $description, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->name = $name?->value();
        $this->description = null === $description ? null : trim($description);
        $this->touch($now);
    }

    /**
     * @param array<string, float|int>|null $crop
     */
    public function updateAvatar(?string $url, ?string $originalUrl, ?array $crop, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->avatarUrl = $url;
        $this->avatarOriginalUrl = $originalUrl;
        $this->avatarCrop = $crop;
        $this->touch($now);
    }

    public function updateCover(?string $url, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->coverUrl = $url;
        $this->touch($now);
    }

    /**
     * Once every 30 days (`FEAT-USR-034` `RN-2`). Reserving the freed name as
     * an alias is the caller's job: the alias is a different aggregate.
     */
    public function changeUsername(Username $username, \DateTimeImmutable $now, int $cooldownDays = 30): void
    {
        $this->guardNotDeleted();

        if (null !== $this->usernameChangedAt) {
            $availableOn = $this->usernameChangedAt->modify(\sprintf('+%d days', $cooldownDays));

            if ($now < $availableOn) {
                throw UsernameChangedTooRecently::availableOn($availableOn);
            }
        }

        $this->username = $username->value();
        $this->usernameChangedAt = $now;
        $this->usernameReclaimedAt = null;
        $this->touch($now);
    }

    /**
     * Recuperar un nombre propio que sigue reservado (`FEAT-USR-034`
     * `RN-1b`).
     *
     * **Esquiva el plazo pero lo renueva**, y las dos mitades importan. Si no
     * lo esquivara, arrepentirse de un cambio —el caso más previsible de esta
     * funcionalidad— obligaría a esperar un mes con un nombre que no se
     * quiere. Si no lo renovara, se podría alternar entre dos nombres
     * indefinidamente, y cada vuelta rompería los enlaces que el plazo existe
     * para proteger.
     *
     * Que sea un método aparte y no un booleano en `changeUsername()` es
     * deliberado: un parámetro que desactiva una comprobación de seguridad
     * acaba pasándose desde donde no debe.
     */
    public function reclaimUsername(Username $username, \DateTimeImmutable $now, int $cooldownDays = 30): void
    {
        $this->guardNotDeleted();

        // **La excepción no se encadena.** Recuperar deja como alias el
        // nombre que se abandona, así que sin esto la vuelta siguiente sería
        // otra recuperación y el plazo no se aplicaría jamás: exactamente el
        // vaivén que `RN-1b` dice bloquear. Deshacer un cambio es el caso
        // previsible; deshacer un «deshacer» ya es alternar.
        if (null !== $this->usernameReclaimedAt) {
            $availableOn = $this->usernameReclaimedAt->modify(\sprintf('+%d days', $cooldownDays));

            if ($now < $availableOn) {
                throw UsernameChangedTooRecently::availableOn($availableOn);
            }
        }

        $this->username = $username->value();
        $this->usernameChangedAt = $now;
        $this->usernameReclaimedAt = $now;
        $this->touch($now);
    }

    /**
     * Cuándo podrá volver a cambiarlo. Es lo que permite desactivar el
     * formulario **sin fallar primero**.
     */
    public function usernameChangeableOn(\DateTimeImmutable $now, int $cooldownDays = 30): \DateTimeImmutable
    {
        if (null === $this->usernameChangedAt) {
            return $now;
        }

        return $this->usernameChangedAt->modify(\sprintf('+%d days', $cooldownDays));
    }

    /**
     * Applied only once the new address has been confirmed
     * (`FEAT-USR-040`): until then the valid address is still the old one.
     */
    public function changeEmail(Email $email, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->email = $email->value();
        $this->touch($now);
    }

    public function changePassword(HashedPassword $password, \DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->passwordHash = $password->value();
        $this->touch($now);
    }

    /**
     * Expulsion (`FEAT-MOD-006`). The account is blocked, **not anonymised**:
     * the person keeps their data and can read, but cannot write again
     * (`MOD-26`).
     */
    public function block(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        $this->status = AccountStatus::BLOCKED;
        $this->touch($now);
    }

    public function unblock(\DateTimeImmutable $now): void
    {
        $this->guardNotDeleted();

        if (AccountStatus::BLOCKED !== $this->status) {
            return;
        }

        $this->status = null === $this->activatedAt
            ? AccountStatus::PENDING_ACTIVATION
            : AccountStatus::ACTIVE;
        $this->touch($now);
    }

    /**
     * Deleting the account (`FEAT-USR-013`). Every personal field is emptied
     * and the row survives as an identifier that nothing points away from.
     *
     * The email is replaced by a unique placeholder rather than set to null:
     * the column is unique and the address must become reusable by nobody,
     * including the person who left.
     */
    public function anonymise(\DateTimeImmutable $now): void
    {
        $this->status = AccountStatus::DELETED;
        $this->email = \sprintf('deleted+%s@lectoresbeta.invalid', $this->id);
        $this->username = 'deleted_'.substr(str_replace('-', '', $this->id), 0, 20);
        $this->passwordHash = null;
        $this->externalId = null;
        $this->name = null;
        $this->description = null;
        $this->birthDate = null;
        $this->avatarUrl = null;
        $this->avatarOriginalUrl = null;
        $this->avatarCrop = null;
        $this->coverUrl = null;
        $this->invitedBy = null;
        $this->touch($now);
    }

    private function advanceOnboarding(OnboardingStatus $expected, \DateTimeImmutable $now): void
    {
        if ($this->onboardingStatus === $expected) {
            $this->onboardingStatus = $expected->next();
        }

        $this->touch($now);
    }

    private function guardNotDeleted(): void
    {
        if (AccountStatus::DELETED === $this->status) {
            throw AccountIsDeleted::forUser($this->id);
        }
    }

    private function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
