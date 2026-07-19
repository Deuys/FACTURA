<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\Paiement;
use App\Entity\User;
use App\Enum\ModePaiement;
use App\Enum\OriginePaiement;
use App\Enum\StatutFacture;
use App\Enum\StatutPaiement;
use App\Repository\PaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ActiviteService;
use App\Service\NotificationService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PaiementController extends AbstractController
{
    #[Route('/api/paiements', name: 'api_paiements_list', methods: ['GET'])]
    public function index(
        Request $request,
        PaiementRepository $paiementRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $recherche = trim(
            (string) $request->query->get('recherche', '')
        );

        $modeParametre = trim(
            (string) $request->query->get('modePaiement', '')
        );

        $statutParametre = trim(
            (string) $request->query->get('statut', '')
        );

        $tri = trim(
            (string) $request->query->get(
                'tri',
                'datePaiement'
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

        $modePaiement = null;

        if ($modeParametre !== '') {
            $modePaiement = ModePaiement::tryFrom(
                $modeParametre
            );

            if ($modePaiement === null) {
                return $this->json(
                    [
                        'message' =>
                        'Le mode de paiement demandé est invalide.',
                        'modesPaiementAutorises' =>
                        $this->modesPaiementAutorises(),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $statut = null;

        if ($statutParametre !== '') {
            $statut = StatutPaiement::tryFrom(
                $statutParametre
            );

            if ($statut === null) {
                return $this->json(
                    [
                        'message' =>
                        'Le statut de paiement demandé est invalide.',
                        'statutsAutorises' => array_map(
                            static fn(
                                StatutPaiement $statut
                            ): string => $statut->value,
                            StatutPaiement::cases()
                        ),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (
            !$paiementRepository->isSortFieldAllowed($tri)
        ) {
            return $this->json(
                [
                    'message' =>
                    'Le champ de tri demandé est invalide.',
                    'trisAutorises' =>
                    $paiementRepository
                        ->getAllowedSortFields(),
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

        $paiements =
            $paiementRepository->findForUserWithFilters(
                user: $user,
                recherche: $recherche,
                modePaiement: $modePaiement,
                statut: $statut,
                tri: $tri,
                ordre: $ordre
            );

        $data = array_map(
            fn(Paiement $paiement): array =>
            $this->transformerPaiement($paiement),
            $paiements
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche !== ''
                    ? $recherche
                    : null,
                'modePaiement' =>
                $modePaiement?->value,
                'statut' => $statut?->value,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'nombreResultats' => count($data),
            'paiements' => $data,
        ]);
    }

    #[Route('/api/paiements', name: 'api_paiements_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ActiviteService $activiteService,
        NotificationService $notificationService,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $facture = $entityManager
            ->getRepository(Facture::class)
            ->find($data['factureId'] ?? 0);

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Facture introuvable ou accès refusé.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $montant = (float) ($data['montant'] ?? 0);

        if ($montant <= 0) {
            return $this->json(
                ['message' => 'Le montant doit être supérieur à zéro.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $modePaiement = ModePaiement::tryFrom(
            (string) ($data['modePaiement'] ?? '')
        );

        if ($modePaiement === null) {
            return $this->json(
                [
                    'message' => 'Le mode de paiement est invalide.',
                    'valeursAutorisees' => $this->modesPaiementAutorises(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $datePaiement = new \DateTimeImmutable();

        if (!empty($data['datePaiement'])) {
            try {
                $datePaiement = new \DateTimeImmutable(
                    (string) $data['datePaiement']
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date de paiement est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $totalTTC = (float) ($facture->getTotalTTC() ?? '0.00');
        $totalDejaPaye = $this->calculerTotalPaye($facture);
        $resteAPayer = max(0, $totalTTC - $totalDejaPaye);

        if ($totalTTC <= 0) {
            return $this->json(
                [
                    'message' =>
                    'Impossible d’enregistrer un paiement sur une facture dont le total TTC est nul.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($montant > $resteAPayer) {
            return $this->json(
                [
                    'message' =>
                    'Le montant dépasse le reste à payer.',
                    'resteAPayer' =>
                    number_format($resteAPayer, 2, '.', ''),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $paiement = new Paiement();

        $paiement->setFacture($facture);
        $facture->addPaiement($paiement);

        $paiement->setMontant(
            number_format($montant, 2, '.', '')
        );
        $paiement->setDatePaiement($datePaiement);
        $paiement->setModePaiement($modePaiement);
        $paiement->setOrigine(OriginePaiement::MANUEL);
        $paiement->setStatut(StatutPaiement::CONFIRME);
        $paiement->setReference(
            isset($data['reference'])
                ? trim((string) $data['reference'])
                : null
        );
        $paiement->setCommentaire(
            isset($data['commentaire'])
                ? trim((string) $data['commentaire'])
                : null
        );

        $errors = $validator->validate($paiement);

        if (count($errors) > 0) {
            $formattedErrors = [];

            foreach ($errors as $error) {
                $formattedErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return $this->json(
                ['errors' => $formattedErrors],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $entityManager->persist($paiement);

        $ancienStatutFacture = $facture->getStatut();

        $this->mettreAJourStatutFacture($facture);

        $nouveauStatutFacture = $facture->getStatut();

        if (
            $ancienStatutFacture !== $nouveauStatutFacture
            && $nouveauStatutFacture === StatutFacture::PARTIELLEMENT_PAYEE
        ) {
            $notificationService->creer(
                user: $user,
                type: 'facture_partiellement_payee',
                titre: 'Facture partiellement payée',
                message: sprintf(
                    'La facture %s a été partiellement payée.',
                    $facture->getNumero()
                ),
                url: null
            );
        }

        if (
            $ancienStatutFacture !== $nouveauStatutFacture
            && $nouveauStatutFacture === StatutFacture::PAYEE
        ) {
            $notificationService->creer(
                user: $user,
                type: 'facture_payee',
                titre: 'Facture payée',
                message: sprintf(
                    'La facture %s a été entièrement payée.',
                    $facture->getNumero()
                ),
                url: null
            );
        }

        $activiteService->enregistrer(
            user: $user,
            type: 'paiement_recu',
            titre: 'Paiement reçu',
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
            type: 'paiement_recu',
            titre: 'Paiement reçu',
            message: sprintf(
                'Paiement reçu pour la facture %s.',
                $facture->getNumero()
            ),
            url: null
        );

        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Paiement enregistré avec succès.',
                'paiement' => $this->transformerPaiement($paiement),
                'situationFacture' =>
                $this->transformerSituationFacture($facture),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route(
        '/api/paiements/{id}',
        name: 'api_paiements_show',
        methods: ['GET']
    )]
    public function show(
        Paiement $paiement,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $paiement->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à ce paiement.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json(
            $this->transformerPaiement($paiement)
        );
    }

    #[Route(
        '/api/paiements/{id}',
        name: 'api_paiements_update',
        methods: ['PUT']
    )]
    public function update(
        Paiement $paiement,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $paiement->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à ce paiement.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('montant', $data)) {
            $nouveauMontant = (float) $data['montant'];

            if ($nouveauMontant <= 0) {
                return $this->json(
                    ['message' => 'Le montant doit être supérieur à zéro.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $totalTTC = (float) ($facture->getTotalTTC() ?? '0.00');

            $totalSansPaiementActuel =
                $this->calculerTotalPaye($facture, $paiement);

            $maximumAutorise = max(
                0,
                $totalTTC - $totalSansPaiementActuel
            );

            if ($nouveauMontant > $maximumAutorise) {
                return $this->json(
                    [
                        'message' =>
                        'Le montant dépasse le reste à payer.',
                        'maximumAutorise' =>
                        number_format(
                            $maximumAutorise,
                            2,
                            '.',
                            ''
                        ),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $paiement->setMontant(
                number_format($nouveauMontant, 2, '.', '')
            );
        }

        if (array_key_exists('datePaiement', $data)) {
            try {
                $paiement->setDatePaiement(
                    new \DateTimeImmutable(
                        (string) $data['datePaiement']
                    )
                );
            } catch (\Exception) {
                return $this->json(
                    ['message' => 'La date de paiement est invalide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (array_key_exists('modePaiement', $data)) {
            $modePaiement = ModePaiement::tryFrom(
                (string) $data['modePaiement']
            );

            if ($modePaiement === null) {
                return $this->json(
                    [
                        'message' =>
                        'Le mode de paiement est invalide.',
                        'valeursAutorisees' =>
                        $this->modesPaiementAutorises(),
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $paiement->setModePaiement($modePaiement);
        }

        if (array_key_exists('reference', $data)) {
            $paiement->setReference(
                $data['reference'] !== null
                    ? trim((string) $data['reference'])
                    : null
            );
        }

        if (array_key_exists('commentaire', $data)) {
            $paiement->setCommentaire(
                $data['commentaire'] !== null
                    ? trim((string) $data['commentaire'])
                    : null
            );
        }

        $errors = $validator->validate($paiement);

        if (count($errors) > 0) {
            $formattedErrors = [];

            foreach ($errors as $error) {
                $formattedErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return $this->json(
                ['errors' => $formattedErrors],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $this->mettreAJourStatutFacture($facture);

        $entityManager->flush();

        return $this->json([
            'message' => 'Paiement modifié avec succès.',
            'paiement' => $this->transformerPaiement($paiement),
            'situationFacture' =>
            $this->transformerSituationFacture($facture),
        ]);
    }

    #[Route(
        '/api/paiements/{id}',
        name: 'api_paiements_delete',
        methods: ['DELETE']
    )]
    public function delete(
        Paiement $paiement,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $paiement->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à ce paiement.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $facture->getPaiements()->removeElement($paiement);

        $entityManager->remove($paiement);

        $this->mettreAJourStatutFacture($facture);

        $entityManager->flush();

        return $this->json([
            'message' => 'Paiement supprimé avec succès.',
            'situationFacture' =>
            $this->transformerSituationFacture($facture),
        ]);
    }

    private function calculerTotalPaye(
        Facture $facture,
        ?Paiement $paiementAExclure = null
    ): float {
        $totalPaye = 0.0;

        foreach ($facture->getPaiements() as $paiement) {
            if (
                $paiementAExclure !== null
                && $paiement === $paiementAExclure
            ) {
                continue;
            }

            // On ne compte que les paiements confirmés
            if ($paiement->getStatut() !== StatutPaiement::CONFIRME) {
                continue;
            }

            $totalPaye += (float) ($paiement->getMontant() ?? '0.00');
        }

        return $totalPaye;
    }

    private function mettreAJourStatutFacture(
        Facture $facture
    ): void {
        $totalTTC = (float) ($facture->getTotalTTC() ?? '0.00');
        $totalPaye = $this->calculerTotalPaye($facture);

        /*
     * Facture entièrement payée.
     */
        if ($totalTTC > 0 && $totalPaye >= $totalTTC) {
            $facture->setStatut(StatutFacture::PAYEE);

            return;
        }

        /*
     * Au moins un paiement enregistré,
     * mais le total TTC n'est pas encore atteint.
     */
        if ($totalPaye > 0) {
            $facture->setStatut(
                StatutFacture::PARTIELLEMENT_PAYEE
            );

            return;
        }

        /*
     * Aucun paiement restant après une modification
     * ou une suppression.
     */
        if (
            in_array(
                $facture->getStatut(),
                [
                    StatutFacture::PAYEE,
                    StatutFacture::PARTIELLEMENT_PAYEE,
                ],
                true
            )
        ) {
            $facture->setStatut(
                StatutFacture::EN_ATTENTE
            );
        }
    }

    private function transformerPaiement(
        Paiement $paiement
    ): array {
        $facture = $paiement->getFacture();

        return [
            'id' => $paiement->getId(),
            'montant' => $paiement->getMontant(),
            'datePaiement' => $paiement
                ->getDatePaiement()
                ?->format('Y-m-d'),
            'modePaiement' =>
            $paiement->getModePaiement()->value,
            'statut' =>
            $paiement->getStatut()->value,
            'origine' => $paiement->getOrigine()->value,
            'externalPaymentId' => $paiement->getExternalPaymentId(),
            'reference' => $paiement->getReference(),
            'commentaire' => $paiement->getCommentaire(),
            'facture' => [
                'id' => $facture?->getId(),
                'numero' => $facture?->getNumero(),
                'totalTTC' => $facture?->getTotalTTC(),
                'statut' => $facture?->getStatut()->value,
                'client' => [
                    'id' => $facture?->getClient()?->getId(),
                    'nom' => $facture?->getClient()?->getNom(),
                    'prenom' => $facture?->getClient()?->getPrenom(),
                    'entreprise' =>
                    $facture?->getClient()?->getEntreprise(),
                ],
            ],
            'createdAt' => $paiement
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $paiement
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }

    private function transformerSituationFacture(
        Facture $facture
    ): array {
        $totalTTC = (float) ($facture->getTotalTTC() ?? '0.00');
        $totalPaye = $this->calculerTotalPaye($facture);
        $resteAPayer = max(0, $totalTTC - $totalPaye);

        return [
            'factureId' => $facture->getId(),
            'numero' => $facture->getNumero(),
            'statut' => $facture->getStatut()->value,
            'totalTTC' => number_format($totalTTC, 2, '.', ''),
            'totalPaye' => number_format($totalPaye, 2, '.', ''),
            'resteAPayer' =>
            number_format($resteAPayer, 2, '.', ''),
        ];
    }

    private function modesPaiementAutorises(): array
    {
        return array_map(
            static fn(ModePaiement $mode): string => $mode->value,
            ModePaiement::cases()
        );
    }
}
