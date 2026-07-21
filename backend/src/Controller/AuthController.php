<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = $request->toArray();

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (
            !is_string($email)
            || !is_string($password)
            || $email === ''
            || $password === ''
        ) {
            return $this->json(
                [
                    'message' =>
                    'L’adresse e-mail et le mot de passe sont obligatoires.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (mb_strlen($password) < 12) {
            return $this->json(
                [
                    'message' =>
                    'Le mot de passe doit contenir au moins 12 caractères.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }


        if (mb_strlen($password) > 4096) {
            return $this->json(
                [
                    'message' =>
                    'Le mot de passe ne peut pas dépasser 4096 caractères.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $user = new User();

        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);

        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $formattedErrors = [];
            $statusCode = JsonResponse::HTTP_BAD_REQUEST;

            foreach ($errors as $error) {
                $formattedErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];

                if (
                    $error->getPropertyPath() === 'email'
                    && $error->getMessage()
                    === 'Cette adresse e-mail est déjà utilisée.'
                ) {
                    $statusCode = JsonResponse::HTTP_CONFLICT;
                }
            }

            return $this->json(
                ['errors' => $formattedErrors],
                $statusCode
            );
        }

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            /*
             * Protection supplémentaire si deux inscriptions simultanées
             * tentent d’utiliser la même adresse e-mail.
             */
            return $this->json(
                [
                    'errors' => [
                        [
                            'field' => 'email',
                            'message' =>
                            'Cette adresse e-mail est déjà utilisée.',
                        ],
                    ],
                ],
                JsonResponse::HTTP_CONFLICT
            );
        }

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
