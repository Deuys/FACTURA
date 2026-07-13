<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;


final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = $request->toArray();

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(
                ['message' => 'L’adresse e-mail et le mot de passe sont obligatoires.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['message' => 'L’adresse e-mail est invalide.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (mb_strlen($password) < 12) {
            return $this->json(
                ['message' => 'Le mot de passe doit contenir au moins 12 caractères.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $existingUser = $entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => mb_strtolower($email)]);

        if ($existingUser !== null) {
            return $this->json(
                ['message' => 'Un compte existe déjà avec cette adresse e-mail.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $user = new User();
        $user->setEmail(mb_strtolower($email));
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Utilisateur créé avec succès.',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if ($user === null) {
            return $this->json(
                ['message' => 'Utilisateur non authentifié.'],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
