<?php

namespace App\Command;

use App\Enum\StatutFacture;
use App\Repository\FactureRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:factures:mettre-en-retard',
    description: 'Passe automatiquement en retard les factures échues.'
)]
final class MettreFacturesEnRetardCommand extends Command
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly EntityManagerInterface $entityManager,
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

        $factures = $this->factureRepository
            ->findFacturesAMettreEnRetard($aujourdhui);

        if ($factures === []) {
            $io->success(
                'Aucune facture à passer en retard aujourd’hui.'
            );

            return Command::SUCCESS;
        }

        foreach ($factures as $facture) {
            $facture->setStatut(StatutFacture::EN_RETARD);

            $user = $facture->getUser();

            if ($user !== null) {
                $this->notificationService->creer(
                    user: $user,
                    type: 'facture_en_retard',
                    titre: 'Facture en retard',
                    message: sprintf(
                        'La facture %s est arrivée à échéance et est maintenant en retard.',
                        $facture->getNumero()
                    ),
                    url: null
                );
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d facture(s) passée(s) en retard.',
            count($factures)
        ));

        return Command::SUCCESS;
    }
}
