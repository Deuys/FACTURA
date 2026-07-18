<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\User;
use App\Enum\StatutFacture;
use App\Service\NumerotationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\FacturePdfService;
use App\Service\FactureMailerService;
use App\Repository\FactureRepository;
use App\Entity\LigneFacture;
use App\Service\ActiviteService;
use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\Response;
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
        FactureRepository $factureRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $statut = null;

        $statutParametre = trim(
            (string) $request->query->get('statut', '')
        );

        if ($statutParametre !== '') {
            $statut = StatutFacture::tryFrom($statutParametre);

            if ($statut === null) {
                return $this->json(
                    [
                        'message' => 'Le statut demandé est invalide.',
                        'statutsAutorises' => array_map(
                            static fn(StatutFacture $statut): string =>
                            $statut->value,
                            StatutFacture::cases()
                        ),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $recherche = trim(
            (string) $request->query->get('recherche', '')
        );

        $tri = trim(
            (string) $request->query->get(
                'tri',
                'dateEmission'
            )
        );

        $ordre = strtoupper(
            trim(
                (string) $request->query->get(
                    'ordre',
                    'DESC'
                )
            )
        );

        $archiveeParametre = strtolower(
            trim(
                (string) $request->query->get(
                    'archivee',
                    'false'
                )
            )
        );

        if (
            !in_array(
                $archiveeParametre,
                [
                    '',
                    'true',
                    'false',
                    '1',
                    '0',
                    'toutes',
                ],
                true
            )
        ) {
            return $this->json(
                [
                    'message' =>
                    'Le filtre archivee doit être true, false, 1, 0 ou toutes.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $archivee = match ($archiveeParametre) {
            'true', '1' => true,
            'toutes' => null,
            default => false,
        };

        if (!$factureRepository->isSortFieldAllowed($tri)) {
            return $this->json(
                [
                    'message' => 'Le champ de tri demandé est invalide.',
                    'trisAutorises' =>
                    $factureRepository->getAllowedSortFields(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($ordre, ['ASC', 'DESC'], true)) {
            return $this->json(
                [
                    'message' =>
                    'L’ordre doit être ASC ou DESC.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $factures = $factureRepository->findForUserWithFilters(
            user: $user,
            statut: $statut,
            recherche: $recherche,
            tri: $tri,
            ordre: $ordre,
            archivee: $archivee
        );

        $data = array_map(
            fn(Facture $facture): array =>
            $this->transformerFacture($facture),
            $factures
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche !== ''
                    ? $recherche
                    : null,
                'statut' => $statut?->value,
                'tri' => $tri,
                'ordre' => $ordre,
                'archivee' => $archiveeParametre === 'toutes'
                    ? 'toutes'
                    : $archivee,
            ],
            'nombreResultats' => count($data),
            'factures' => $data,
        ]);
    }

    #[Route('/api/factures', name: 'api_factures_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        NumerotationService $numerotationService,
        ActiviteService $activiteService,
        NotificationService $notificationService,
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
        $activiteService->enregistrer(
            user: $user,
            type: 'facture_creee',
            titre: 'Nouvelle facture créée',
            description: sprintf(
                '%s • %s',
                $facture->getNumero(),
                $facture->getClient()?->getEntreprise()
                    ?: trim(sprintf(
                        '%s %s',
                        $facture->getClient()?->getPrenom() ?? '',
                        $facture->getClient()?->getNom() ?? ''
                    ))
            )
        );

        $notificationService->creer(
            user: $user,
            type: 'facture_creee',
            titre: 'Nouvelle facture créée',
            message: sprintf(
                'La facture %s a été créée.',
                $facture->getNumero()
            ),
            url: null
        );
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

    #[Route('/api/factures/{id}/pdf', name: 'api_factures_pdf', methods: ['GET'])]
    public function pdf(
        Facture $facture,
        FacturePdfService $facturePdfService,
        #[CurrentUser] User $user
    ): Response {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $pdf = $facturePdfService->generate($facture);

        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $facturePdfService->generateFilename($facture)
                ),
            ]
        );
    }

    #[Route('/api/factures/{id}/envoyer', name: 'api_factures_send', methods: ['POST'])]
    public function send(
        Facture $facture,
        FactureMailerService $factureMailerService,
        EntityManagerInterface $entityManager,
        ActiviteService $activiteService,
        NotificationService $notificationService,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $clientEmail = trim((string) $facture->getClient()?->getEmail());

        if ($clientEmail === '') {
            return $this->json(
                ['message' => 'Le client ne possède aucune adresse e-mail.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $ancienStatut = $facture->getStatut();

        /*
     * Le statut est modifié avant la génération du PDF.
     * Le PDF joint affichera donc bien « Envoyée ».
     */
        $facture->setStatut(StatutFacture::ENVOYEE);

        try {
            $factureMailerService->send($facture);

            $activiteService->enregistrer(
                user: $user,
                type: 'facture_envoyee',
                titre: 'Facture envoyée',
                description: sprintf(
                    '%s • %s',
                    $facture->getNumero(),
                    $facture->getClient()?->getEntreprise()
                        ?: trim(
                            sprintf(
                                '%s %s',
                                $facture->getClient()?->getPrenom() ?? '',
                                $facture->getClient()?->getNom() ?? ''
                            )
                        )
                )
            );

            $notificationService->creer(
                user: $user,
                type: 'facture_envoyee',
                titre: 'Facture envoyée',
                message: sprintf(
                    'La facture %s a été envoyée.',
                    $facture->getNumero()
                ),
                url: null
            );

            $entityManager->flush();
        } catch (\InvalidArgumentException $exception) {
            $facture->setStatut($ancienStatut);

            return $this->json(
                ['message' => $exception->getMessage()],
                JsonResponse::HTTP_BAD_REQUEST
            );
        } catch (\Throwable) {
            $facture->setStatut($ancienStatut);

            return $this->json(
                [
                    'message' =>
                    'Une erreur est survenue pendant l’envoi de la facture.',
                ],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([
            'message' => 'Facture mise en file d’envoi avec succès.',
            'facture' => $this->transformerFacture($facture),
        ]);
    }

    #[Route(
        '/api/factures/{id}/dupliquer',
        name: 'api_factures_duplicate',
        methods: ['POST']
    )]
    public function duplicate(
        Facture $facture,
        EntityManagerInterface $entityManager,
        NumerotationService $numerotationService,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $dateEmission = new \DateTimeImmutable();
        $dateEcheance = $dateEmission->modify('+30 days');

        $nouvelleFacture = new Facture();

        $nouvelleFacture->setNumero(
            $numerotationService->genererNumeroFacture($user)
        );

        $nouvelleFacture->setDateEmission($dateEmission);
        $nouvelleFacture->setDateEmissionPrevue(null);
        $nouvelleFacture->setDateEcheance($dateEcheance);
        $nouvelleFacture->setStatut(StatutFacture::BROUILLON);

        $nouvelleFacture->setTotalHT(
            $facture->getTotalHT() ?? '0.00'
        );

        $nouvelleFacture->setTotalTVA(
            $facture->getTotalTVA() ?? '0.00'
        );

        $nouvelleFacture->setTotalTTC(
            $facture->getTotalTTC() ?? '0.00'
        );

        $nouvelleFacture->setCommentaire(
            $facture->getCommentaire()
        );

        $nouvelleFacture->setClient(
            $facture->getClient()
        );

        $nouvelleFacture->setUser($user);

        foreach ($facture->getLigneFactures() as $ligneFacture) {
            $nouvelleLigne = new LigneFacture();

            $nouvelleLigne->setProduit(
                $ligneFacture->getProduit()
            );

            $nouvelleLigne->setDesignation(
                $ligneFacture->getDesignation() ?? ''
            );

            $nouvelleLigne->setDescription(
                $ligneFacture->getDescription()
            );

            $nouvelleLigne->setUnite(
                $ligneFacture->getUnite()
            );

            $nouvelleLigne->setQuantite(
                $ligneFacture->getQuantite() ?? '1.00'
            );

            $nouvelleLigne->setPrixUnitaireHT(
                $ligneFacture->getPrixUnitaireHT() ?? '0.00'
            );

            $nouvelleLigne->setTva(
                $ligneFacture->getTva() ?? '20.00'
            );

            $nouvelleLigne->setRemise(
                $ligneFacture->getRemise()
            );

            $nouvelleLigne->setTotalHT(
                $ligneFacture->getTotalHT() ?? '0.00'
            );

            $nouvelleLigne->setTotalTVA(
                $ligneFacture->getTotalTVA() ?? '0.00'
            );

            $nouvelleLigne->setTotalTTC(
                $ligneFacture->getTotalTTC() ?? '0.00'
            );

            $nouvelleFacture->addLigneFacture(
                $nouvelleLigne
            );
        }

        $entityManager->persist($nouvelleFacture);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Facture dupliquée avec succès.',
                'id' => $nouvelleFacture->getId(),
                'numero' => $nouvelleFacture->getNumero(),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/factures/{id}', name: 'api_factures_update', methods: ['PUT'])]
    public function update(
        Facture $facture,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }
        if (
            !in_array(
                $facture->getStatut(),
                [
                    StatutFacture::BROUILLON,
                    StatutFacture::PLANIFIEE,
                ],
                true
            )
        ) {
            return $this->json(
                [
                    'message' =>
                    'Cette facture est verrouillée et ne peut plus être modifiée.',
                ],
                JsonResponse::HTTP_CONFLICT
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
            $ancienStatut = $facture->getStatut();

            $facture->setStatut($statut);

            if (
                $ancienStatut !== StatutFacture::EN_RETARD &&
                $statut === StatutFacture::EN_RETARD
            ) {
                $notificationService->creer(
                    user: $user,
                    type: 'facture_en_retard',
                    titre: 'Facture en retard',
                    message: sprintf(
                        'La facture %s est maintenant en retard.',
                        $facture->getNumero()
                    )
                );
            }

            if (
                $ancienStatut !== StatutFacture::PARTIELLEMENT_PAYEE &&
                $statut === StatutFacture::PARTIELLEMENT_PAYEE
            ) {
                $notificationService->creer(
                    user: $user,
                    type: 'facture_partiellement_payee',
                    titre: 'Paiement partiel reçu',
                    message: sprintf(
                        'Un paiement partiel a été reçu pour la facture %s.',
                        $facture->getNumero()
                    )
                );
            }

            if (
                $ancienStatut !== StatutFacture::PAYEE &&
                $statut === StatutFacture::PAYEE
            ) {
                $notificationService->creer(
                    user: $user,
                    type: 'facture_payee',
                    titre: 'Facture payée',
                    message: sprintf(
                        'La facture %s est entièrement payée.',
                        $facture->getNumero()
                    )
                );
            }
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
        if (
            !in_array(
                $facture->getStatut(),
                [
                    StatutFacture::BROUILLON,
                    StatutFacture::PLANIFIEE,
                ],
                true
            )
        ) {
            return $this->json(
                [
                    'message' =>
                    'Cette facture est verrouillée et ne peut plus être supprimée.',
                ],
                JsonResponse::HTTP_CONFLICT
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
            'archivee' => $facture->isArchivee(),
            'nombreRelances' => $facture->getNombreRelances(),
            'derniereRelanceAt' => $facture
                ->getDerniereRelanceAt()
                ?->format(DATE_ATOM),
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

    #[Route(
        '/api/factures/{id}/archiver',
        name: 'api_factures_archive',
        methods: ['POST']
    )]
    public function archive(
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

        if ($facture->isArchivee()) {
            return $this->json(
                ['message' => 'Cette facture est déjà archivée.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $facture->setArchivee(true);
        $entityManager->flush();

        return $this->json([
            'message' => 'Facture archivée avec succès.',
            'facture' => [
                'id' => $facture->getId(),
                'numero' => $facture->getNumero(),
                'archivee' => $facture->isArchivee(),
            ],
        ]);
    }

    #[Route(
        '/api/factures/{id}/restaurer',
        name: 'api_factures_restore',
        methods: ['POST']
    )]
    public function restore(
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

        if (!$facture->isArchivee()) {
            return $this->json(
                ['message' => 'Cette facture n’est pas archivée.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $facture->setArchivee(false);
        $entityManager->flush();

        return $this->json([
            'message' => 'Facture restaurée avec succès.',
            'facture' => [
                'id' => $facture->getId(),
                'numero' => $facture->getNumero(),
                'archivee' => $facture->isArchivee(),
            ],
        ]);
    }
}
