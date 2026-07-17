<?php

namespace App\Service;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActiviteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function enregistrer(
        User $user,
        string $type,
        string $titre,
        string $description
    ): Activite {
        $activite = new Activite();

        $activite->setUser($user);
        $activite->setType(trim($type));
        $activite->setTitre(trim($titre));
        $activite->setDescription(trim($description));

        $this->entityManager->persist($activite);

        return $activite;
    }
}
