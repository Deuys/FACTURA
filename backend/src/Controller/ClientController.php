<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Service\ActiviteService;
use App\Service\NotificationService;
use App\Repository\ClientRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ClientController extends AbstractController
{
    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    public function index(
        Request $request,
        ClientRepository $clientRepository,
        #[CurrentUser] User $user
    ): JsonResponse {
        $recherche = trim(
            (string) $request->query->get('recherche', '')
        );

        $filtre = trim(
            (string) $request->query->get('filtre', 'tous')
        );

        $tri = trim(
            (string) $request->query->get('tri', 'nom')
        );

        $ordre = strtoupper(
            trim(
                (string) $request->query->get('ordre', 'ASC')
            )
        );

        if (!$clientRepository->isFilterAllowed($filtre)) {
            return $this->json(
                [
                    'message' => 'Le filtre client demandé est invalide.',
                    'filtresAutorises' =>
                    $clientRepository->getAllowedFilters(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!$clientRepository->isSortFieldAllowed($tri)) {
            return $this->json(
                [
                    'message' => 'Le champ de tri demandé est invalide.',
                    'trisAutorises' =>
                    $clientRepository->getAllowedSortFields(),
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($ordre, ['ASC', 'DESC'], true)) {
            return $this->json(
                [
                    'message' => 'L’ordre doit être ASC ou DESC.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $clients = $clientRepository->findForUserWithFilters(
            user: $user,
            recherche: $recherche,
            filtre: $filtre,
            tri: $tri,
            ordre: $ordre
        );

        $data = array_map(
            fn(Client $client): array =>
            $this->transformerClient($client),
            $clients
        );

        return $this->json([
            'filtres' => [
                'recherche' => $recherche !== ''
                    ? $recherche
                    : null,
                'filtre' => $filtre,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'nombreResultats' => count($data),
            'clients' => $data,
        ]);
    }

    #[Route('/api/clients', name: 'api_clients_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ActiviteService $activiteService,
        NotificationService $notificationService,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $client = new Client();

        $client->setNom($data['nom'] ?? '');
        $client->setPrenom($data['prenom'] ?? '');
        $client->setEntreprise($data['entreprise'] ?? null);
        $client->setEmail($data['email'] ?? '');
        $client->setTelephone($data['telephone'] ?? null);
        $client->setAdresse($data['adresse'] ?? null);
        $client->setCodePostal($data['codePostal'] ?? null);
        $client->setVille($data['ville'] ?? null);
        $client->setPays($data['pays'] ?? null);
        $client->setSiret($data['siret'] ?? null);
        $client->setTvaIntracom($data['tvaIntracom'] ?? null);

        $client->setUser($user);

        $entityManager->persist($client);

        $nomClient = $client->getEntreprise()
            ?: trim(
                sprintf(
                    '%s %s',
                    $client->getPrenom() ?? '',
                    $client->getNom() ?? ''
                )
            );

        $activiteService->enregistrer(
            user: $user,
            type: 'client_ajoute',
            titre: 'Nouveau client ajouté',
            description: $nomClient
        );

        $notificationService->creer(
            user: $user,
            type: 'client_cree',
            titre: 'Nouveau client créé',
            message: sprintf(
                'Le client %s a été créé.',
                $nomClient
            ),
            url: null
        );

        $entityManager->flush();

        $entityManager->flush();

        return $this->json([
            'message' => 'Client créé avec succès.',
            'id' => $client->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/clients/{id}', name: 'api_clients_show', methods: ['GET'])]
    public function show(
        Client $client,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json([
            'id' => $client->getId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'entreprise' => $client->getEntreprise(),
            'email' => $client->getEmail(),
            'telephone' => $client->getTelephone(),
            'adresse' => $client->getAdresse(),
            'codePostal' => $client->getCodePostal(),
            'ville' => $client->getVille(),
            'pays' => $client->getPays(),
            'siret' => $client->getSiret(),
            'tvaIntracom' => $client->getTvaIntracom(),
            'createdAt' => $client->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $client->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/clients/{id}', name: 'api_clients_update', methods: ['PUT'])]
    public function update(
        Client $client,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $data = $request->toArray();

        if (array_key_exists('nom', $data)) {
            $client->setNom((string) $data['nom']);
        }

        if (array_key_exists('prenom', $data)) {
            $client->setPrenom((string) $data['prenom']);
        }

        if (array_key_exists('entreprise', $data)) {
            $client->setEntreprise($data['entreprise']);
        }

        if (array_key_exists('email', $data)) {
            $client->setEmail((string) $data['email']);
        }

        if (array_key_exists('telephone', $data)) {
            $client->setTelephone($data['telephone']);
        }

        if (array_key_exists('adresse', $data)) {
            $client->setAdresse($data['adresse']);
        }

        if (array_key_exists('codePostal', $data)) {
            $client->setCodePostal($data['codePostal']);
        }

        if (array_key_exists('ville', $data)) {
            $client->setVille($data['ville']);
        }

        if (array_key_exists('pays', $data)) {
            $client->setPays($data['pays']);
        }

        if (array_key_exists('siret', $data)) {
            $client->setSiret($data['siret']);
        }

        if (array_key_exists('tvaIntracom', $data)) {
            $client->setTvaIntracom($data['tvaIntracom']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Client modifié avec succès.',
            'client' => [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'prenom' => $client->getPrenom(),
                'entreprise' => $client->getEntreprise(),
                'email' => $client->getEmail(),
                'telephone' => $client->getTelephone(),
                'adresse' => $client->getAdresse(),
                'codePostal' => $client->getCodePostal(),
                'ville' => $client->getVille(),
                'pays' => $client->getPays(),
                'siret' => $client->getSiret(),
                'tvaIntracom' => $client->getTvaIntracom(),
                'updatedAt' => $client->getUpdatedAt()?->format(DATE_ATOM),
            ],
        ]);
    }

    #[Route('/api/clients/{id}', name: 'api_clients_delete', methods: ['DELETE'])]
    public function delete(
        Client $client,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Accès refusé.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $entityManager->remove($client);
        $entityManager->flush();

        return $this->json([
            'message' => 'Client supprimé avec succès.'
        ]);
    }

    private function transformerClient(Client $client): array
    {
        return [
            'id' => $client->getId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'entreprise' => $client->getEntreprise(),
            'email' => $client->getEmail(),
            'telephone' => $client->getTelephone(),
            'adresse' => $client->getAdresse(),
            'codePostal' => $client->getCodePostal(),
            'ville' => $client->getVille(),
            'pays' => $client->getPays(),
            'siret' => $client->getSiret(),
            'tvaIntracom' => $client->getTvaIntracom(),
            'typeDelaiPaiement' =>
            $client->getTypeDelaiPaiement()?->value,
            'delaiPaiement' => $client->getDelaiPaiement(),
            'createdAt' => $client
                ->getCreatedAt()
                ?->format(DATE_ATOM),
            'updatedAt' => $client
                ->getUpdatedAt()
                ?->format(DATE_ATOM),
        ];
    }
}
