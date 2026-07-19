<?php

namespace App\Entity;

use App\Repository\EntrepriseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\ModePaiement;
use App\Enum\TypeDelaiPaiement;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Entreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\OneToOne(inversedBy: 'entreprise')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L’utilisateur associé est obligatoire.')]
    private ?User $user = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom de l’entreprise est obligatoire.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le chemin du logo ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $logo = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L’adresse est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L’adresse ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $adresse = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $ville = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $codePostal = null;
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $pays = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{14}$/',
        message: 'Le SIRET doit contenir exactement 14 chiffres.'
    )]
    private ?string $siret = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Z]{2}[A-Z0-9]{2,13}$/',
        message: 'Le numéro de TVA intracommunautaire est invalide.'
    )]
    private ?string $tvaIntracom = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(
        max: 30,
        maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[0-9+().\s-]+$/',
        message: 'Le numéro de téléphone contient des caractères invalides.'
    )]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'L’adresse e-mail de l’entreprise est invalide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/',
        message: 'L’IBAN est invalide.'
    )]
    private ?string $iban = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]{8}([A-Z0-9]{3})?$/',
        message: 'Le BIC est invalide.'
    )]
    private ?string $bic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Les conditions de règlement ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $conditionsReglement = null;

    #[ORM\Column(
        length: 30,
        enumType: TypeDelaiPaiement::class,
        options: ['default' => 'Jours nets']
    )]
    #[Assert\NotNull(message: 'Le type de délai de paiement est obligatoire.')]
    private TypeDelaiPaiement $typeDelaiPaiement =
    TypeDelaiPaiement::JOURS_NETS;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le délai de paiement est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 365,
        notInRangeMessage: 'Le délai de paiement doit être compris entre {{ min }} et {{ max }} jours.'
    )]
    private ?int $delaiPaiement = 30;

    #[ORM\Column(
        length: 30,
        enumType: ModePaiement::class,
        options: ['default' => 'Virement bancaire']
    )]
    #[Assert\NotNull(message: 'Le mode de paiement par défaut est obligatoire.')]
    private ModePaiement $modePaiementDefaut = ModePaiement::VIREMENT;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 5,
        scale: 2,
        options: ['default' => '12.40']
    )]
    #[Assert\NotBlank(message: 'Le taux de pénalités est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de pénalités doit être compris entre {{ min }} et {{ max }}.'
    )]
    private ?string $tauxPenalitesRetard = '12.40';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $escomptePaiementAnticipe = null;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['default' => '40.00']
    )]
    #[Assert\NotBlank(message: 'L’indemnité de recouvrement est obligatoire.')]
    #[Assert\PositiveOrZero(
        message: 'L’indemnité de recouvrement ne peut pas être négative.'
    )]
    private ?string $indemniteRecouvrement = '40.00';

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $formeJuridique = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(
        message: 'Le capital social ne peut pas être négatif.'
    )]
    private ?string $capitalSocial = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $rcs = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $villeRcs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $mentionTva = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'La devise est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[A-Z]{3}$/',
        message: 'La devise doit être un code ISO composé de trois lettres, par exemple EUR.'
    )]
    private ?string $devise = 'EUR';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le taux de TVA par défaut est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de TVA doit être compris entre {{ min }} et {{ max }}.'
    )]
    private ?string $tauxTvaDefaut = '20.00';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le préfixe des devis est obligatoire.')]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9_-]+$/',
        message: 'Le préfixe des devis ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.'
    )]
    private ?string $prefixeDevis = 'DV';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le préfixe des factures est obligatoire.')]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9_-]+$/',
        message: 'Le préfixe des factures ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.'
    )]
    private ?string $prefixeFacture = 'FAC';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getTvaIntracom(): ?string
    {
        return $this->tvaIntracom;
    }

    public function setTvaIntracom(?string $tvaIntracom): static
    {
        $this->tvaIntracom = $tvaIntracom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;

        return $this;
    }

    public function getConditionsReglement(): ?string
    {
        return $this->conditionsReglement;
    }

    public function setConditionsReglement(?string $conditionsReglement): static
    {
        $this->conditionsReglement = $conditionsReglement;

        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = $devise;

        return $this;
    }

    public function getTauxTvaDefaut(): ?string
    {
        return $this->tauxTvaDefaut;
    }

    public function setTauxTvaDefaut(string $tauxTvaDefaut): static
    {
        $this->tauxTvaDefaut = $tauxTvaDefaut;

        return $this;
    }

    public function getTypeDelaiPaiement(): TypeDelaiPaiement
    {
        return $this->typeDelaiPaiement;
    }

    public function setTypeDelaiPaiement(
        TypeDelaiPaiement $typeDelaiPaiement
    ): static {
        $this->typeDelaiPaiement = $typeDelaiPaiement;

        return $this;
    }

    public function getDelaiPaiement(): ?int
    {
        return $this->delaiPaiement;
    }

    public function setDelaiPaiement(int $delaiPaiement): static
    {
        $this->delaiPaiement = $delaiPaiement;

        return $this;
    }

    public function getPrefixeDevis(): ?string
    {
        return $this->prefixeDevis;
    }

    public function setPrefixeDevis(string $prefixeDevis): static
    {
        $this->prefixeDevis = $prefixeDevis;

        return $this;
    }

    public function getPrefixeFacture(): ?string
    {
        return $this->prefixeFacture;
    }

    public function setPrefixeFacture(string $prefixeFacture): static
    {
        $this->prefixeFacture = $prefixeFacture;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();

        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getModePaiementDefaut(): ModePaiement
    {
        return $this->modePaiementDefaut;
    }

    public function setModePaiementDefaut(ModePaiement $modePaiementDefaut): static
    {
        $this->modePaiementDefaut = $modePaiementDefaut;

        return $this;
    }

    public function getTauxPenalitesRetard(): ?string
    {
        return $this->tauxPenalitesRetard;
    }

    public function setTauxPenalitesRetard(string $tauxPenalitesRetard): static
    {
        $this->tauxPenalitesRetard = $tauxPenalitesRetard;

        return $this;
    }

    public function getEscomptePaiementAnticipe(): ?string
    {
        return $this->escomptePaiementAnticipe;
    }

    public function setEscomptePaiementAnticipe(
        ?string $escomptePaiementAnticipe
    ): static {
        $this->escomptePaiementAnticipe = $escomptePaiementAnticipe;

        return $this;
    }

    public function getIndemniteRecouvrement(): ?string
    {
        return $this->indemniteRecouvrement;
    }

    public function setIndemniteRecouvrement(
        string $indemniteRecouvrement
    ): static {
        $this->indemniteRecouvrement = $indemniteRecouvrement;

        return $this;
    }

    public function getFormeJuridique(): ?string
    {
        return $this->formeJuridique;
    }

    public function setFormeJuridique(?string $formeJuridique): static
    {
        $this->formeJuridique = $formeJuridique;

        return $this;
    }

    public function getCapitalSocial(): ?string
    {
        return $this->capitalSocial;
    }

    public function setCapitalSocial(?string $capitalSocial): static
    {
        $this->capitalSocial = $capitalSocial;

        return $this;
    }

    public function getRcs(): ?string
    {
        return $this->rcs;
    }

    public function setRcs(?string $rcs): static
    {
        $this->rcs = $rcs;

        return $this;
    }

    public function getVilleRcs(): ?string
    {
        return $this->villeRcs;
    }

    public function setVilleRcs(?string $villeRcs): static
    {
        $this->villeRcs = $villeRcs;

        return $this;
    }

    public function getMentionTva(): ?string
    {
        return $this->mentionTva;
    }

    public function setMentionTva(?string $mentionTva): static
    {
        $this->mentionTva = $mentionTva;

        return $this;
    }
}
