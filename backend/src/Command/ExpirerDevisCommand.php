<?php

namespace App\Command;

use App\Entity\Devis;
use App\Enum\StatutDevis;
use App\Repository\DevisRepository;
use App\Service\ActiviteService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:devis:expirer',
    description: 'Marque automatiquement comme expirés les devis envoyés dont la date de validité est dépassée.'
)]
final class ExpirerDevisCommand extends Command
{
    public function __construct(
        private readonly DevisRepository $devisRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiviteService $activiteService,
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $aujourdhui = new \DateTimeImmutable('today');

        $devisExpires = $this->devisRepository
            ->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.dateValidite < :aujourdhui')
            ->setParameter('statut', StatutDevis::ENVOYE)
            ->setParameter('aujourdhui', $aujourdhui)
            ->getQuery()
            ->getResult();

        /** @var Devis $devis */
        foreach ($devisExpires as $devis) {
            $user = $devis->getUser();

            if ($user === null) {
                continue;
            }

            $devis->setStatut(StatutDevis::EXPIRE);

            $nomClient = $devis->getClient()?->getEntreprise()
                ?: trim(sprintf(
                    '%s %s',
                    $devis->getClient()?->getPrenom() ?? '',
                    $devis->getClient()?->getNom() ?? ''
                ));

            $this->activiteService->enregistrer(
                user: $user,
                type: 'devis_expire',
                titre: 'Devis expiré',
                description: sprintf(
                    '%s • %s',
                    $devis->getNumero(),
                    $nomClient
                )
            );

            $this->notificationService->creer(
                user: $user,
                type: 'devis_expire',
                titre: 'Devis expiré',
                message: sprintf(
                    'Le devis %s est arrivé à expiration.',
                    $devis->getNumero()
                ),
                url: null
            );
        }

        $this->entityManager->flush();

        $nombreDevisExpires = count($devisExpires);

        if ($nombreDevisExpires === 0) {
            $io->success('Aucun devis à expirer.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%d devis expiré(s) avec succès.',
            $nombreDevisExpires
        ));

        return Command::SUCCESS;
    }
}
