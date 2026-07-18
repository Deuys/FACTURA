<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class NotificationController extends AbstractController
{
    #[Route(
        '/api/notifications',
        name: 'api_notifications_list',
        methods: ['GET']
    )]
    public function index(
        NotificationRepository $notificationRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $notifications = $notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = array_map(
            fn(Notification $notification): array => [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'titre' => $notification->getTitre(),
                'message' => $notification->getMessage(),
                'url' => $notification->getUrl(),
                'lue' => $notification->isLue(),
                'createdAt' => $notification
                    ->getCreatedAt()
                    ?->format(DATE_ATOM),
            ],
            $notifications
        );

        return $this->json([
            'nombreNotifications' => count($data),
            'nombreNonLues' => count(
                array_filter(
                    $data,
                    fn(array $notification): bool =>
                    $notification['lue'] === false
                )
            ),
            'notifications' => $data,
        ]);
    }

    #[Route(
        '/api/notifications/{id}/lire',
        name: 'api_notifications_read',
        methods: ['PATCH']
    )]
    public function lire(
        Notification $notification,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($notification->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $notification->setLue(true);
        $entityManager->flush();

        return $this->json([
            'message' => 'Notification marquée comme lue.',
            'notification' => [
                'id' => $notification->getId(),
                'lue' => $notification->isLue(),
            ],
        ]);
    }

    #[Route(
        '/api/notifications/lire-toutes',
        name: 'api_notifications_read_all',
        methods: ['PATCH']
    )]
    public function lireToutes(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $notifications = $notificationRepository->findBy([
            'user' => $user,
            'lue' => false,
        ]);

        foreach ($notifications as $notification) {
            $notification->setLue(true);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'nombreModifiees' => count($notifications),
        ]);
    }
}
