<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function creer(
        User $user,
        string $type,
        string $titre,
        string $message,
        ?string $url = null
    ): Notification {
        $notification = new Notification();

        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setUrl($url);
        $notification->setLue(false);

        $this->entityManager->persist($notification);

        return $notification;
    }
}
