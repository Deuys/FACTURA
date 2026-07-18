<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Request;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function index(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getDashboard($user)
        );
    }

    #[Route(
        '/api/dashboard/clients',
        name: 'api_dashboard_clients',
        methods: ['GET']
    )]
    public function clients(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getClientsDashboard($user)
        );
    }

    #[Route(
        '/api/dashboard/produits',
        name: 'api_dashboard_produits',
        methods: ['GET']
    )]
    public function produits(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getProduitsDashboard($user)
        );
    }

    #[Route(
        '/api/dashboard/devis',
        name: 'api_dashboard_devis',
        methods: ['GET']
    )]
    public function devis(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getDevisDashboard($user)
        );
    }

    #[Route(
        '/api/dashboard/factures',
        name: 'api_dashboard_factures',
        methods: ['GET']
    )]
    public function factures(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getFacturesDashboard($user)
        );
    }
    #[Route(
        '/api/dashboard/paiements',
        name: 'api_dashboard_paiements',
        methods: ['GET']
    )]
    public function paiements(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getPaiementsDashboard($user)
        );
    }

    #[Route(
        '/api/dashboard/activite',
        name: 'api_dashboard_activite',
        methods: ['GET']
    )]
    public function activite(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getActiviteRecente($user)
        );
    }

    #[Route(
        '/api/dashboard/dernieres-factures',
        name: 'api_dashboard_dernieres_factures',
        methods: ['GET']
    )]
    public function dernieresFactures(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getDernieresFactures($user)
        );
    }

    #[Route(
        '/api/dashboard/factures-echeance',
        name: 'api_dashboard_factures_echeance',
        methods: ['GET']
    )]
    public function facturesEcheance(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getFacturesAEcheance($user)
        );
    }


    #[Route(
        '/api/dashboard/evolution-chiffre-affaires',
        name: 'api_dashboard_evolution_chiffre_affaires',
        methods: ['GET']
    )]
    
    public function evolutionChiffreAffaires(
        Request $request,
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $periode = $request->query->get('periode');

        if ($periode !== null) {
            $periode = strtolower(trim((string) $periode));

            $periodesAutorisees = [
                '1m',
                '3m',
                '6m',
                '12m',
            ];

            if (!in_array($periode, $periodesAutorisees, true)) {
                return $this->json(
                    [
                        'message' => 'Période invalide.',
                        'periodesAutorisees' => $periodesAutorisees,
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            return $this->json(
                $dashboardService->getEvolutionChiffreAffaires(
                    $user,
                    null,
                    $periode
                )
            );
        }

        $annee = $request->query->getInt(
            'annee',
            (int) date('Y')
        );

        if ($annee < 2000 || $annee > 2100) {
            return $this->json(
                ['message' => 'Année invalide.'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        return $this->json(
            $dashboardService->getEvolutionChiffreAffaires(
                $user,
                $annee
            )
        );
    }

    #[Route(
        '/api/dashboard/repartition-factures',
        name: 'api_dashboard_repartition_factures',
        methods: ['GET']
    )]
    public function repartitionFactures(
        DashboardService $dashboardService,
        #[CurrentUser] User $user
    ): JsonResponse {
        return $this->json(
            $dashboardService->getRepartitionFactures($user)
        );
    }
}
