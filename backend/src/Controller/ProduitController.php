<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\User;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProduitController extends AbstractController
{
    #[Route('/api/produits', name: 'api_produits_list', methods: ['GET'])]
    public function index(
        Request $request,
        ProduitRepository $produitRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $recherche = trim(
            (string) $request->query->get('recherche', '')
        );

        $filtre = strtolower(
            trim((string) $request->query->get('filtre', 'tous'))
        );

        $tri = trim(
            (string) $request->query->get('tri', 'nom')
        );

        $ordre = strtoupper(
            trim((string) $request->query->get('ordre', 'ASC'))
        );

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        if (!$produitRepository->isFilterAllowed($filtre)) {
            return $this->json(
                [
                    'message' => 'Le filtre demandé est invalide.',
                    'filtresAutorises' =>
                    $produitRepository->getAllowedFilters(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!$produitRepository->isSortFieldAllowed($tri)) {
            return $this->json(
                [
                    'message' => 'Le champ de tri demandé est invalide.',
                    'trisAutorises' =>
                    $produitRepository->getAllowedSortFields(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($ordre, ['ASC', 'DESC'], true)) {
            return $this->json(
                ['message' => 'L’ordre doit être ASC ou DESC.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($page < 1) {
            return $this->json(
                ['message' => 'Le numéro de page doit être supérieur à 0.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($limit < 1 || $limit > 100) {
            return $this->json(
                [
                    'message' =>
                    'Le nombre de résultats par page doit être compris entre 1 et 100.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $resultat = $produitRepository->findForUserWithFilters(
            user: $user,
            recherche: $recherche,
            filtre: $filtre,
            tri: $tri,
            ordre: $ordre,
            page: $page,
            limit: $limit
        );

        $produits = $resultat['produits'];
        $total = $resultat['total'];

        $nombrePages = $total > 0
            ? (int) ceil($total / $limit)
            : 0;

        $data = array_map(
            fn(Produit $produit): array =>
            $this->transformerProduit($produit),
            $produits
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche !== '' ? $recherche : null,
                'filtre' => $filtre,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'nombrePages' => $nombrePages,
                'nombreResultats' => count($data),
                'pagePrecedente' => $page > 1 ? $page - 1 : null,
                'pageSuivante' => $page < $nombrePages ? $page + 1 : null,
            ],
            'produits' => $data,
        ]);
    }

    #[Route('/api/produits', name: 'api_produits_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ProduitRepository $produitRepository,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $typeError = $this->validatePayloadTypes($data);

        if ($typeError !== null) {
            return $typeError;
        }

        $reference = $this->normalizeRequiredString(
            $data['reference'] ?? null
        );

        if (
            $reference !== ''
            && $produitRepository->referenceExistsForUser($user, $reference)
        ) {
            return $this->json(
                ['message' => 'Cette référence est déjà utilisée.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $produit = new Produit();
        $produit->setNom(
            $this->normalizeRequiredString($data['nom'] ?? null)
        );
        $produit->setDescription(
            $this->normalizeNullableString($data['description'] ?? null)
        );
        $produit->setType(
            strtolower(
                $this->normalizeRequiredString($data['type'] ?? 'produit')
            )
        );
        $produit->setReference($reference);
        $produit->setPrixHT(
            $this->normalizeDecimal($data['prixHT'] ?? '0.00')
        );
        $produit->setTva(
            $this->normalizeDecimal($data['tva'] ?? '20.00')
        );
        $produit->setUnite(
            $this->normalizeNullableString($data['unite'] ?? null)
        );
        $produit->setActif(
            $this->normalizeBoolean($data['actif'] ?? true)
        );
        $produit->setUser($user);

        $validationResponse = $this->validateProduit($produit, $validator);

        if ($validationResponse !== null) {
            return $validationResponse;
        }

        $entityManager->persist($produit);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Produit créé avec succès.',
                'produit' => $this->transformerProduit($produit),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/produits/{id}', name: 'api_produits_show', methods: ['GET'])]
    public function show(
        Produit $produit,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Produit introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->transformerProduit($produit));
    }

    #[Route('/api/produits/{id}', name: 'api_produits_update', methods: ['PUT'])]
    public function update(
        Produit $produit,
        Request $request,
        EntityManagerInterface $entityManager,
        ProduitRepository $produitRepository,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Produit introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        $typeError = $this->validatePayloadTypes($data);

        if ($typeError !== null) {
            return $typeError;
        }

        if (array_key_exists('nom', $data)) {
            $produit->setNom(
                $this->normalizeRequiredString($data['nom'])
            );
        }

        if (array_key_exists('description', $data)) {
            $produit->setDescription(
                $this->normalizeNullableString($data['description'])
            );
        }

        if (array_key_exists('type', $data)) {
            $produit->setType(
                strtolower(
                    $this->normalizeRequiredString($data['type'])
                )
            );
        }

        if (array_key_exists('reference', $data)) {
            $reference = $this->normalizeRequiredString(
                $data['reference']
            );

            if (
                $reference !== ''
                && $produitRepository->referenceExistsForUser(
                    $user,
                    $reference,
                    $produit->getId()
                )
            ) {
                return $this->json(
                    ['message' => 'Cette référence est déjà utilisée.'],
                    JsonResponse::HTTP_CONFLICT
                );
            }

            $produit->setReference($reference);
        }

        if (array_key_exists('prixHT', $data)) {
            $produit->setPrixHT(
                $this->normalizeDecimal($data['prixHT'])
            );
        }

        if (array_key_exists('tva', $data)) {
            $produit->setTva(
                $this->normalizeDecimal($data['tva'])
            );
        }

        if (array_key_exists('unite', $data)) {
            $produit->setUnite(
                $this->normalizeNullableString($data['unite'])
            );
        }

        if (array_key_exists('actif', $data)) {
            $produit->setActif(
                $this->normalizeBoolean($data['actif'])
            );
        }

        $validationResponse = $this->validateProduit($produit, $validator);

        if ($validationResponse !== null) {
            return $validationResponse;
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Produit modifié avec succès.',
            'produit' => $this->transformerProduit($produit),
        ]);
    }

    #[Route('/api/produits/{id}', name: 'api_produits_delete', methods: ['DELETE'])]
    public function delete(
        Produit $produit,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Produit introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        if (
            !$produit->getLigneFactures()->isEmpty()
            || !$produit->getLigneDevis()->isEmpty()
        ) {
            return $this->json(
                [
                    'message' =>
                    'Ce produit est déjà utilisé dans une facture ou un devis. Désactivez-le au lieu de le supprimer.',
                ],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $entityManager->remove($produit);
        $entityManager->flush();

        return $this->json([
            'message' => 'Produit supprimé avec succès.',
        ]);
    }

    private function validatePayloadTypes(array $data): ?JsonResponse
    {
        $stringFields = [
            'nom',
            'description',
            'type',
            'reference',
            'unite',
        ];

        foreach ($stringFields as $field) {
            if (
                array_key_exists($field, $data)
                && $data[$field] !== null
                && !is_string($data[$field])
            ) {
                return $this->json(
                    [
                        'message' =>
                        sprintf('Le champ "%s" doit être une chaîne de caractères.', $field),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        foreach (['prixHT', 'tva'] as $field) {
            if (
                array_key_exists($field, $data)
                && !is_int($data[$field])
                && !is_float($data[$field])
                && !is_string($data[$field])
            ) {
                return $this->json(
                    [
                        'message' =>
                        sprintf('Le champ "%s" doit être un nombre.', $field),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (
            array_key_exists('actif', $data)
            && !$this->isBooleanLike($data['actif'])
        ) {
            return $this->json(
                ['message' => 'Le champ "actif" doit être un booléen.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        return null;
    }

    private function validateProduit(
        Produit $produit,
        ValidatorInterface $validator
    ): ?JsonResponse {
        $violations = $validator->validate($produit);

        if (count($violations) === 0) {
            return null;
        }

        $erreurs = [];

        foreach ($violations as $violation) {
            $erreurs[$violation->getPropertyPath()][] =
                $violation->getMessage();
        }

        return $this->json(
            [
                'message' => 'Les données du produit sont invalides.',
                'erreurs' => $erreurs,
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    private function normalizeRequiredString(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeDecimal(mixed $value): string
    {
        return str_replace(',', '.', trim((string) $value));
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        return false;
    }

    private function isBooleanLike(mixed $value): bool
    {
        return in_array(
            $value,
            [true, false, 1, 0, '1', '0', 'true', 'false'],
            true
        );
    }

    private function transformerProduit(
        Produit $produit
    ): array {
        return [
            'id' => $produit->getId(),
            'nom' => $produit->getNom(),
            'description' => $produit->getDescription(),
            'type' => $produit->getType(),
            'reference' => $produit->getReference(),
            'prixHT' => $produit->getPrixHT(),
            'tva' => $produit->getTva(),
            'unite' => $produit->getUnite(),
            'actif' => $produit->isActif(),
            'createdAt' => $produit
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $produit
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }
}
