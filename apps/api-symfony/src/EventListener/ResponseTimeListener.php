<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class ResponseTimeListener
{

    #[AsEventListener(priority: 64)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getRequest()->attributes->set('request_start_time', hrtime(true));
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $start = $request->attributes->get('request_start_time');
        if (null === $start) {
            return;
        }

        $durationMs = intdiv(hrtime(true) - $start, 1_000_000);
        $event->getResponse()->headers->set('X-Response-Time', (string) $durationMs);
    }
}
