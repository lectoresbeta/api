<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede publicar eso (`FEAT-COM-002`).
 *
 * Fíjate en qué **no** está aquí: la publicación de otra persona no da un
 * error de permiso, da `PostNotFound`. Un `403` sobre una publicación que no
 * puedes ver confirmaría que existe, y eso ya es información sobre quien la
 * escribió.
 */
final class PostRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Ni texto ni adjunto.
     *
     * Es la única forma de estar vacía: una foto sin texto es una publicación
     * legítima, y obligar a escribir algo junto a ella solo consigue que la
     * gente escriba un punto.
     */
    public static function empty(): self
    {
        return new self(
            'EMPTY_POST',
            FailureKind::INVALID,
            'A post needs text or an attachment.',
        );
    }

    /**
     * Más de un adjunto.
     *
     * Se rechaza en vez de quedarse con el primero: quien manda dos cree que
     * va a publicar dos, y elegir por él le enseñaría el resultado cuando ya
     * no puede cambiarlo.
     */
    /**
     * Una publicación que busca lectores beta **sin obra** (`FEAT-COM-003`
     * `RN-1`).
     *
     * «Busco lectores» sin decir para qué es una petición que nadie puede
     * atender: quien la lee no sabe qué se le ofrece, y quien la escribe no
     * recibe a nadie.
     */
    public static function withoutAWorkToRead(): self
    {
        return new self(
            'WORK_REQUIRED',
            FailureKind::INVALID,
            'A post looking for beta readers has to say which work.',
        );
    }

    /**
     * La obra es de otra persona (`FEAT-COM-003` `RN-2`).
     *
     * Reclutar lectores para lo que escribió otro es decidir por él a quién
     * enseña su texto, que es justo lo que la modalidad de acceso existe para
     * que decida su autor.
     */
    public static function forSomebodyElsesWork(): self
    {
        return new self(
            'NOT_YOUR_WORK',
            FailureKind::FORBIDDEN,
            'You can only look for beta readers for your own work.',
        );
    }

    /**
     * La obra no se puede ver todavía (`FEAT-COM-003` `RN-3`).
     *
     * Anunciar un borrador manda a quien responda a una puerta cerrada: no
     * puede leerlo ni solicitar acceso, porque para él la obra no existe.
     */
    public static function forAWorkNobodyCanSeeYet(): self
    {
        return new self(
            'WORK_NOT_VISIBLE',
            FailureKind::CONFLICT,
            'Publish the work before looking for beta readers for it.',
        );
    }

    /**
     * Se busca lo que se tiene cerrado (`FEAT-COM-004` `RN-2`,
     * `FEAT-COM-005` `RN-2`).
     *
     * Publicar «busco writing buddy» con las propuestas cerradas manda a todo
     * el que responda contra una puerta cerrada, y quien publicó no se entera
     * nunca de por qué no le escribe nadie. Se rechaza aquí y no se abre el
     * ajuste solo: publicar no es consentir, y un ajuste de recepción que se
     * abre sin pedirlo deja de ser un ajuste.
     */
    public static function whileTheDoorIsShut(string $setting): self
    {
        return new self(
            'RECEPTION_CLOSED',
            FailureKind::CONFLICT,
            \sprintf('Open %s in your settings before asking for it on the wall.', $setting),
        );
    }

    /**
     * Nadie ha designado la cuenta institucional (`FEAT-COM-038` `RN-3`).
     *
     * Es un estado normal de una instalación recién puesta en marcha, no una
     * avería, y por eso se dice con claridad: a quien opera le falta ejecutar
     * un comando, y un error genérico le haría buscar en el sitio equivocado.
     */
    public static function withoutAPlatformAccount(): self
    {
        return new self(
            'NO_PLATFORM_ACCOUNT',
            FailureKind::CONFLICT,
            'No account has been designated to speak for the platform yet.',
        );
    }

    public static function tooManyAttachments(): self
    {
        return new self(
            'TOO_MANY_ATTACHMENTS',
            FailureKind::INVALID,
            'A post carries one attachment at most.',
        );
    }

    public static function imageTooLarge(): self
    {
        return new self(
            'FILE_TOO_LARGE',
            FailureKind::TOO_LARGE,
            'That image is too large.',
        );
    }

    public static function unsupportedImage(): self
    {
        return new self(
            'UNSUPPORTED_FILE_TYPE',
            FailureKind::INVALID,
            'That file is not an image this platform can read.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
