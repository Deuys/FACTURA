<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Entity\Produit;
use App\Entity\User;
use App\Service\CalculTotauxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        CalculTotauxService $calculTotauxService,
        ValidatorInterface $validator,
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

        $ligneFacture = new LigneFacture();

        $ligneFacture->setProduit($produit);
        $ligneFacture->setDesignation($produit->getNom());
        $ligneFacture->setDescription($produit->getDescription());
        $ligneFacture->setUnite($produit->getUnite());

        $ligneFacture->setQuantite(
            number_format($quantite, 2, '.', '')
        );

        $ligneFacture->setPrixUnitaireHT(
            number_format((float) $produit->getPrixHT(), 2, '.', '')
        );

        $ligneFacture->setTva(
            number_format((float) $produit->getTva(), 2, '.', '')
        );

        $ligneFacture->setRemise(
            number_format($remise, 2, '.', '')
        );

        $facture->addLigneFacture($ligneFacture);

        $errors = $validator->validate($ligneFacture);

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

        $entityManager->persist($ligneFacture);


        $calculTotauxService->recalculerFacture($facture);

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
        CalculTotauxService $calculTotauxService,
        ValidatorInterface $validator,
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

        if (array_key_exists('quantite', $data)) {
            $quantite = (float) $data['quantite'];

            if ($quantite <= 0) {
                return $this->json(
                    ['message' => 'La quantité doit être supérieure à zéro.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $ligneFacture->setQuantite(
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

            $ligneFacture->setRemise(
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

            $ligneFacture->setPrixUnitaireHT(
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

            $ligneFacture->setTva(
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

        $errors = $validator->validate($ligneFacture);

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

        $calculTotauxService->recalculerFacture($facture);

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
        CalculTotauxService $calculTotauxService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $facture = $ligneFacture->getFacture();

        if ($facture === null || $facture->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de facture.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $facture->getLigneFactures()->removeElement($ligneFacture);

        $entityManager->remove($ligneFacture);

        $calculTotauxService->recalculerFacture($facture);

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de facture supprimée avec succès.',
            'totauxFacture' => $this->transformerTotaux($facture),
        ]);
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
