<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom du produit ou service est obligatoire.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(
        choices: ['produit', 'service'],
        message: 'Le type doit être produit ou service.'
    )]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La référence est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9._\-\/]+$/',
        message: 'La référence contient des caractères non autorisés.'
    )]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix HT est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d{1,8}(\.\d{1,2})?$/',
        message: 'Le prix HT doit être un montant positif avec au maximum 2 décimales.'
    )]
    private ?string $prixHT = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le taux de TVA est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de TVA doit être compris entre {{ min }} et {{ max }}.'
    )]
    private ?string $tva = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(
        max: 30,
        maxMessage: 'L’unité ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $unite = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, LigneFacture>
     */
    #[ORM\OneToMany(targetEntity: LigneFacture::class, mappedBy: 'produit')]
    private Collection $ligneFactures;

    /**
     * @var Collection<int, LigneDevis>
     */
    #[ORM\OneToMany(targetEntity: LigneDevis::class, mappedBy: 'produit')]
    private Collection $ligneDevis;

    public function __construct()
    {
        $this->ligneFactures = new ArrayCollection();
        $this->ligneDevis = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getPrixHT(): ?string
    {
        return $this->prixHT;
    }

    public function setPrixHT(string $prixHT): static
    {
        $this->prixHT = $prixHT;
        return $this;
    }

    public function getTva(): ?string
    {
        return $this->tva;
    }

    public function setTva(string $tva): static
    {
        $this->tva = $tva;
        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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

    /**
     * @return Collection<int, LigneFacture>
     */
    public function getLigneFactures(): Collection
    {
        return $this->ligneFactures;
    }

    public function addLigneFacture(LigneFacture $ligneFacture): static
    {
        if (!$this->ligneFactures->contains($ligneFacture)) {
            $this->ligneFactures->add($ligneFacture);
            $ligneFacture->setProduit($this);
        }

        return $this;
    }

    public function removeLigneFacture(LigneFacture $ligneFacture): static
    {
        if (
            $this->ligneFactures->removeElement($ligneFacture)
            && $ligneFacture->getProduit() === $this
        ) {
            $ligneFacture->setProduit(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, LigneDevis>
     */
    public function getLigneDevis(): Collection
    {
        return $this->ligneDevis;
    }

    public function addLigneDevi(LigneDevis $ligneDevi): static
    {
        if (!$this->ligneDevis->contains($ligneDevi)) {
            $this->ligneDevis->add($ligneDevi);
            $ligneDevi->setProduit($this);
        }

        return $this;
    }

    public function removeLigneDevi(LigneDevis $ligneDevi): static
    {
        if (
            $this->ligneDevis->removeElement($ligneDevi)
            && $ligneDevi->getProduit() === $this
        ) {
            $ligneDevi->setProduit(null);
        }

        return $this;
    }
}
