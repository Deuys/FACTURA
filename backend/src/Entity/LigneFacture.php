<?php

namespace App\Entity;

use App\Repository\LigneFactureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneFactureRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LigneFacture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La quantité est obligatoire.')]
    #[Assert\Positive(message: 'La quantité doit être supérieure à zéro.')]
    private ?string $quantite = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix unitaire HT est obligatoire.')]
    #[Assert\PositiveOrZero(
        message: 'Le prix unitaire HT ne peut pas être négatif.'
    )]
    private ?string $prixUnitaireHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le taux de TVA est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de TVA doit être compris entre {{ min }} et {{ max }}.'
    )]
    private ?string $tva = '20.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'La remise doit être comprise entre {{ min }} et {{ max }}.'
    )]
    private ?string $remise = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $totalHT = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'ligneFactures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La facture associée est obligatoire.')]
    private ?Facture $facture = null;

    #[ORM\ManyToOne(inversedBy: 'ligneFactures')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Produit $produit = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'La désignation est obligatoire.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'La désignation ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $designation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(
        max: 30,
        maxMessage: 'L’unité ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $unite = null;
    
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $totalTVA = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $totalTTC = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixUnitaireHT(): ?string
    {
        return $this->prixUnitaireHT;
    }

    public function setPrixUnitaireHT(string $prixUnitaireHT): static
    {
        $this->prixUnitaireHT = $prixUnitaireHT;

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

    public function getRemise(): ?string
    {
        return $this->remise;
    }

    public function setRemise(?string $remise): static
    {
        $this->remise = $remise;

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

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

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

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;

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
}
