<?php
// src/EventSubscriber/JWTCreatedSubscriber.php
namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// THIS IS USED TO ADD THE USER ID TO THE JWT

final class JWTCreatedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJWTCreated',
        ];
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof UserInterface) {
            return;                              // safety for other user types
        }

        $payload        = $event->getData();
        $payload['id']  = $user->getId();        // add any extra claims here
        $event->setData($payload);
    }
}