<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\Produit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LigneFactureController extends AbstractController
{
    #[Route(
        '/api/factures/{id}/lignes',
        name: 'api_ligne_facture_create',
        methods: ['POST']
    )]
    public function create(
        Facture $facture,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à la facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        $produit = $entityManager
            ->getRepository(Produit::class)
            ->find($data['produitId'] ?? 0);

        if ($produit === null || $produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Produit introuvable ou accès refusé.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $quantite = (float) ($data['quantite'] ?? 0);
        $remise = (float) ($data['remise'] ?? 0);

        if ($quantite <= 0) {
            return $this->json(
                ['message' => 'La quantité doit être supérieure à zéro.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($remise < 0 || $remise > 100) {
            return $this->json(
                ['message' => 'La remise doit être comprise entre 0 et 100.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $prixUnitaireHT = (float) $produit->getPrixHT();
        $tva = (float) $produit->getTva();

        $totalBrutHT = $quantite * $prixUnitaireHT;
        $montantRemise = $totalBrutHT * ($remise / 100);
        $totalHT = $totalBrutHT - $montantRemise;
        $totalTVA = $totalHT * ($tva / 100);
        $totalTTC = $totalHT + $totalTVA;

        $ligneFacture = new LigneFacture();

        $ligneFacture->setFacture($facture);
        $facture->addLigneFacture($ligneFacture);

        $ligneFacture->setProduit($produit);
        $ligneFacture->setDesignation($produit->getNom());
        $ligneFacture->setDescription($produit->getDescription());
        $ligneFacture->setUnite($produit->getUnite());

        $ligneFacture->setQuantite(
            number_format($quantite, 2, '.', '')
        );

        $ligneFacture->setPrixUnitaireHT(
            number_format($prixUnitaireHT, 2, '.', '')
        );

        $ligneFacture->setTva(
            number_format($tva, 2, '.', '')
        );

        $ligneFacture->setRemise(
            number_format($remise, 2, '.', '')
        );

        $ligneFacture->setTotalHT(
            number_format($totalHT, 2, '.', '')
        );

        $ligneFacture->setTotalTVA(
            number_format($totalTVA, 2, '.', '')
        );

        $ligneFacture->setTotalTTC(
            number_format($totalTTC, 2, '.', '')
        );

        $entityManager->persist($ligneFacture);

        $this->recalculerTotauxFacture($facture);

        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Ligne de facture créée avec succès.',
                'ligne' => $this->transformerLigne($ligneFacture),
                'totauxFacture' => $this->transformerTotaux($facture),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route(
        '/api/factures/{id}/lignes',
        name: 'api_ligne_facture_list',
        methods: ['GET']
    )]
    public function index(
        Facture $facture,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à la facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = [];

        foreach ($facture->getLigneFactures() as $ligneFacture) {
            $data[] = $this->transformerLigne($ligneFacture);
        }

        return $this->json($data);
    }

    #[Route(
        '/api/lignes-facture/{id}',
        name: 'api_ligne_facture_update',
        methods: ['PUT']
    )]
    public function update(
        LigneFacture $ligneFacture,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $ligneFacture->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        $quantite = (float) $ligneFacture->getQuantite();
        $remise = (float) ($ligneFacture->getRemise() ?? '0.00');

        if (array_key_exists('quantite', $data)) {
            $quantite = (float) $data['quantite'];

            if ($quantite <= 0) {
                return $this->json(
                    ['message' => 'La quantité doit être supérieure à zéro.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (array_key_exists('remise', $data)) {
            $remise = (float) $data['remise'];

            if ($remise < 0 || $remise > 100) {
                return $this->json(
                    ['message' => 'La remise doit être comprise entre 0 et 100.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        if (array_key_exists('designation', $data)) {
            $designation = trim((string) $data['designation']);

            if ($designation === '') {
                return $this->json(
                    ['message' => 'La désignation ne peut pas être vide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneFacture->setDesignation($designation);
        }

        if (array_key_exists('description', $data)) {
            $ligneFacture->setDescription(
                $data['description'] !== null
                    ? (string) $data['description']
                    : null
            );
        }

        if (array_key_exists('unite', $data)) {
            $ligneFacture->setUnite(
                $data['unite'] !== null
                    ? (string) $data['unite']
                    : null
            );
        }

        $prixUnitaireHT = (float) $ligneFacture->getPrixUnitaireHT();
        $tva = (float) $ligneFacture->getTva();

        $totalBrutHT = $quantite * $prixUnitaireHT;
        $montantRemise = $totalBrutHT * ($remise / 100);
        $totalHT = $totalBrutHT - $montantRemise;
        $totalTVA = $totalHT * ($tva / 100);
        $totalTTC = $totalHT + $totalTVA;

        $ligneFacture->setQuantite(
            number_format($quantite, 2, '.', '')
        );

        $ligneFacture->setRemise(
            number_format($remise, 2, '.', '')
        );

        $ligneFacture->setTotalHT(
            number_format($totalHT, 2, '.', '')
        );

        $ligneFacture->setTotalTVA(
            number_format($totalTVA, 2, '.', '')
        );

        $ligneFacture->setTotalTTC(
            number_format($totalTTC, 2, '.', '')
        );

        $this->recalculerTotauxFacture($facture);

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de facture modifiée avec succès.',
            'ligne' => $this->transformerLigne($ligneFacture),
            'totauxFacture' => $this->transformerTotaux($facture),
        ]);
    }

    #[Route(
        '/api/lignes-facture/{id}',
        name: 'api_ligne_facture_delete',
        methods: ['DELETE']
    )]
    public function delete(
        LigneFacture $ligneFacture,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $ligneFacture->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        /*
         * On retire la ligne de la collection en mémoire afin que le
         * recalcul ne la compte plus avant même l'exécution du flush.
         */
        $facture->getLigneFactures()->removeElement($ligneFacture);

        $entityManager->remove($ligneFacture);

        $this->recalculerTotauxFacture($facture);

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de facture supprimée avec succès.',
            'totauxFacture' => $this->transformerTotaux($facture),
        ]);
    }

    private function recalculerTotauxFacture(Facture $facture): void
    {
        $totalFactureHT = 0.0;
        $totalFactureTVA = 0.0;
        $totalFactureTTC = 0.0;

        foreach ($facture->getLigneFactures() as $ligneFacture) {
            $totalFactureHT += (float) $ligneFacture->getTotalHT();
            $totalFactureTVA += (float) $ligneFacture->getTotalTVA();
            $totalFactureTTC += (float) $ligneFacture->getTotalTTC();
        }

        $facture->setTotalHT(
            number_format($totalFactureHT, 2, '.', '')
        );

        $facture->setTotalTVA(
            number_format($totalFactureTVA, 2, '.', '')
        );

        $facture->setTotalTTC(
            number_format($totalFactureTTC, 2, '.', '')
        );
    }

    private function transformerLigne(LigneFacture $ligneFacture): array
    {
        return [
            'id' => $ligneFacture->getId(),
            'produitId' => $ligneFacture->getProduit()?->getId(),
            'designation' => $ligneFacture->getDesignation(),
            'description' => $ligneFacture->getDescription(),
            'quantite' => $ligneFacture->getQuantite(),
            'prixUnitaireHT' => $ligneFacture->getPrixUnitaireHT(),
            'tva' => $ligneFacture->getTva(),
            'remise' => $ligneFacture->getRemise(),
            'unite' => $ligneFacture->getUnite(),
            'totalHT' => $ligneFacture->getTotalHT(),
            'totalTVA' => $ligneFacture->getTotalTVA(),
            'totalTTC' => $ligneFacture->getTotalTTC(),
            'createdAt' => $ligneFacture
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $ligneFacture
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }

    private function transformerTotaux(Facture $facture): array
    {
        return [
            'totalHT' => $facture->getTotalHT(),
            'totalTVA' => $facture->getTotalTVA(),
            'totalTTC' => $facture->getTotalTTC(),
        ];
    }
}
