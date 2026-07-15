<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/account')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'api_account_show', methods: ['GET'])]
    public function show(
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'entrepriseConfiguree' => $user->getEntreprise() !== null,
        ]);
    }

    #[Route('', name: 'api_account_update', methods: ['PUT'])]
    public function update(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        if (!array_key_exists('email', $data)) {
            return $this->json(
                ['message' => 'L’adresse e-mail est obligatoire.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $email = mb_strtolower(
            trim((string) $data['email'])
        );

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['message' => 'L’adresse e-mail est invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $existingUser = $entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (
            $existingUser !== null
            && $existingUser->getId() !== $user->getId()
        ) {
            return $this->json(
                ['message' => 'Cette adresse e-mail est déjà utilisée.'],
                Response::HTTP_CONFLICT
            );
        }

        $user->setEmail($email);

        $entityManager->flush();

        return $this->json([
            'message' => 'Compte modifié avec succès.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route(
        '/password',
        name: 'api_account_password_update',
        methods: ['PUT']
    )]
    public function updatePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $currentPassword = (string) (
            $data['currentPassword'] ?? ''
        );

        $newPassword = (string) (
            $data['newPassword'] ?? ''
        );

        $newPasswordConfirmation = (string) (
            $data['newPasswordConfirmation'] ?? ''
        );

        if (
            $currentPassword === ''
            || $newPassword === ''
            || $newPasswordConfirmation === ''
        ) {
            return $this->json(
                ['message' => 'Tous les champs sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (
            !$passwordHasher->isPasswordValid(
                $user,
                $currentPassword
            )
        ) {
            return $this->json(
                ['message' => 'Le mot de passe actuel est incorrect.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (mb_strlen($newPassword) < 12) {
            return $this->json(
                [
                    'message' =>
                    'Le nouveau mot de passe doit contenir au moins 12 caractères.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($newPassword !== $newPasswordConfirmation) {
            return $this->json(
                [
                    'message' =>
                    'La confirmation du nouveau mot de passe ne correspond pas.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (
            $passwordHasher->isPasswordValid(
                $user,
                $newPassword
            )
        ) {
            return $this->json(
                [
                    'message' =>
                    'Le nouveau mot de passe doit être différent de l’ancien.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setPassword(
            $passwordHasher->hashPassword(
                $user,
                $newPassword
            )
        );

        $entityManager->flush();

        return $this->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }
}
