<?php

namespace App\Entity;

use App\Enum\TypeDelaiPaiement;
use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du client est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom de l’entreprise ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $entreprise = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L’adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'L’adresse e-mail n’est pas valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L’adresse e-mail ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[0-9+().\s-]+$/',
        message: 'Le numéro de téléphone contient des caractères invalides.'
    )]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L’adresse ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(
        max: 10,
        maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9\s-]+$/',
        message: 'Le code postal contient des caractères invalides.'
    )]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $ville = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $pays = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{14}$/',
        message: 'Le SIRET doit contenir exactement 14 chiffres.'
    )]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le numéro de TVA ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9]+$/',
        message: 'Le numéro de TVA contient des caractères invalides.'
    )]
    private ?string $tvaIntracom = null;

    #[ORM\Column(length: 30, enumType: TypeDelaiPaiement::class, nullable: true)]
    private ?TypeDelaiPaiement $typeDelaiPaiement = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le délai de paiement doit être positif ou nul.')]
    #[Assert\LessThanOrEqual(
        value: 365,
        message: 'Le délai de paiement ne peut pas dépasser 365 jours.'
    )]
    private ?int $delaiPaiement = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'clients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** @var Collection<int, Facture> */
    #[ORM\OneToMany(targetEntity: Facture::class, mappedBy: 'client')]
    private Collection $factures;

    /** @var Collection<int, Devis> */
    #[ORM\OneToMany(targetEntity: Devis::class, mappedBy: 'client')]
    private Collection $devis;

    public function __construct()
    {
        $this->factures = new ArrayCollection();
        $this->devis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
    public function getPrenom(): ?string
    {
        return $this->prenom;
    }
    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }
    public function getEntreprise(): ?string
    {
        return $this->entreprise;
    }
    public function setEntreprise(?string $entreprise): static
    {
        $this->entreprise = $entreprise;
        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): static
    {
        $this->email = $email;
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
    public function getAdresse(): ?string
    {
        return $this->adresse;
    }
    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }
    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }
    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }
    public function getVille(): ?string
    {
        return $this->ville;
    }
    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }
    public function getPays(): ?string
    {
        return $this->pays;
    }
    public function setPays(string $pays): static
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
    public function getTypeDelaiPaiement(): ?TypeDelaiPaiement
    {
        return $this->typeDelaiPaiement;
    }
    public function setTypeDelaiPaiement(?TypeDelaiPaiement $typeDelaiPaiement): static
    {
        $this->typeDelaiPaiement = $typeDelaiPaiement;
        return $this;
    }
    public function getDelaiPaiement(): ?int
    {
        return $this->delaiPaiement;
    }
    public function setDelaiPaiement(?int $delaiPaiement): static
    {
        $this->delaiPaiement = $delaiPaiement;
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
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, Facture> */
    public function getFactures(): Collection
    {
        return $this->factures;
    }
    public function addFacture(Facture $facture): static
    {
        if (!$this->factures->contains($facture)) {
            $this->factures->add($facture);
            $facture->setClient($this);
        }
        return $this;
    }
    public function removeFacture(Facture $facture): static
    {
        if ($this->factures->removeElement($facture) && $facture->getClient() === $this) {
            $facture->setClient(null);
        }
        return $this;
    }
    /** @return Collection<int, Devis> */
    public function getDevis(): Collection
    {
        return $this->devis;
    }
    public function addDevi(Devis $devi): static
    {
        if (!$this->devis->contains($devi)) {
            $this->devis->add($devi);
            $devi->setClient($this);
        }
        return $this;
    }
    public function removeDevi(Devis $devi): static
    {
        if ($this->devis->removeElement($devi) && $devi->getClient() === $this) {
            $devi->setClient(null);
        }
        return $this;
    }
}
