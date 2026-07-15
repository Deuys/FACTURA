<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\LigneDevis;
use App\Entity\Produit;
use App\Entity\User;
use App\Service\CalculTotauxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LigneDevisController extends AbstractController
{
    #[Route(
        '/api/devis/{id}/lignes',
        name: 'api_ligne_devis_create',
        methods: ['POST']
    )]
    public function create(
        Devis $devis,
        Request $request,
        EntityManagerInterface $entityManager,
        CalculTotauxService $calculTotauxService,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé au devis.'],
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

        $ligneDevis = new LigneDevis();

        $ligneDevis->setProduit($produit);
        $ligneDevis->setDesignation($produit->getNom());
        $ligneDevis->setDescription($produit->getDescription());
        $ligneDevis->setUnite($produit->getUnite());

        $ligneDevis->setQuantite(
            number_format($quantite, 2, '.', '')
        );

        $ligneDevis->setPrixUnitaireHT(
            number_format((float) $produit->getPrixHT(), 2, '.', '')
        );

        $ligneDevis->setTva(
            number_format((float) $produit->getTva(), 2, '.', '')
        );

        $ligneDevis->setRemise(
            number_format($remise, 2, '.', '')
        );

        $devis->addLigneDevis($ligneDevis);

        $entityManager->persist($ligneDevis);

        $calculTotauxService->recalculerDevis($devis);

        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Ligne de devis créée avec succès.',
                'ligne' => $this->transformerLigne($ligneDevis),
                'totauxDevis' => $this->transformerTotaux($devis),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route(
        '/api/devis/{id}/lignes',
        name: 'api_ligne_devis_list',
        methods: ['GET']
    )]
    public function index(
        Devis $devis,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé au devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = [];

        foreach ($devis->getLigneDevis() as $ligneDevis) {
            $data[] = $this->transformerLigne($ligneDevis);
        }

        return $this->json($data);
    }

    #[Route(
        '/api/lignes-devis/{id}',
        name: 'api_ligne_devis_update',
        methods: ['PUT']
    )]
    public function update(
        LigneDevis $ligneDevis,
        Request $request,
        EntityManagerInterface $entityManager,
        CalculTotauxService $calculTotauxService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $devis = $ligneDevis->getDevis();

        if ($devis === null || $devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('quantite', $data)) {
            $quantite = (float) $data['quantite'];

            if ($quantite <= 0) {
                return $this->json(
                    ['message' => 'La quantité doit être supérieure à zéro.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneDevis->setQuantite(
                number_format($quantite, 2, '.', '')
            );
        }

        if (array_key_exists('remise', $data)) {
            $remise = (float) $data['remise'];

            if ($remise < 0 || $remise > 100) {
                return $this->json(
                    ['message' => 'La remise doit être comprise entre 0 et 100.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneDevis->setRemise(
                number_format($remise, 2, '.', '')
            );
        }

        if (array_key_exists('prixUnitaireHT', $data)) {
            $prixUnitaireHT = (float) $data['prixUnitaireHT'];

            if ($prixUnitaireHT < 0) {
                return $this->json(
                    ['message' => 'Le prix unitaire HT ne peut pas être négatif.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneDevis->setPrixUnitaireHT(
                number_format($prixUnitaireHT, 2, '.', '')
            );
        }

        if (array_key_exists('tva', $data)) {
            $tva = (float) $data['tva'];

            if ($tva < 0 || $tva > 100) {
                return $this->json(
                    ['message' => 'Le taux de TVA doit être compris entre 0 et 100.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneDevis->setTva(
                number_format($tva, 2, '.', '')
            );
        }

        if (array_key_exists('designation', $data)) {
            $designation = trim((string) $data['designation']);

            if ($designation === '') {
                return $this->json(
                    ['message' => 'La désignation ne peut pas être vide.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneDevis->setDesignation($designation);
        }

        if (array_key_exists('description', $data)) {
            $ligneDevis->setDescription(
                $data['description'] !== null
                    ? (string) $data['description']
                    : null
            );
        }

        if (array_key_exists('unite', $data)) {
            $ligneDevis->setUnite(
                $data['unite'] !== null
                    ? (string) $data['unite']
                    : null
            );
        }

        $calculTotauxService->recalculerDevis($devis);

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de devis modifiée avec succès.',
            'ligne' => $this->transformerLigne($ligneDevis),
            'totauxDevis' => $this->transformerTotaux($devis),
        ]);
    }

    #[Route(
        '/api/lignes-devis/{id}',
        name: 'api_ligne_devis_delete',
        methods: ['DELETE']
    )]
    public function delete(
        LigneDevis $ligneDevis,
        EntityManagerInterface $entityManager,
        CalculTotauxService $calculTotauxService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $devis = $ligneDevis->getDevis();

        if ($devis === null || $devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        /*
         * On retire la ligne de la collection avant le recalcul,
         * sinon elle serait encore comptée.
         */
        $devis->removeLigneDevis($ligneDevis);

        $entityManager->remove($ligneDevis);

        $calculTotauxService->recalculerDevis($devis);

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de devis supprimée avec succès.',
            'totauxDevis' => $this->transformerTotaux($devis),
        ]);
    }

    private function transformerLigne(LigneDevis $ligneDevis): array
    {
        return [
            'id' => $ligneDevis->getId(),
            'produitId' => $ligneDevis->getProduit()?->getId(),
            'designation' => $ligneDevis->getDesignation(),
            'description' => $ligneDevis->getDescription(),
            'quantite' => $ligneDevis->getQuantite(),
            'prixUnitaireHT' => $ligneDevis->getPrixUnitaireHT(),
            'tva' => $ligneDevis->getTva(),
            'remise' => $ligneDevis->getRemise(),
            'unite' => $ligneDevis->getUnite(),
            'totalHT' => $ligneDevis->getTotalHT(),
            'totalTVA' => $ligneDevis->getTotalTVA(),
            'totalTTC' => $ligneDevis->getTotalTTC(),
            'createdAt' => $ligneDevis
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $ligneDevis
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }

    private function transformerTotaux(Devis $devis): array
    {
        return [
            'totalHT' => $devis->getTotalHT(),
            'totalTVA' => $devis->getTotalTVA(),
            'totalTTC' => $devis->getTotalTTC(),
        ];
    }
}
