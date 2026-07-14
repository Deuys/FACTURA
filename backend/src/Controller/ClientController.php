<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ClientController extends AbstractController
{
    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): JsonResponse {
        $clients = $entityManager
            ->getRepository(Client::class)
            ->findBy(['user' => $user]);

        $data = array_map(
            static fn(Client $client): array => [
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
            ],
            $clients
        );

        return $this->json($data);
    }
    #[Route('/api/clients', name: 'api_clients_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
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
}
