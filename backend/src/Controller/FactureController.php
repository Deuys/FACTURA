<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\User;
use App\Enum\StatutFacture;
use App\Service\NumerotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class FactureController extends AbstractController
{
    #[Route('/api/factures', name: 'api_factures_list', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $statut = null;

        if ($request->query->has('statut')) {
            $statut = StatutFacture::tryFrom(
                (string) $request->query->get('statut')
            );

            if ($statut === null) {
                return $this->json(
                    ['message' => 'Le statut demandé est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $ordre = strtoupper(
            (string) $request->query->get('sort', 'DESC')
        );

        $factures = $entityManager
            ->getRepository(Facture::class)
            ->findForUserWithFilters(
                $user,
                $statut,
                $ordre
            );

        $data = array_map(
            fn(Facture $facture): array => $this->transformerFacture($facture),
            $factures
        );

        return $this->json($data);
    }

    #[Route('/api/factures', name: 'api_factures_create', methods: ['POST'])]
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

        if ($client === null || $client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Client introuvable ou accès refusé.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $dateEmission = new \DateTimeImmutable();

        if (!empty($data['dateEmission'])) {
            try {
                $dateEmission = new \DateTimeImmutable(
                    (string) $data['dateEmission']
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’émission est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $dateEmissionPrevue = null;

        if (!empty($data['dateEmissionPrevue'])) {
            try {
                $dateEmissionPrevue = new \DateTimeImmutable(
                    (string) $data['dateEmissionPrevue']
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’émission prévue est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }
        $dateEcheance = $dateEmission->modify('+30 days');

        if (!empty($data['dateEcheance'])) {
            try {
                $dateEcheance = new \DateTimeImmutable(
                    (string) $data['dateEcheance']
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’échéance est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if ($dateEcheance < $dateEmission) {
            return $this->json(
                [
                    'message' =>
                    'La date d’échéance doit être postérieure ou égale à la date d’émission.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $facture = new Facture();

        $facture->setNumero(
            $numerotationService->genererNumeroFacture($user)
        );

        $facture->setDateEmission($dateEmission);
        $facture->setDateEmissionPrevue($dateEmissionPrevue);
        $facture->setDateEcheance($dateEcheance);
        $statut = StatutFacture::BROUILLON;

        if (!empty($data['statut'])) {
            $statut = StatutFacture::tryFrom((string) $data['statut']);

            if ($statut === null) {
                return $this->json(
                    ['message' => 'Statut invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $facture->setStatut($statut);
        if (
            $statut === StatutFacture::PLANIFIEE &&
            $dateEmissionPrevue === null
        ) {
            return $this->json(
                [
                    'message' =>
                    'Une facture planifiée doit posséder une date d’émission prévue.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (
            $statut !== StatutFacture::PLANIFIEE &&
            $dateEmissionPrevue !== null
        ) {
            return $this->json(
                [
                    'message' =>
                    'La date d’émission prévue est réservée aux factures planifiées.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }
        $facture->setTotalHT('0.00');
        $facture->setTotalTVA('0.00');
        $facture->setTotalTTC('0.00');
        $facture->setCommentaire(
            isset($data['commentaire'])
                ? trim((string) $data['commentaire'])
                : null
        );
        $facture->setClient($client);
        $facture->setUser($user);

        $entityManager->persist($facture);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Facture créée avec succès.',
                'facture' => $this->transformerFacture($facture),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/factures/{id}', name: 'api_factures_show', methods: ['GET'])]
    public function show(
        Facture $facture,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json($this->transformerFacture($facture));
    }

    #[Route('/api/factures/{id}', name: 'api_factures_update', methods: ['PUT'])]
    public function update(
        Facture $facture,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('clientId', $data)) {
            $client = $entityManager
                ->getRepository(Client::class)
                ->find($data['clientId']);

            if ($client === null || $client->getUser() !== $user) {
                return $this->json(
                    ['message' => 'Client introuvable ou accès refusé.'],
                    JsonResponse::HTTP_NOT_FOUND
                );
            }

            $facture->setClient($client);
        }

        if (array_key_exists('dateEmission', $data)) {
            try {
                $facture->setDateEmission(
                    new \DateTimeImmutable((string) $data['dateEmission'])
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’émission est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }
        if (array_key_exists('dateEmissionPrevue', $data)) {
            try {
                $facture->setDateEmissionPrevue(
                    $data['dateEmissionPrevue'] !== null
                        && trim((string) $data['dateEmissionPrevue']) !== ''
                        ? new \DateTimeImmutable(
                            (string) $data['dateEmissionPrevue']
                        )
                        : null
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’émission prévue est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }
        if (array_key_exists('dateEcheance', $data)) {
            try {
                $facture->setDateEcheance(
                    new \DateTimeImmutable((string) $data['dateEcheance'])
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date d’échéance est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if ($facture->getDateEcheance() < $facture->getDateEmission()) {
            return $this->json(
                [
                    'message' =>
                    'La date d’échéance doit être postérieure ou égale à la date d’émission.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (array_key_exists('statut', $data)) {
            $statut = StatutFacture::tryFrom((string) $data['statut']);

            if ($statut === null) {
                return $this->json(
                    ['message' => 'Le statut de la facture est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $facture->setStatut($statut);
        }

        if (
            $facture->getStatut() === StatutFacture::PLANIFIEE
            && $facture->getDateEmissionPrevue() === null
        ) {
            return $this->json(
                [
                    'message' =>
                    'Une facture planifiée doit posséder une date d’émission prévue.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (
            $facture->getStatut() !== StatutFacture::PLANIFIEE
            && $facture->getDateEmissionPrevue() !== null
        ) {
            return $this->json(
                [
                    'message' =>
                    'La date d’émission prévue est réservée aux factures planifiées.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (array_key_exists('commentaire', $data)) {
            $facture->setCommentaire(
                $data['commentaire'] !== null
                    ? trim((string) $data['commentaire'])
                    : null
            );
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Facture modifiée avec succès.',
            'facture' => $this->transformerFacture($facture),
        ]);
    }

    #[Route('/api/factures/{id}', name: 'api_factures_delete', methods: ['DELETE'])]
    public function delete(
        Facture $facture,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $entityManager->remove($facture);
        $entityManager->flush();

        return $this->json([
            'message' => 'Facture supprimée avec succès.',
        ]);
    }

    private function transformerFacture(Facture $facture): array
    {
        return [
            'id' => $facture->getId(),
            'numero' => $facture->getNumero(),
            'dateEmission' => $facture
                ->getDateEmission()
                ?->format('Y-m-d'),
            'dateEmissionPrevue' => $facture
                ->getDateEmissionPrevue()
                ?->format('Y-m-d'),
            'dateEcheance' => $facture
                ->getDateEcheance()
                ?->format('Y-m-d'),
            'statut' => $facture->getStatut()->value,
            'totalHT' => $facture->getTotalHT(),
            'totalTVA' => $facture->getTotalTVA(),
            'totalTTC' => $facture->getTotalTTC(),
            'commentaire' => $facture->getCommentaire(),
            'client' => [
                'id' => $facture->getClient()?->getId(),
                'nom' => $facture->getClient()?->getNom(),
                'prenom' => $facture->getClient()?->getPrenom(),
                'entreprise' => $facture->getClient()?->getEntreprise(),
            ],
            'createdAt' => $facture
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $facture
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }
}
