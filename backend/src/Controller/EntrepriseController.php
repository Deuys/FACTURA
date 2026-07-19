<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Enum\ModePaiement;
use App\Enum\TypeDelaiPaiement;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/entreprise')]
#[IsGranted('ROLE_USER')]
final class EntrepriseController extends AbstractController
{
    /**
     * Récupère les paramètres de l'entreprise de l'utilisateur connecté.
     */
    #[Route('', name: 'api_entreprise_show', methods: ['GET'])]
    public function show(
        EntrepriseRepository $entrepriseRepository
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $entreprise = $entrepriseRepository->findOneBy([
            'user' => $user,
        ]);

        if ($entreprise === null) {
            return $this->json(
                ['message' => 'Aucune entreprise configurée pour cet utilisateur.'],
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
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(
                ['message' => 'Le corps de la requête contient un JSON invalide.'],
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
            $entreprise->setNom(trim((string) $data['nom']));
        }

        if (array_key_exists('logo', $data)) {
            $entreprise->setLogo($this->nullableString($data['logo']));
        }

        if (array_key_exists('adresse', $data)) {
            $entreprise->setAdresse(trim((string) $data['adresse']));
        }

        if (array_key_exists('ville', $data)) {
            $entreprise->setVille(trim((string) $data['ville']));
        }

        if (array_key_exists('codePostal', $data)) {
            $entreprise->setCodePostal(trim((string) $data['codePostal']));
        }

        if (array_key_exists('pays', $data)) {
            $entreprise->setPays($this->nullableString($data['pays']));
        }

        if (array_key_exists('siret', $data)) {
            $entreprise->setSiret($this->nullableString($data['siret']));
        }

        if (array_key_exists('tvaIntracom', $data)) {
            $entreprise->setTvaIntracom(
                $this->nullableUppercaseString($data['tvaIntracom'])
            );
        }

        if (array_key_exists('telephone', $data)) {
            $entreprise->setTelephone(
                $this->nullableString($data['telephone'])
            );
        }

        if (array_key_exists('email', $data)) {
            $entreprise->setEmail(
                $this->nullableString($data['email'])
            );
        }

        if (array_key_exists('iban', $data)) {
            $entreprise->setIban(
                $this->nullableUppercaseStringWithoutSpaces($data['iban'])
            );
        }

        if (array_key_exists('bic', $data)) {
            $entreprise->setBic(
                $this->nullableUppercaseStringWithoutSpaces($data['bic'])
            );
        }

        if (array_key_exists('conditionsReglement', $data)) {
            $entreprise->setConditionsReglement(
                $this->nullableString($data['conditionsReglement'])
            );
        }

        if (array_key_exists('typeDelaiPaiement', $data)) {
            $typeDelaiPaiement = TypeDelaiPaiement::tryFrom(
                trim((string) $data['typeDelaiPaiement'])
            );

            if ($typeDelaiPaiement === null) {
                return $this->json(
                    [
                        'message' => 'Le type de délai de paiement est invalide.',
                        'valeursAutorisees' => array_map(
                            static fn(TypeDelaiPaiement $type): string => $type->value,
                            TypeDelaiPaiement::cases()
                        ),
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setTypeDelaiPaiement($typeDelaiPaiement);
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

            $entreprise->setDelaiPaiement((int) $data['delaiPaiement']);
        }

        if (array_key_exists('modePaiementDefaut', $data)) {
            $modePaiement = ModePaiement::tryFrom(
                trim((string) $data['modePaiementDefaut'])
            );

            if ($modePaiement === null) {
                return $this->json(
                    [
                        'message' => 'Le mode de paiement par défaut est invalide.',
                        'valeursAutorisees' => array_map(
                            static fn(ModePaiement $mode): string => $mode->value,
                            ModePaiement::cases()
                        ),
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setModePaiementDefaut($modePaiement);
        }

        if (array_key_exists('tauxPenalitesRetard', $data)) {
            $tauxPenalitesRetard = $this->normalizeDecimal(
                $data['tauxPenalitesRetard']
            );

            if ($tauxPenalitesRetard === null) {
                return $this->json(
                    ['message' => 'Le taux de pénalités doit être un nombre.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setTauxPenalitesRetard($tauxPenalitesRetard);
        }

        if (array_key_exists('escomptePaiementAnticipe', $data)) {
            $entreprise->setEscomptePaiementAnticipe(
                $this->nullableString($data['escomptePaiementAnticipe'])
            );
        }

        if (array_key_exists('indemniteRecouvrement', $data)) {
            $indemniteRecouvrement = $this->normalizeDecimal(
                $data['indemniteRecouvrement']
            );

            if ($indemniteRecouvrement === null) {
                return $this->json(
                    ['message' => 'L’indemnité de recouvrement doit être un nombre.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setIndemniteRecouvrement(
                $indemniteRecouvrement
            );
        }

        if (array_key_exists('formeJuridique', $data)) {
            $entreprise->setFormeJuridique(
                $this->nullableString($data['formeJuridique'])
            );
        }

        if (array_key_exists('capitalSocial', $data)) {
            if ($this->isNullOrEmpty($data['capitalSocial'])) {
                $entreprise->setCapitalSocial(null);
            } else {
                $capitalSocial = $this->normalizeDecimal(
                    $data['capitalSocial']
                );

                if ($capitalSocial === null) {
                    return $this->json(
                        ['message' => 'Le capital social doit être un nombre.'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                $entreprise->setCapitalSocial($capitalSocial);
            }
        }

        if (array_key_exists('rcs', $data)) {
            $entreprise->setRcs($this->nullableString($data['rcs']));
        }

        if (array_key_exists('villeRcs', $data)) {
            $entreprise->setVilleRcs(
                $this->nullableString($data['villeRcs'])
            );
        }

        if (array_key_exists('mentionTva', $data)) {
            $entreprise->setMentionTva(
                $this->nullableString($data['mentionTva'])
            );
        }

        if (array_key_exists('devise', $data)) {
            $entreprise->setDevise(
                strtoupper(trim((string) $data['devise']))
            );
        }

        if (array_key_exists('tauxTvaDefaut', $data)) {
            $tauxTvaDefaut = $this->normalizeDecimal(
                $data['tauxTvaDefaut']
            );

            if ($tauxTvaDefaut === null) {
                return $this->json(
                    ['message' => 'Le taux de TVA doit être un nombre.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $entreprise->setTauxTvaDefaut($tauxTvaDefaut);
        }

        if (array_key_exists('prefixeDevis', $data)) {
            $entreprise->setPrefixeDevis(
                strtoupper(trim((string) $data['prefixeDevis']))
            );
        }

        if (array_key_exists('prefixeFacture', $data)) {
            $entreprise->setPrefixeFacture(
                strtoupper(trim((string) $data['prefixeFacture']))
            );
        }

        $errors = $validator->validate($entreprise);

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
                Response::HTTP_BAD_REQUEST
            );
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

    private function nullableUppercaseString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null ? strtoupper($value) : null;
    }

    private function nullableUppercaseStringWithoutSpaces(mixed $value): ?string
    {
        $value = $this->nullableUppercaseString($value);

        return $value !== null
            ? str_replace(' ', '', $value)
            : null;
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = str_replace(
            [' ', ','],
            ['', '.'],
            trim((string) $value)
        );

        if ($normalizedValue === '' || !is_numeric($normalizedValue)) {
            return null;
        }

        return number_format(
            (float) $normalizedValue,
            2,
            '.',
            ''
        );
    }

    private function isNullOrEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
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
            'typeDelaiPaiement' => $entreprise
                ->getTypeDelaiPaiement()
                ->value,
            'delaiPaiement' => $entreprise->getDelaiPaiement(),
            'modePaiementDefaut' => $entreprise
                ->getModePaiementDefaut()
                ->value,
            'tauxPenalitesRetard' => $entreprise
                ->getTauxPenalitesRetard(),
            'escomptePaiementAnticipe' => $entreprise
                ->getEscomptePaiementAnticipe(),
            'indemniteRecouvrement' => $entreprise
                ->getIndemniteRecouvrement(),
            'formeJuridique' => $entreprise->getFormeJuridique(),
            'capitalSocial' => $entreprise->getCapitalSocial(),
            'rcs' => $entreprise->getRcs(),
            'villeRcs' => $entreprise->getVilleRcs(),
            'mentionTva' => $entreprise->getMentionTva(),
            'devise' => $entreprise->getDevise(),
            'tauxTvaDefaut' => $entreprise->getTauxTvaDefaut(),
            'prefixeDevis' => $entreprise->getPrefixeDevis(),
            'prefixeFacture' => $entreprise->getPrefixeFacture(),
            'createdAt' => $entreprise->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $entreprise->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
