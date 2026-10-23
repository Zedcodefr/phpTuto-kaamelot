<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class ResponseTimeListener{

    #[AsEventListener(priority: 64)]
    public function onRequest(RequestEvent $event): void{
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) return;

        $request->attributes->set('_response_time_start', hrtime(true));
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void{
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) return;

        $timerAttribute = $request->attributes->get('_response_time_start');

        if ($timerAttribute == null) return;

        $elapsedMs = intdiv(hrtime(true) - $timerAttribute, 1_000_000);

        $event->getResponse()->headers->set('X-Response-Time', (string) $elapsedMs);
    }
}
