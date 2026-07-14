<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/entreprise')]
#[IsGranted('ROLE_USER')]
final class EntrepriseController extends AbstractController
{
    /**
     * Récupère les paramètres de l'entreprise de l'utilisateur connecté.
     */
    #[Route('', name: 'api_entreprise_show', methods: ['GET'])]
    public function show(EntrepriseRepository $entrepriseRepository): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        $entreprise = $entrepriseRepository->findOneBy([
            'user' => $user,
        ]);

        if (!$entreprise) {
            return $this->json(
                [
                    'message' => 'Aucune entreprise configurée pour cet utilisateur.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json(
            $this->formatEntreprise($entreprise),
            Response::HTTP_OK
        );
    }

    /**
     * Crée ou met à jour les paramètres de l'entreprise.
     */
    #[Route('', name: 'api_entreprise_update', methods: ['PUT'])]
    public function update(
        Request $request,
        EntrepriseRepository $entrepriseRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(
                [
                    'message' => 'Le corps de la requête contient un JSON invalide.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $entreprise = $entrepriseRepository->findOneBy([
            'user' => $user,
        ]);

        $isNew = $entreprise === null;

        if ($isNew) {
            $missingFields = $this->getMissingRequiredFields($data);

            if ($missingFields !== []) {
                return $this->json(
                    [
                        'message' => 'Certains champs obligatoires sont manquants.',
                        'champsManquants' => $missingFields,
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise = new Entreprise();
            $entreprise->setUser($user);
        }

        if (array_key_exists('nom', $data)) {
            $nom = trim((string) $data['nom']);

            if ($nom === '') {
                return $this->json(
                    ['message' => 'Le nom de l’entreprise ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setNom($nom);
        }

        if (array_key_exists('logo', $data)) {
            $entreprise->setLogo(
                $this->nullableString($data['logo'])
            );
        }

        if (array_key_exists('adresse', $data)) {
            $adresse = trim((string) $data['adresse']);

            if ($adresse === '') {
                return $this->json(
                    ['message' => 'L’adresse ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setAdresse($adresse);
        }

        if (array_key_exists('ville', $data)) {
            $ville = trim((string) $data['ville']);

            if ($ville === '') {
                return $this->json(
                    ['message' => 'La ville ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setVille($ville);
        }

        if (array_key_exists('codePostal', $data)) {
            $codePostal = trim((string) $data['codePostal']);

            if ($codePostal === '') {
                return $this->json(
                    ['message' => 'Le code postal ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setCodePostal($codePostal);
        }

        if (array_key_exists('pays', $data)) {
            $entreprise->setPays(
                $this->nullableString($data['pays'])
            );
        }

        if (array_key_exists('siret', $data)) {
            $entreprise->setSiret(
                $this->nullableString($data['siret'])
            );
        }

        if (array_key_exists('tvaIntracom', $data)) {
            $entreprise->setTvaIntracom(
                $this->nullableString($data['tvaIntracom'])
            );
        }

        if (array_key_exists('telephone', $data)) {
            $entreprise->setTelephone(
                $this->nullableString($data['telephone'])
            );
        }

        if (array_key_exists('email', $data)) {
            $email = $this->nullableString($data['email']);

            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(
                    ['message' => 'L’adresse e-mail de l’entreprise est invalide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setEmail($email);
        }

        if (array_key_exists('iban', $data)) {
            $entreprise->setIban(
                $this->nullableString($data['iban'])
            );
        }

        if (array_key_exists('bic', $data)) {
            $entreprise->setBic(
                $this->nullableString($data['bic'])
            );
        }

        if (array_key_exists('conditionsReglement', $data)) {
            $entreprise->setConditionsReglement(
                $this->nullableString($data['conditionsReglement'])
            );
        }

        if (array_key_exists('devise', $data)) {
            $devise = strtoupper(trim((string) $data['devise']));

            if ($devise === '') {
                return $this->json(
                    ['message' => 'La devise ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setDevise($devise);
        }

        if (array_key_exists('tauxTvaDefaut', $data)) {
            if (!is_numeric($data['tauxTvaDefaut'])) {
                return $this->json(
                    ['message' => 'Le taux de TVA doit être un nombre.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $tauxTva = (float) $data['tauxTvaDefaut'];

            if ($tauxTva < 0 || $tauxTva > 100) {
                return $this->json(
                    ['message' => 'Le taux de TVA doit être compris entre 0 et 100.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setTauxTvaDefaut(
                number_format($tauxTva, 2, '.', '')
            );
        }

        if (array_key_exists('delaiPaiement', $data)) {
            if (
                filter_var(
                    $data['delaiPaiement'],
                    FILTER_VALIDATE_INT
                ) === false
            ) {
                return $this->json(
                    ['message' => 'Le délai de paiement doit être un nombre entier.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $delaiPaiement = (int) $data['delaiPaiement'];

            if ($delaiPaiement < 0) {
                return $this->json(
                    ['message' => 'Le délai de paiement ne peut pas être négatif.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setDelaiPaiement($delaiPaiement);
        }

        if (array_key_exists('prefixeDevis', $data)) {
            $prefixeDevis = strtoupper(trim((string) $data['prefixeDevis']));

            if ($prefixeDevis === '') {
                return $this->json(
                    ['message' => 'Le préfixe des devis ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setPrefixeDevis($prefixeDevis);
        }

        if (array_key_exists('prefixeFacture', $data)) {
            $prefixeFacture = strtoupper(
                trim((string) $data['prefixeFacture'])
            );

            if ($prefixeFacture === '') {
                return $this->json(
                    ['message' => 'Le préfixe des factures ne peut pas être vide.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setPrefixeFacture($prefixeFacture);
        }

        $entityManager->persist($entreprise);
        $entityManager->flush();

        return $this->json(
            [
                'message' => $isNew
                    ? 'Entreprise configurée avec succès.'
                    : 'Paramètres de l’entreprise modifiés avec succès.',
                'entreprise' => $this->formatEntreprise($entreprise),
            ],
            $isNew
                ? Response::HTTP_CREATED
                : Response::HTTP_OK
        );
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException(
                'Utilisateur non authentifié.'
            );
        }

        return $user;
    }

    /**
     * Vérifie les champs nécessaires lors de la première configuration.
     *
     * @return string[]
     */
    private function getMissingRequiredFields(array $data): array
    {
        $requiredFields = [
            'nom',
            'adresse',
            'ville',
            'codePostal',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists($field, $data)
                || trim((string) $data[$field]) === ''
            ) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Transforme l'entité en tableau JSON sans exposer l'utilisateur.
     *
     * @return array<string, mixed>
     */
    private function formatEntreprise(Entreprise $entreprise): array
    {
        return [
            'id' => $entreprise->getId(),
            'nom' => $entreprise->getNom(),
            'logo' => $entreprise->getLogo(),
            'adresse' => $entreprise->getAdresse(),
            'ville' => $entreprise->getVille(),
            'codePostal' => $entreprise->getCodePostal(),
            'pays' => $entreprise->getPays(),
            'siret' => $entreprise->getSiret(),
            'tvaIntracom' => $entreprise->getTvaIntracom(),
            'telephone' => $entreprise->getTelephone(),
            'email' => $entreprise->getEmail(),
            'iban' => $entreprise->getIban(),
            'bic' => $entreprise->getBic(),
            'conditionsReglement' => $entreprise->getConditionsReglement(),
            'devise' => $entreprise->getDevise(),
            'tauxTvaDefaut' => $entreprise->getTauxTvaDefaut(),
            'delaiPaiement' => $entreprise->getDelaiPaiement(),
            'prefixeDevis' => $entreprise->getPrefixeDevis(),
            'prefixeFacture' => $entreprise->getPrefixeFacture(),
            'createdAt' => $entreprise->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $entreprise->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
