<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

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
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        if (
            !array_key_exists('email', $data)
            || !is_string($data['email'])
        ) {
            return $this->json(
                [
                    'errors' => [
                        [
                            'field' => 'email',
                            'message' => 'L’adresse e-mail est obligatoire.',
                        ],
                    ],
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        /*
         * setEmail() normalise déjà l’adresse :
         * - suppression des espaces au début et à la fin ;
         * - conversion en minuscules.
         */
        $user->setEmail($data['email']);

        /*
         * Exécute les contraintes présentes dans User.php :
         * - NotBlank ;
         * - Email ;
         * - Length ;
         * - UniqueEntity.
         */
        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $statusCode = Response::HTTP_BAD_REQUEST;

            foreach ($errors as $error) {
                if ($error->getCode() === UniqueEntity::NOT_UNIQUE_ERROR) {
                    $statusCode = Response::HTTP_CONFLICT;

                    break;
                }
            }

            return $this->json(
                [
                    'errors' => $this->formatErrors($errors),
                ],
                $statusCode
            );
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            /*
             * Protection complémentaire contre le cas rare où deux
             * requêtes utilisent simultanément la même adresse.
             */
            return $this->json(
                [
                    'errors' => [
                        [
                            'field' => 'email',
                            'message' => 'Cette adresse e-mail est déjà utilisée.',
                        ],
                    ],
                ],
                Response::HTTP_CONFLICT
            );
        }

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

        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;
        $newPasswordConfirmation =
            $data['newPasswordConfirmation'] ?? null;

        if (
            !is_string($currentPassword)
            || !is_string($newPassword)
            || !is_string($newPasswordConfirmation)
            || $currentPassword === ''
            || $newPassword === ''
            || $newPasswordConfirmation === ''
        ) {
            return $this->json(
                ['message' => 'Tous les champs sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        /*
         * Limite défensive contre l’envoi de mots de passe
         * anormalement volumineux au système de hashage.
         */
        if (
            mb_strlen($currentPassword) > 4096
            || mb_strlen($newPassword) > 4096
            || mb_strlen($newPasswordConfirmation) > 4096
        ) {
            return $this->json(
                ['message' => 'La valeur fournie est trop longue.'],
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

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $newPassword
        );

        $user->setPassword($hashedPassword);

        $entityManager->flush();

        return $this->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function formatErrors(
        ConstraintViolationListInterface $errors
    ): array {
        $formattedErrors = [];

        foreach ($errors as $error) {
            $formattedErrors[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
            ];
        }

        return $formattedErrors;
    }
}
