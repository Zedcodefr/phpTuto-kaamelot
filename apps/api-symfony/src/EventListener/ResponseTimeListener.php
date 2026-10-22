<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A3 — Deux écouteurs dans une classe : on note l'heure au tout début (kernel.request, priorité haute,
 * avant le routing qui peut lever une 404) et on pose l'en-tête à la fin (kernel.response, qui passe
 * aussi pour les réponses d'erreur). ≈ un middleware ASP.NET Core avec un Stopwatch autour de next().
 */
final class ResponseTimeListener
{
    private const START = 'response_time_start';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::START, hrtime(true));
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $start = $request->attributes->get(self::START);
        if (!$event->isMainRequest() || null === $start || !str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $milliseconds = intdiv(hrtime(true) - $start, 1_000_000); // hrtime() est en nanosecondes
        $event->getResponse()->headers->set('X-Response-Time', (string) $milliseconds);
    }
}
