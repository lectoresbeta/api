<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Enum;

enum LegalDocumentType: string
{
    case TERMS_OF_USE = 'TERMS_OF_USE';
    case PRIVACY_POLICY = 'PRIVACY_POLICY';
    case COOKIE_POLICY = 'COOKIE_POLICY';
    case LEGAL_NOTICE = 'LEGAL_NOTICE';

    /**
     * The two that must be accepted to create an account (`FEAT-USR-024`).
     *
     * @return list<self>
     */
    public static function requiredOnRegistration(): array
    {
        return [self::TERMS_OF_USE, self::PRIVACY_POLICY];
    }
}
