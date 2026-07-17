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
            trim(
                (string) $request->query->get('filtre', 'tous')
            )
        );

        $tri = trim(
            (string) $request->query->get('tri', 'nom')
        );

        $ordre = strtoupper(
            trim(
                (string) $request->query->get('ordre', 'ASC')
            )
        );

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
                [
                    'message' => 'L’ordre doit être ASC ou DESC.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $produits = $produitRepository->findForUserWithFilters(
            user: $user,
            recherche: $recherche,
            filtre: $filtre,
            tri: $tri,
            ordre: $ordre
        );

        $data = array_map(
            fn(Produit $produit): array =>
            $this->transformerProduit($produit),
            $produits
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche !== ''
                    ? $recherche
                    : null,
                'filtre' => $filtre,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'nombreResultats' => count($data),
            'produits' => $data,
        ]);
    }

    #[Route('/api/produits', name: 'api_produits_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $nom = trim((string) ($data['nom'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $type = strtolower(
            trim((string) ($data['type'] ?? 'produit'))
        );

        if ($nom === '') {
            return $this->json(
                ['message' => 'Le nom est obligatoire.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($reference === '') {
            return $this->json(
                ['message' => 'La référence est obligatoire.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($type, ['produit', 'service'], true)) {
            return $this->json(
                [
                    'message' =>
                    'Le type doit être produit ou service.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $produit = new Produit();

        $produit->setNom($nom);

        $produit->setDescription(
            isset($data['description'])
                ? (string) $data['description']
                : null
        );

        $produit->setType($type);
        $produit->setReference($reference);

        $produit->setPrixHT(
            (string) ($data['prixHT'] ?? '0.00')
        );

        $produit->setTva(
            (string) ($data['tva'] ?? '20.00')
        );

        $produit->setUnite(
            isset($data['unite'])
                ? (string) $data['unite']
                : null
        );

        $produit->setActif(
            (bool) ($data['actif'] ?? true)
        );

        $produit->setUser($user);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Produit créé avec succès.',
                'produit' =>
                $this->transformerProduit($produit),
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
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json(
            $this->transformerProduit($produit)
        );
    }

    #[Route('/api/produits/{id}', name: 'api_produits_update', methods: ['PUT'])]
    public function update(
        Produit $produit,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('nom', $data)) {
            $nom = trim((string) $data['nom']);

            if ($nom === '') {
                return $this->json(
                    ['message' => 'Le nom est obligatoire.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $produit->setNom($nom);
        }

        if (array_key_exists('description', $data)) {
            $produit->setDescription(
                $data['description'] !== null
                    ? (string) $data['description']
                    : null
            );
        }

        if (array_key_exists('type', $data)) {
            $type = strtolower(
                trim((string) $data['type'])
            );

            if (!in_array($type, ['produit', 'service'], true)) {
                return $this->json(
                    [
                        'message' =>
                        'Le type doit être produit ou service.',
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $produit->setType($type);
        }

        if (array_key_exists('reference', $data)) {
            $reference = trim((string) $data['reference']);

            if ($reference === '') {
                return $this->json(
                    ['message' => 'La référence est obligatoire.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $produit->setReference($reference);
        }

        if (array_key_exists('prixHT', $data)) {
            $produit->setPrixHT(
                (string) $data['prixHT']
            );
        }

        if (array_key_exists('tva', $data)) {
            $produit->setTva(
                (string) $data['tva']
            );
        }

        if (array_key_exists('unite', $data)) {
            $produit->setUnite(
                $data['unite'] !== null
                    ? (string) $data['unite']
                    : null
            );
        }

        if (array_key_exists('actif', $data)) {
            $produit->setActif(
                (bool) $data['actif']
            );
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Produit modifié avec succès.',
            'produit' =>
            $this->transformerProduit($produit),
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
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $entityManager->remove($produit);
        $entityManager->flush();

        return $this->json([
            'message' => 'Produit supprimé avec succès.',
        ]);
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
