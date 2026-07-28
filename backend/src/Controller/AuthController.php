<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(
                ['message' => 'Le JSON envoyé est invalide.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $prenom = $data['prenom'] ?? null;
        $nom = $data['nom'] ?? null;
        $nomEntreprise = $data['nomEntreprise'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $passwordConfirmation =
            $data['passwordConfirmation'] ?? null;
        $acceptTerms = $data['acceptTerms'] ?? null;
        $acceptPrivacy = $data['acceptPrivacy'] ?? null;

        $errors = [];

        if (!is_string($prenom) || trim($prenom) === '') {
            $errors[] = [
                'field' => 'prenom',
                'message' => 'Le prénom est obligatoire.',
            ];
        }

        if (!is_string($nom) || trim($nom) === '') {
            $errors[] = [
                'field' => 'nom',
                'message' => 'Le nom est obligatoire.',
            ];
        }

        if (!is_string($email) || trim($email) === '') {
            $errors[] = [
                'field' => 'email',
                'message' => 'L’adresse e-mail est obligatoire.',
            ];
        }

        if (!is_string($password) || $password === '') {
            $errors[] = [
                'field' => 'password',
                'message' => 'Le mot de passe est obligatoire.',
            ];
        } else {
            if (mb_strlen($password) < 12) {
                $errors[] = [
                    'field' => 'password',
                    'message' =>
                    'Le mot de passe doit contenir au moins 12 caractères.',
                ];
            }

            if (mb_strlen($password) > 4096) {
                $errors[] = [
                    'field' => 'password',
                    'message' =>
                    'Le mot de passe ne peut pas dépasser 4096 caractères.',
                ];
            }
        }

        if (
            !is_string($passwordConfirmation)
            || $passwordConfirmation === ''
        ) {
            $errors[] = [
                'field' => 'passwordConfirmation',
                'message' =>
                'La confirmation du mot de passe est obligatoire.',
            ];
        } elseif (
            is_string($password)
            && $password !== $passwordConfirmation
        ) {
            $errors[] = [
                'field' => 'passwordConfirmation',
                'message' =>
                'La confirmation du mot de passe ne correspond pas.',
            ];
        }

        if (
            $nomEntreprise !== null
            && !is_string($nomEntreprise)
        ) {
            $errors[] = [
                'field' => 'nomEntreprise',
                'message' =>
                'Le nom de l’entreprise doit être une chaîne de caractères.',
            ];
        }

        if ($acceptTerms !== true) {
            $errors[] = [
                'field' => 'acceptTerms',
                'message' =>
                'Vous devez accepter les conditions d’utilisation.',
            ];
        }

        if ($acceptPrivacy !== true) {
            $errors[] = [
                'field' => 'acceptPrivacy',
                'message' =>
                'Vous devez accepter la politique de confidentialité.',
            ];
        }

        if ($errors !== []) {
            return $this->json(
                ['errors' => $errors],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $user = new User();

        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );

        $userErrors = $validator->validate(
            $user,
            null,
            ['Default', 'registration']
        );

        if (count($userErrors) > 0) {
            return $this->json(
                [
                    'errors' => $this->formatErrors($userErrors),
                ],
                $this->getValidationStatus($userErrors)
            );
        }

        $entreprise = null;

        if (
            is_string($nomEntreprise)
            && trim($nomEntreprise) !== ''
        ) {
            $entreprise = new Entreprise();
            $entreprise->setNom(trim($nomEntreprise));
            $user->setEntreprise($entreprise);

            $entrepriseErrors = $validator->validate($entreprise);

            if (count($entrepriseErrors) > 0) {
                return $this->json(
                    [
                        'errors' =>
                        $this->formatErrors($entrepriseErrors),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        try {
            $entityManager->persist($user);

            if ($entreprise !== null) {
                $entityManager->persist($entreprise);
            }

            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
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
                    'prenom' => $user->getPrenom(),
                    'nom' => $user->getNom(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
                'entreprise' => $entreprise === null
                    ? null
                    : [
                        'id' => $entreprise->getId(),
                        'nom' => $entreprise->getNom(),
                        'complete' => $entreprise->isComplete(),
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
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'entrepriseConfiguree' =>
            $user->getEntreprise()?->isComplete() ?? false,
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

    private function getValidationStatus(
        ConstraintViolationListInterface $errors
    ): int {
        foreach ($errors as $error) {
            if ($error->getCode() === UniqueEntity::NOT_UNIQUE_ERROR) {
                return JsonResponse::HTTP_CONFLICT;
            }
        }

        return JsonResponse::HTTP_BAD_REQUEST;
    }
}
