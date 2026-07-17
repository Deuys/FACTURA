<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Enum\StatutFacture;
use App\Service\CalculTotauxService;
use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\User;
use App\Enum\StatutDevis;
use App\Service\NumerotationService;
use App\Service\ActiviteService;
use App\Repository\DevisRepository;
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
        Request $request,
        DevisRepository $devisRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $recherche = $request->query->getString('recherche');
        $statutParam = $request->query->get('statut');
        $tri = $request->query->getString('tri', 'dateEmission');
        $ordre = strtoupper(
            $request->query->getString('ordre', 'DESC')
        );

        $statut = null;

        if (
            is_string($statutParam)
            && trim($statutParam) !== ''
            && strtolower($statutParam) !== 'tous'
        ) {
            $statut = StatutDevis::tryFrom($statutParam);

            if ($statut === null) {
                return $this->json(
                    [
                        'message' => 'Statut de devis invalide.',
                        'statutsAutorises' => array_map(
                            static fn(StatutDevis $statut): string =>
                            $statut->value,
                            StatutDevis::cases()
                        ),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (!in_array($ordre, ['ASC', 'DESC'], true)) {
            return $this->json(
                [
                    'message' => 'Ordre de tri invalide.',
                    'ordresAutorises' => ['ASC', 'DESC'],
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $devis = $devisRepository->findForUserWithFilters(
            user: $user,
            recherche: $recherche,
            statut: $statut,
            tri: $tri,
            ordre: $ordre
        );

        $data = array_map(
            static fn(Devis $devis): array => [
                'id' => $devis->getId(),
                'numero' => $devis->getNumero(),
                'dateEmission' => $devis
                    ->getDateEmission()
                    ?->format('Y-m-d'),
                'dateValidite' => $devis
                    ->getDateValidite()
                    ?->format('Y-m-d'),
                'statut' => $devis->getStatut()->value,
                'totalHT' => $devis->getTotalHT(),
                'totalTVA' => $devis->getTotalTVA(),
                'totalTTC' => $devis->getTotalTTC(),
                'commentaire' => $devis->getCommentaire(),
                'client' => [
                    'id' => $devis->getClient()?->getId(),
                    'nom' => $devis->getClient()?->getNom(),
                    'prenom' => $devis->getClient()?->getPrenom(),
                    'entreprise' => $devis
                        ->getClient()
                        ?->getEntreprise(),
                ],
                'createdAt' => $devis
                    ->getCreatedAt()
                    ?->format(DATE_ATOM),
                'updatedAt' => $devis
                    ->getUpdatedAt()
                    ?->format(DATE_ATOM),
            ],
            $devis
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche,
                'statut' => $statut?->value,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'nombreResultats' => count($data),
            'devis' => $data,
        ]);
    }
    #[Route('/api/devis', name: 'api_devis_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        NumerotationService $numerotationService,
        ActiviteService $activiteService,
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
        $activiteService->enregistrer(
            user: $user,
            type: 'devis_cree',
            titre: 'Nouveau devis créé',
            description: sprintf(
                '%s • %s',
                $devis->getNumero(),
                $devis->getClient()?->getEntreprise()
                    ?: trim(
                        sprintf(
                            '%s %s',
                            $devis->getClient()?->getPrenom() ?? '',
                            $devis->getClient()?->getNom() ?? ''
                        )
                    )
            )
        );

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

    #[Route(
        '/api/devis/{id}/transformer',
        name: 'api_devis_transformer',
        methods: ['POST']
    )]
    public function transformerEnFacture(
        Devis $devis,
        EntityManagerInterface $entityManager,
        NumerotationService $numerotationService,
        CalculTotauxService $calculTotauxService,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à ce devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        if ($devis->getStatut() === StatutDevis::TRANSFORME) {
            return $this->json(
                ['message' => 'Ce devis a déjà été transformé en facture.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        if ($devis->getStatut() !== StatutDevis::ACCEPTE) {
            return $this->json(
                [
                    'message' =>
                    'Seul un devis accepté peut être transformé en facture.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($devis->getLigneDevis()->isEmpty()) {
            return $this->json(
                [
                    'message' =>
                    'Impossible de transformer un devis sans ligne.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $aujourdhui = new \DateTimeImmutable('today');

        $delaiPaiement = $user
            ->getEntreprise()
            ?->getDelaiPaiement() ?? 30;

        $dateEcheance = $aujourdhui->modify(
            sprintf('+%d days', $delaiPaiement)
        );

        $facture = new Facture();

        $facture->setNumero(
            $numerotationService->genererNumeroFacture($user)
        );

        $facture->setDateEmission($aujourdhui);
        $facture->setDateEmissionPrevue(null);
        $facture->setDateEcheance($dateEcheance);
        $facture->setStatut(StatutFacture::BROUILLON);
        $facture->setClient($devis->getClient());
        $facture->setUser($user);
        $facture->setCommentaire(
            sprintf(
                'Facture créée depuis le devis %s.',
                $devis->getNumero()
            )
        );

        foreach ($devis->getLigneDevis() as $ligneDevis) {
            $ligneFacture = new LigneFacture();

            $ligneFacture->setProduit($ligneDevis->getProduit());
            $ligneFacture->setDesignation(
                (string) $ligneDevis->getDesignation()
            );
            $ligneFacture->setDescription(
                $ligneDevis->getDescription()
            );
            $ligneFacture->setUnite(
                $ligneDevis->getUnite()
            );
            $ligneFacture->setQuantite(
                (string) $ligneDevis->getQuantite()
            );
            $ligneFacture->setPrixUnitaireHT(
                (string) $ligneDevis->getPrixUnitaireHT()
            );
            $ligneFacture->setTva(
                (string) $ligneDevis->getTva()
            );
            $ligneFacture->setRemise(
                $ligneDevis->getRemise()
            );

            $facture->addLigneFacture($ligneFacture);
        }

        $calculTotauxService->recalculerFacture($facture);

        $devis->setStatut(StatutDevis::TRANSFORME);

        $entityManager->persist($facture);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Facture créée avec succès.',
                'devis' => [
                    'id' => $devis->getId(),
                    'numero' => $devis->getNumero(),
                    'statut' => $devis->getStatut()->value,
                ],
                'facture' => [
                    'id' => $facture->getId(),
                    'numero' => $facture->getNumero(),
                    'statut' => $facture->getStatut()->value,
                    'dateEmission' => $facture
                        ->getDateEmission()
                        ?->format('Y-m-d'),
                    'dateEcheance' => $facture
                        ->getDateEcheance()
                        ?->format('Y-m-d'),
                    'totalHT' => $facture->getTotalHT(),
                    'totalTVA' => $facture->getTotalTVA(),
                    'totalTTC' => $facture->getTotalTTC(),
                ],
            ],
            JsonResponse::HTTP_CREATED
        );
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
