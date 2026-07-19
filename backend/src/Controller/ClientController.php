<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Service\ActiviteService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

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

        if ($page < 1) {
            return $this->json(
                [
                    'message' => 'Le numéro de page doit être supérieur à 0.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if ($limit < 1 || $limit > 100) {
            return $this->json(
                [
                    'message' =>
                    'Le nombre de résultats par page doit être compris entre 1 et 100.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $resultat = $clientRepository->findForUserWithFilters(
            user: $user,
            recherche: $recherche,
            filtre: $filtre,
            tri: $tri,
            ordre: $ordre,
            page: $page,
            limit: $limit
        );

        $clients = $resultat['clients'];
        $total = $resultat['total'];

        $nombrePages = $total > 0
            ? (int) ceil($total / $limit)
            : 0;

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
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'nombrePages' => $nombrePages,
                'nombreResultats' => count($data),
                'pagePrecedente' => $page > 1
                    ? $page - 1
                    : null,
                'pageSuivante' => $page < $nombrePages
                    ? $page + 1
                    : null,
            ],
            'clients' => $data,
        ]);
    }
    #[Route('/api/clients', name: 'api_clients_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ActiviteService $activiteService,
        NotificationService $notificationService,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = $request->toArray();

        $typeError = $this->validateInputTypes($data);
        if ($typeError !== null) {
            return $typeError;
        }

        $client = new Client();
        $client->setNom(trim((string) ($data['nom'] ?? '')));
        $client->setPrenom($this->normalizeOptionalString($data['prenom'] ?? null));
        $client->setEntreprise($this->normalizeOptionalString($data['entreprise'] ?? null));
        $client->setEmail(mb_strtolower(trim((string) ($data['email'] ?? ''))));
        $client->setTelephone($this->normalizeOptionalString($data['telephone'] ?? null));
        $client->setAdresse($this->normalizeOptionalString($data['adresse'] ?? null));
        $client->setCodePostal($this->normalizeOptionalString($data['codePostal'] ?? null));
        $client->setVille($this->normalizeOptionalString($data['ville'] ?? null));
        $client->setPays(trim((string) ($data['pays'] ?? '')));
        $client->setSiret($this->normalizeOptionalString($data['siret'] ?? null));
        $client->setTvaIntracom($this->normalizeOptionalString($data['tvaIntracom'] ?? null));
        $client->setUser($user);

        $violations = $validator->validate($client);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->persist($client);

        $nomClient = $client->getEntreprise() ?: trim(sprintf(
            '%s %s',
            $client->getPrenom() ?? '',
            $client->getNom() ?? ''
        ));

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
            message: sprintf('Le client %s a été créé.', $nomClient),
            url: null
        );

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
                ['message' => 'Client introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->transformerClient($client));
    }

    #[Route('/api/clients/{id}', name: 'api_clients_update', methods: ['PUT'])]
    public function update(
        Client $client,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        #[CurrentUser] User $user
    ): JsonResponse {
        if ($client->getUser() !== $user) {
            return $this->json(
                ['message' => 'Client introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $data = $request->toArray();

        $typeError = $this->validateInputTypes($data);
        if ($typeError !== null) {
            return $typeError;
        }

        if (array_key_exists('nom', $data)) {
            $client->setNom(trim((string) $data['nom']));
        }

        if (array_key_exists('prenom', $data)) {
            $client->setPrenom($this->normalizeOptionalString($data['prenom']));
        }

        if (array_key_exists('entreprise', $data)) {
            $client->setEntreprise($this->normalizeOptionalString($data['entreprise']));
        }

        if (array_key_exists('email', $data)) {
            $client->setEmail(mb_strtolower(trim((string) $data['email'])));
        }

        if (array_key_exists('telephone', $data)) {
            $client->setTelephone($this->normalizeOptionalString($data['telephone']));
        }

        if (array_key_exists('adresse', $data)) {
            $client->setAdresse($this->normalizeOptionalString($data['adresse']));
        }

        if (array_key_exists('codePostal', $data)) {
            $client->setCodePostal($this->normalizeOptionalString($data['codePostal']));
        }

        if (array_key_exists('ville', $data)) {
            $client->setVille($this->normalizeOptionalString($data['ville']));
        }

        if (array_key_exists('pays', $data)) {
            $client->setPays(trim((string) $data['pays']));
        }

        if (array_key_exists('siret', $data)) {
            $client->setSiret($this->normalizeOptionalString($data['siret']));
        }

        if (array_key_exists('tvaIntracom', $data)) {
            $client->setTvaIntracom($this->normalizeOptionalString($data['tvaIntracom']));
        }

        $violations = $validator->validate($client);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Client modifié avec succès.',
            'client' => $this->transformerClient($client),
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
                ['message' => 'Client introuvable.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $entityManager->remove($client);
        $entityManager->flush();

        return $this->json([
            'message' => 'Client supprimé avec succès.',
        ]);
    }

    private function validateInputTypes(array $data): ?JsonResponse
    {
        $allowedFields = [
            'nom',
            'prenom',
            'entreprise',
            'email',
            'telephone',
            'adresse',
            'codePostal',
            'ville',
            'pays',
            'siret',
            'tvaIntracom',
        ];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] !== null && !is_string($data[$field])) {
                return $this->json([
                    'message' => 'Les données du client sont invalides.',
                    'erreurs' => [
                        $field => ['Ce champ doit être une chaîne de caractères.'],
                    ],
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return null;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function validationErrorResponse(
        ConstraintViolationListInterface $violations
    ): JsonResponse {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $this->json([
            'message' => 'Les données du client sont invalides.',
            'erreurs' => $errors,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
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
            'typeDelaiPaiement' => $client->getTypeDelaiPaiement()?->value,
            'delaiPaiement' => $client->getDelaiPaiement(),
            'createdAt' => $client->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $client->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
