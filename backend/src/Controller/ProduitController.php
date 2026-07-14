<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProduitController extends AbstractController
{
    #[Route('/api/produits', name: 'api_produits_list', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $produits = $entityManager
            ->getRepository(Produit::class)
            ->findBy(['user' => $user]);

        $data = array_map(
            static fn(Produit $produit): array => [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'description' => $produit->getDescription(),
                'type' => $produit->getType(),
                'reference' => $produit->getReference(),
                'prixHT' => $produit->getPrixHT(),
                'tva' => $produit->getTva(),
                'unite' => $produit->getUnite(),
                'actif' => $produit->isActif(),
                'createdAt' => $produit->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $produit->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ],
            $produits
        );

        return $this->json($data);
    }

    #[Route('/api/produits', name: 'api_produits_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $produit = new Produit();

        $produit->setNom(trim((string) ($data['nom'] ?? '')));
        $produit->setDescription(
            isset($data['description']) ? (string) $data['description'] : null
        );
        $produit->setType((string) ($data['type'] ?? 'produit'));
        $produit->setReference(trim((string) ($data['reference'] ?? '')));
        $produit->setPrixHT((string) ($data['prixHT'] ?? '0.00'));
        $produit->setTva((string) ($data['tva'] ?? '20.00'));
        $produit->setUnite(
            isset($data['unite']) ? (string) $data['unite'] : null
        );
        $produit->setActif((bool) ($data['actif'] ?? true));
        $produit->setUser($user);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Produit créé avec succès.',
                'produit' => [
                    'id' => $produit->getId(),
                    'nom' => $produit->getNom(),
                    'description' => $produit->getDescription(),
                    'type' => $produit->getType(),
                    'reference' => $produit->getReference(),
                    'prixHT' => $produit->getPrixHT(),
                    'tva' => $produit->getTva(),
                    'unite' => $produit->getUnite(),
                    'actif' => $produit->isActif(),
                ],
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

        return $this->json([
            'id' => $produit->getId(),
            'nom' => $produit->getNom(),
            'description' => $produit->getDescription(),
            'type' => $produit->getType(),
            'reference' => $produit->getReference(),
            'prixHT' => $produit->getPrixHT(),
            'tva' => $produit->getTva(),
            'unite' => $produit->getUnite(),
            'actif' => $produit->isActif(),
            'createdAt' => $produit->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $produit->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
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
            $produit->setNom(trim((string) $data['nom']));
        }

        if (array_key_exists('description', $data)) {
            $produit->setDescription($data['description']);
        }

        if (array_key_exists('type', $data)) {
            $produit->setType((string) $data['type']);
        }

        if (array_key_exists('reference', $data)) {
            $produit->setReference(trim((string) $data['reference']));
        }

        if (array_key_exists('prixHT', $data)) {
            $produit->setPrixHT((string) $data['prixHT']);
        }

        if (array_key_exists('tva', $data)) {
            $produit->setTva((string) $data['tva']);
        }

        if (array_key_exists('unite', $data)) {
            $produit->setUnite($data['unite']);
        }

        if (array_key_exists('actif', $data)) {
            $produit->setActif((bool) $data['actif']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Produit modifié avec succès.',
            'produit' => [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'description' => $produit->getDescription(),
                'type' => $produit->getType(),
                'reference' => $produit->getReference(),
                'prixHT' => $produit->getPrixHT(),
                'tva' => $produit->getTva(),
                'unite' => $produit->getUnite(),
                'actif' => $produit->isActif(),
                'updatedAt' => $produit->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ],
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
            'message' => 'Produit supprimé avec succès.'
        ]);
    }
}
