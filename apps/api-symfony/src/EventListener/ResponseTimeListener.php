<?php

declare(strict_types=1);

namespace App\EventListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class ResponseTimeListener
{
    #[AsEventListener] // l'événement est déduit du type de l'argument
    public function response(ResponseEvent $event): void
    {
        if(!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/api'))
            return;

        $depart = (int)$event->getResponse()->headers->get('X-Api-Depart');
        $arrivee = (int)hrtime(true);
        $result = $arrivee - $depart;

        $event->getResponse()->headers->set('X-Response-Time', "$result");
    }

    #[AsEventListener] // l'événement est déduit du type de l'argument
    public function request(RequestEvent $event): void
    {
        if(!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/api'))
            return;

        $depart = hrtime(true);
        $event->getRequest()->attributes->set('X-Api-Depart', $depart);
    }
}
