<?php

namespace App\Entity;

use App\Enum\ModePaiement;
use App\Enum\StatutPaiement;
use App\Enum\OriginePaiement;
use App\Repository\PaiementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: OriginePaiement::class)]
    #[Assert\NotNull]
    private OriginePaiement $origine = OriginePaiement::MANUEL;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L’identifiant externe ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $externalPaymentId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro.')]
    private ?string $montant = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date du paiement est obligatoire.')]
    #[Assert\LessThanOrEqual(
        'today',
        message: 'La date du paiement ne peut pas être dans le futur.'
    )]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(length: 30, enumType: ModePaiement::class)]
    #[Assert\NotNull(message: 'Le mode de paiement est obligatoire.')]
    private ModePaiement $modePaiement;

    #[ORM\Column(length: 20, enumType: StatutPaiement::class)]
    #[Assert\NotNull(message: 'Le statut du paiement est obligatoire.')]
    private StatutPaiement $statut = StatutPaiement::CONFIRME;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $reference = null;

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

    #[ORM\ManyToOne(inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La facture associée est obligatoire.')]
    private ?Facture $facture = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrigine(): OriginePaiement
    {
        return $this->origine;
    }

    public function setOrigine(OriginePaiement $origine): static
    {
        $this->origine = $origine;

        return $this;
    }

    public function getExternalPaymentId(): ?string
    {
        return $this->externalPaymentId;
    }

    public function setExternalPaymentId(?string $externalPaymentId): static
    {
        $this->externalPaymentId = $externalPaymentId;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(\DateTimeImmutable $datePaiement): static
    {
        $this->datePaiement = $datePaiement;

        return $this;
    }

    public function getModePaiement(): ModePaiement
    {
        return $this->modePaiement;
    }

    public function setModePaiement(ModePaiement $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

        return $this;
    }

    public function getStatut(): StatutPaiement
    {
        return $this->statut;
    }

    public function setStatut(StatutPaiement $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

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

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;

        return $this;
    }

    #[Assert\Callback]
    public function validateExternalPaymentId(
        ExecutionContextInterface $context
    ): void {
        if (
            $this->origine !== OriginePaiement::MANUEL
            && (
                $this->externalPaymentId === null
                || trim($this->externalPaymentId) === ''
            )
        ) {
            $context
                ->buildViolation(
                    'L’identifiant externe est obligatoire pour un paiement provenant d’un prestataire.'
                )
                ->atPath('externalPaymentId')
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
}
