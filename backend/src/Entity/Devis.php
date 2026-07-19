<?php

namespace App\Entity;

use App\Enum\StatutDevis;
use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['numero'],
    message: 'Ce numéro de devis existe déjà.'
)]
class Devis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro est obligatoire.')]
    #[Assert\Length(
        max: 30,
        maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date d'émission est obligatoire.")]
    private ?\DateTimeImmutable $dateEmission = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de validité est obligatoire.')]
    private ?\DateTimeImmutable $dateValidite = null;

    #[ORM\Column(length: 20, enumType: StatutDevis::class)]
    #[Assert\NotNull(message: 'Le statut est obligatoire.')]
    private StatutDevis $statut = StatutDevis::BROUILLON;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le total HT est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le total HT doit être supérieur ou égal à 0.')]
    private ?string $totalHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le total TVA est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le total TVA doit être supérieur ou égal à 0.')]
    private ?string $totalTVA = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le total TTC est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le total TTC doit être supérieur ou égal à 0.')]
    private ?string $totalTTC = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "L'utilisateur est obligatoire.")]
    private ?User $user = null;

    /**
     * @var Collection<int, LigneDevis>
     */
    #[ORM\OneToMany(
        targetEntity: LigneDevis::class,
        mappedBy: 'devis',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[Assert\Valid]
    private Collection $ligneDevis;

    public function __construct()
    {
        $this->ligneDevis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDateEmission(): ?\DateTimeImmutable
    {
        return $this->dateEmission;
    }

    public function setDateEmission(\DateTimeImmutable $dateEmission): static
    {
        $this->dateEmission = $dateEmission;

        return $this;
    }

    public function getDateValidite(): ?\DateTimeImmutable
    {
        return $this->dateValidite;
    }

    public function setDateValidite(\DateTimeImmutable $dateValidite): static
    {
        $this->dateValidite = $dateValidite;

        return $this;
    }

    public function getStatut(): StatutDevis
    {
        return $this->statut;
    }

    public function setStatut(StatutDevis $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getTotalHT(): ?string
    {
        return $this->totalHT;
    }

    public function setTotalHT(string $totalHT): static
    {
        $this->totalHT = $totalHT;

        return $this;
    }

    public function getTotalTVA(): ?string
    {
        return $this->totalTVA;
    }

    public function setTotalTVA(string $totalTVA): static
    {
        $this->totalTVA = $totalTVA;

        return $this;
    }

    public function getTotalTTC(): ?string
    {
        return $this->totalTTC;
    }

    public function setTotalTTC(string $totalTTC): static
    {
        $this->totalTTC = $totalTTC;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

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

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (
            $this->dateEmission !== null
            && $this->dateValidite !== null
            && $this->dateValidite < $this->dateEmission
        ) {
            $context
                ->buildViolation(
                    "La date de validité doit être postérieure ou égale à la date d'émission."
                )
                ->atPath('dateValidite')
                ->addViolation();
        }
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

    /**
     * @return Collection<int, LigneDevis>
     */
    public function getLigneDevis(): Collection
    {
        return $this->ligneDevis;
    }

    public function addLigneDevis(LigneDevis $ligneDevis): static
    {
        if (!$this->ligneDevis->contains($ligneDevis)) {
            $this->ligneDevis->add($ligneDevis);
            $ligneDevis->setDevis($this);
        }

        return $this;
    }

    public function removeLigneDevis(LigneDevis $ligneDevis): static
    {
        if ($this->ligneDevis->removeElement($ligneDevis)) {
            if ($ligneDevis->getDevis() === $this) {
                $ligneDevis->setDevis(null);
            }
        }

        return $this;
    }
}
