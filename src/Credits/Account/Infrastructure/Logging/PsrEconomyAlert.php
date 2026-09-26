<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Logging;

use LectoresBeta\Credits\Account\Application\Port\EconomyAlert;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

final readonly class PsrEconomyAlert implements EconomyAlert
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function debtRanDeeperThanExpected(UserId $userId, int $balance, int $threshold): void
    {
        $this->logger->alert('A credit balance went deeper than the design predicts.', [
            'userId' => $userId->value(),
            'balance' => $balance,
            'threshold' => $threshold,
        ]);
    }
}
