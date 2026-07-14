<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\LigneDevis;
use App\Entity\Produit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LigneDevisController extends AbstractController
{
    #[Route('/api/devis/{id}/lignes', name: 'api_ligne_devis_create', methods: ['POST'])]
    public function create(
        Devis $devis,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        if ($devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé au devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $produit = $entityManager
            ->getRepository(Produit::class)
            ->find($data['produitId'] ?? 0);

        if (!$produit || $produit->getUser() !== $user) {
            return $this->json(
                ['message' => 'Produit introuvable ou accès refusé.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $quantite = (float) ($data['quantite'] ?? 0);

        if ($quantite <= 0) {
            return $this->json(
                ['message' => 'La quantité doit être supérieure à zéro.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $prixUnitaireHT = (float) $produit->getPrixHT();
        $tva = (float) $produit->getTva();

        $totalHT = $quantite * $prixUnitaireHT;
        $totalTVA = $totalHT * ($tva / 100);
        $totalTTC = $totalHT + $totalTVA;

        $ligneDevis = new LigneDevis();

        $ligneDevis->setDevis($devis);
        $devis->addLigneDevi($ligneDevis);

        $ligneDevis->setProduit($produit);
        $ligneDevis->setQuantite(number_format($quantite, 2, '.', ''));
        $ligneDevis->setPrixUnitaireHT(number_format($prixUnitaireHT, 2, '.', ''));
        $ligneDevis->setTva(number_format($tva, 2, '.', ''));
        $ligneDevis->setRemise('0.00');
        $ligneDevis->setDesignation($produit->getNom());
        $ligneDevis->setDescription($produit->getDescription());
        $ligneDevis->setUnite($produit->getUnite());
        $ligneDevis->setTotalHT(number_format($totalHT, 2, '.', ''));
        $ligneDevis->setTotalTVA(number_format($totalTVA, 2, '.', ''));
        $ligneDevis->setTotalTTC(number_format($totalTTC, 2, '.', ''));

        $entityManager->persist($ligneDevis);

        $totalDevisHT = 0.0;
        $totalDevisTVA = 0.0;
        $totalDevisTTC = 0.0;

        foreach ($devis->getLigneDevis() as $ligne) {
            $totalDevisHT += (float) $ligne->getTotalHT();
            $totalDevisTVA += (float) $ligne->getTotalTVA();
            $totalDevisTTC += (float) $ligne->getTotalTTC();
        }

        $devis->setTotalHT(number_format($totalDevisHT, 2, '.', ''));
        $devis->setTotalTVA(number_format($totalDevisTVA, 2, '.', ''));
        $devis->setTotalTTC(number_format($totalDevisTTC, 2, '.', ''));

        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Ligne de devis créée avec succès.',
                'ligne' => [
                    'id' => $ligneDevis->getId(),
                    'produitId' => $produit->getId(),
                    'designation' => $ligneDevis->getDesignation(),
                    'quantite' => $ligneDevis->getQuantite(),
                    'prixUnitaireHT' => $ligneDevis->getPrixUnitaireHT(),
                    'tva' => $ligneDevis->getTva(),
                    'remise' => $ligneDevis->getRemise(),
                    'totalHT' => $ligneDevis->getTotalHT(),
                    'totalTVA' => $ligneDevis->getTotalTVA(),
                    'totalTTC' => $ligneDevis->getTotalTTC(),
                ],
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/devis/{id}/lignes', name: 'api_ligne_devis_list', methods: ['GET'])]
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

        foreach ($devis->getLigneDevis() as $ligne) {
            $data[] = [
                'id' => $ligne->getId(),
                'produitId' => $ligne->getProduit()?->getId(),
                'designation' => $ligne->getDesignation(),
                'description' => $ligne->getDescription(),
                'quantite' => $ligne->getQuantite(),
                'prixUnitaireHT' => $ligne->getPrixUnitaireHT(),
                'tva' => $ligne->getTva(),
                'remise' => $ligne->getRemise(),
                'unite' => $ligne->getUnite(),
                'totalHT' => $ligne->getTotalHT(),
                'totalTVA' => $ligne->getTotalTVA(),
                'totalTTC' => $ligne->getTotalTTC(),
                'createdAt' => $ligne->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $ligne->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/lignes-devis/{id}', name: 'api_ligne_devis_update', methods: ['PUT'])]
    public function update(
        LigneDevis $ligneDevis,
        Request $request,
        EntityManagerInterface $entityManager,
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

        $quantite = (float) $ligneDevis->getQuantite();
        $remise = (float) ($ligneDevis->getRemise() ?? '0.00');

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

        $prixUnitaireHT = (float) $ligneDevis->getPrixUnitaireHT();
        $tva = (float) $ligneDevis->getTva();

        $totalBrutHT = $quantite * $prixUnitaireHT;
        $montantRemise = $totalBrutHT * ($remise / 100);
        $totalHT = $totalBrutHT - $montantRemise;
        $totalTVA = $totalHT * ($tva / 100);
        $totalTTC = $totalHT + $totalTVA;

        $ligneDevis->setQuantite(number_format($quantite, 2, '.', ''));
        $ligneDevis->setRemise(number_format($remise, 2, '.', ''));
        $ligneDevis->setTotalHT(number_format($totalHT, 2, '.', ''));
        $ligneDevis->setTotalTVA(number_format($totalTVA, 2, '.', ''));
        $ligneDevis->setTotalTTC(number_format($totalTTC, 2, '.', ''));

        $totalDevisHT = 0.0;
        $totalDevisTVA = 0.0;
        $totalDevisTTC = 0.0;

        foreach ($devis->getLigneDevis() as $ligne) {
            $totalDevisHT += (float) $ligne->getTotalHT();
            $totalDevisTVA += (float) $ligne->getTotalTVA();
            $totalDevisTTC += (float) $ligne->getTotalTTC();
        }

        $devis->setTotalHT(number_format($totalDevisHT, 2, '.', ''));
        $devis->setTotalTVA(number_format($totalDevisTVA, 2, '.', ''));
        $devis->setTotalTTC(number_format($totalDevisTTC, 2, '.', ''));

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de devis modifiée avec succès.',
            'ligne' => [
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
                'updatedAt' => $ligneDevis->getUpdatedAt()?->format(DATE_ATOM),
            ],
            'totauxDevis' => [
                'totalHT' => $devis->getTotalHT(),
                'totalTVA' => $devis->getTotalTVA(),
                'totalTTC' => $devis->getTotalTTC(),
            ],
        ]);
    }
    #[Route('/api/lignes-devis/{id}', name: 'api_ligne_devis_delete', methods: ['DELETE'])]
    public function delete(
        LigneDevis $ligneDevis,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $devis = $ligneDevis->getDevis();

        if ($devis === null || $devis->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé à cette ligne de devis.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $entityManager->remove($ligneDevis);
        $entityManager->flush();

        $totalDevisHT = 0.0;
        $totalDevisTVA = 0.0;
        $totalDevisTTC = 0.0;

        foreach ($devis->getLigneDevis() as $ligne) {
            $totalDevisHT += (float) $ligne->getTotalHT();
            $totalDevisTVA += (float) $ligne->getTotalTVA();
            $totalDevisTTC += (float) $ligne->getTotalTTC();
        }

        $devis->setTotalHT(number_format($totalDevisHT, 2, '.', ''));
        $devis->setTotalTVA(number_format($totalDevisTVA, 2, '.', ''));
        $devis->setTotalTTC(number_format($totalDevisTTC, 2, '.', ''));

        $entityManager->flush();

        return $this->json([
            'message' => 'Ligne de devis supprimée avec succès.',
            'totauxDevis' => [
                'totalHT' => $devis->getTotalHT(),
                'totalTVA' => $devis->getTotalTVA(),
                'totalTTC' => $devis->getTotalTTC(),
            ],
        ]);
    }
}
