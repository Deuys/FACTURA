<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_IDENTIFIER_EMAIL',
    fields: ['email']
)]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cette adresse e-mail est déjà utilisée.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(
        message: 'L’adresse e-mail est obligatoire.'
    )]
    #[Assert\Email(
        message: 'L’adresse e-mail est invalide.'
    )]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L’adresse e-mail ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe hashé.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(
        message: 'Le mot de passe est obligatoire.'
    )]
    private ?string $password = null;

    /**
     * @var Collection<int, Client>
     */
    #[ORM\OneToMany(
        targetEntity: Client::class,
        mappedBy: 'user'
    )]
    private Collection $clients;

    /**
     * @var Collection<int, Produit>
     */
    #[ORM\OneToMany(
        targetEntity: Produit::class,
        mappedBy: 'user'
    )]
    private Collection $produits;

    /**
     * @var Collection<int, Facture>
     */
    #[ORM\OneToMany(
        targetEntity: Facture::class,
        mappedBy: 'user'
    )]
    private Collection $factures;

    /**
     * @var Collection<int, Devis>
     */
    #[ORM\OneToMany(
        targetEntity: Devis::class,
        mappedBy: 'user'
    )]
    private Collection $devis;

    /**
     * @var Collection<int, Activite>
     */
    #[ORM\OneToMany(
        targetEntity: Activite::class,
        mappedBy: 'user',
        orphanRemoval: true
    )]
    private Collection $activites;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(
        targetEntity: Notification::class,
        mappedBy: 'user',
        orphanRemoval: true
    )]
    private Collection $notifications;

    #[ORM\OneToOne(
        mappedBy: 'user',
        cascade: ['persist', 'remove']
    )]
    private ?Entreprise $entreprise = null;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->produits = new ArrayCollection();
        $this->factures = new ArrayCollection();
        $this->devis = new ArrayCollection();
        $this->activites = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;

        $data["\0" . self::class . "\0password"] = hash(
            'crc32c',
            (string) $this->password
        );

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void {}

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): static
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
            $client->setUser($this);
        }

        return $this;
    }

    public function removeClient(Client $client): static
    {
        if (
            $this->clients->removeElement($client)
            && $client->getUser() === $this
        ) {
            $client->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Produit>
     */
    public function getProduits(): Collection
    {
        return $this->produits;
    }

    public function addProduit(Produit $produit): static
    {
        if (!$this->produits->contains($produit)) {
            $this->produits->add($produit);
            $produit->setUser($this);
        }

        return $this;
    }

    public function removeProduit(Produit $produit): static
    {
        if (
            $this->produits->removeElement($produit)
            && $produit->getUser() === $this
        ) {
            $produit->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Facture>
     */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function addFacture(Facture $facture): static
    {
        if (!$this->factures->contains($facture)) {
            $this->factures->add($facture);
            $facture->setUser($this);
        }

        return $this;
    }

    public function removeFacture(Facture $facture): static
    {
        if (
            $this->factures->removeElement($facture)
            && $facture->getUser() === $this
        ) {
            $facture->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Devis>
     */
    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevis(Devis $devis): static
    {
        if (!$this->devis->contains($devis)) {
            $this->devis->add($devis);
            $devis->setUser($this);
        }

        return $this;
    }

    public function removeDevis(Devis $devis): static
    {
        if (
            $this->devis->removeElement($devis)
            && $devis->getUser() === $this
        ) {
            $devis->setUser(null);
        }

        return $this;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;

        if (
            $entreprise !== null
            && $entreprise->getUser() !== $this
        ) {
            $entreprise->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        return $this->activites;
    }

    public function addActivite(Activite $activite): static
    {
        if (!$this->activites->contains($activite)) {
            $this->activites->add($activite);
            $activite->setUser($this);
        }

        return $this;
    }

    public function removeActivite(Activite $activite): static
    {
        if (
            $this->activites->removeElement($activite)
            && $activite->getUser() === $this
        ) {
            $activite->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if (
            $this->notifications->removeElement($notification)
            && $notification->getUser() === $this
        ) {
            $notification->setUser(null);
        }

        return $this;
    }
}
