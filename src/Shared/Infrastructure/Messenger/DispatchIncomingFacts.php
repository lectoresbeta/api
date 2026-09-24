<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Reparte un hecho entre los contextos que lo escuchan.
 *
 * Cada reconstrucción se vuelve a despachar **con `ReceivedStamp`**, que es lo
 * que le dice al enrutado que este mensaje ya viene del transporte y toca
 * ejecutarlo aquí. Sin ese sello, un evento de integración despachado en el
 * bus volvería a salir hacia RabbitMQ y daría vueltas para siempre.
 *
 * Consecuencia que conviene conocer: si el consumidor de un contexto falla,
 * **el hecho entero se reintenta** y los demás consumidores lo ven otra vez.
 * No es un problema porque la idempotencia es requisito de todos ellos —la
 * cola no promete entrega única de todas formas—, pero sí es la razón de que
 * lo sea.
 */
final readonly class DispatchIncomingFacts
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(IncomingFacts $facts): void
    {
        foreach ($facts->events as $event) {
            $this->bus->dispatch(new Envelope($event, [new ReceivedStamp('integration')]));
        }
    }
}
