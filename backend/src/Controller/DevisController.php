<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\User;
use App\Enum\StatutDevis;
use App\Service\NumerotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class DevisController extends AbstractController
{
    #[Route('/api/devis', name: 'api_devis_list', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $devis = $entityManager
            ->getRepository(Devis::class)
            ->findBy(['user' => $user]);

        $data = array_map(
            static fn(Devis $devis): array => [
                'id' => $devis->getId(),
                'numero' => $devis->getNumero(),
                'dateEmission' => $devis->getDateEmission()?->format('Y-m-d'),
                'dateValidite' => $devis->getDateValidite()?->format('Y-m-d'),
                'statut' => $devis->getStatut()->value,
                'totalHT' => $devis->getTotalHT(),
                'totalTVA' => $devis->getTotalTVA(),
                'totalTTC' => $devis->getTotalTTC(),
                'commentaire' => $devis->getCommentaire(),
                'client' => [
                    'id' => $devis->getClient()?->getId(),
                    'nom' => $devis->getClient()?->getNom(),
                    'prenom' => $devis->getClient()?->getPrenom(),
                    'entreprise' => $devis->getClient()?->getEntreprise(),
                ],
                'createdAt' => $devis->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $devis->getUpdatedAt()?->format(DATE_ATOM),
            ],
            $devis
        );

        return $this->json($data);
    }
    #[Route('/api/devis', name: 'api_devis_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        NumerotationService $numerotationService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $client = $entityManager
            ->getRepository(Client::class)
            ->find($data['clientId'] ?? 0);

        if (!$client || $client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Client introuvable ou accès refusé.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $devis = new Devis();

        $devis->setNumero(
            $numerotationService->genererNumeroDevis($user)
        );

        $devis->setDateEmission(new \DateTimeImmutable());
        $devis->setDateValidite(new \DateTimeImmutable('+30 days'));
        $devis->setStatut(StatutDevis::BROUILLON);
        $devis->setTotalHT('0.00');
        $devis->setTotalTVA('0.00');
        $devis->setTotalTTC('0.00');
        $devis->setCommentaire($data['commentaire'] ?? null);
        $devis->setClient($client);
        $devis->setUser($user);

        $entityManager->persist($devis);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Devis créé avec succès.',
                'id' => $devis->getId(),
                'numero' => $devis->getNumero(),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/devis/{id}', name: 'api_devis_show', methods: ['GET'])]
    public function show(
        Devis $devis,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json([
            'id' => $devis->getId(),
            'numero' => $devis->getNumero(),
            'dateEmission' => $devis->getDateEmission()?->format('Y-m-d'),
            'dateValidite' => $devis->getDateValidite()?->format('Y-m-d'),
            'statut' => $devis->getStatut()->value,
            'totalHT' => $devis->getTotalHT(),
            'totalTVA' => $devis->getTotalTVA(),
            'totalTTC' => $devis->getTotalTTC(),
            'commentaire' => $devis->getCommentaire(),
            'client' => [
                'id' => $devis->getClient()?->getId(),
                'nom' => $devis->getClient()?->getNom(),
                'prenom' => $devis->getClient()?->getPrenom(),
                'entreprise' => $devis->getClient()?->getEntreprise(),
            ],
            'createdAt' => $devis->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $devis->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/devis/{id}', name: 'api_devis_update', methods: ['PUT'])]
    public function update(
        Devis $devis,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('clientId', $data)) {
            $client = $entityManager
                ->getRepository(Client::class)
                ->find((int) $data['clientId']);

            if (!$client || $client->getUser() !== $user) {
                return $this->json(
                    ['message' => 'Client introuvable ou accès refusé.'],
                    JsonResponse::HTTP_NOT_FOUND
                );
            }

            $devis->setClient($client);
        }

        if (array_key_exists('dateEmission', $data)) {
            $devis->setDateEmission(
                new \DateTimeImmutable((string) $data['dateEmission'])
            );
        }

        if (array_key_exists('dateValidite', $data)) {
            $devis->setDateValidite(
                new \DateTimeImmutable((string) $data['dateValidite'])
            );
        }

        if (array_key_exists('statut', $data)) {
            $statut = StatutDevis::tryFrom((string) $data['statut']);

            if ($statut === null) {
                return $this->json(
                    ['message' => 'Statut de devis invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $devis->setStatut($statut);
        }

        if (array_key_exists('commentaire', $data)) {
            $devis->setCommentaire(
                $data['commentaire'] !== null
                    ? (string) $data['commentaire']
                    : null
            );
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Devis modifié avec succès.',
            'devis' => [
                'id' => $devis->getId(),
                'numero' => $devis->getNumero(),
                'dateEmission' => $devis->getDateEmission()?->format('Y-m-d'),
                'dateValidite' => $devis->getDateValidite()?->format('Y-m-d'),
                'statut' => $devis->getStatut()->value,
                'totalHT' => $devis->getTotalHT(),
                'totalTVA' => $devis->getTotalTVA(),
                'totalTTC' => $devis->getTotalTTC(),
                'commentaire' => $devis->getCommentaire(),
                'client' => [
                    'id' => $devis->getClient()?->getId(),
                    'nom' => $devis->getClient()?->getNom(),
                    'prenom' => $devis->getClient()?->getPrenom(),
                    'entreprise' => $devis->getClient()?->getEntreprise(),
                ],
                'updatedAt' => $devis->getUpdatedAt()?->format(DATE_ATOM),
            ],
        ]);
    }

    #[Route('/api/devis/{id}', name: 'api_devis_delete', methods: ['DELETE'])]
    public function delete(
        Devis $devis,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $entityManager->remove($devis);
        $entityManager->flush();

        return $this->json([
            'message' => 'Devis supprimé avec succès.',
        ]);
    }
}
