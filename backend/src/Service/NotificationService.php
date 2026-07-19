<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
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

        $errors = $this->validator->validate($notification);

        if (count($errors) > 0) {
            throw new ValidationFailedException($notification, $errors);
        }

        $this->entityManager->persist($notification);

        return $notification;
    }
}
