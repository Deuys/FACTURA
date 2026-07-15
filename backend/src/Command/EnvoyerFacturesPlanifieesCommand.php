<?php

namespace App\Command;

use App\Enum\StatutFacture;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:factures:envoyer-planifiees',
    description: 'Envoie automatiquement les factures planifiées arrivées à leur date prévue.',
)]
class EnvoyerFacturesPlanifieesCommand extends Command
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $aujourdhui = new \DateTimeImmutable('today');

        $factures = $this->factureRepository->createQueryBuilder('f')
            ->andWhere('f.statut = :statut')
            ->andWhere('f.dateEmissionPrevue IS NOT NULL')
            ->andWhere('f.dateEmissionPrevue <= :aujourdhui')
            ->setParameter('statut', StatutFacture::PLANIFIEE)
            ->setParameter('aujourdhui', $aujourdhui)
            ->getQuery()
            ->getResult();

        if ($factures === []) {
            $io->success('Aucune facture planifiée à envoyer aujourd’hui.');

            return Command::SUCCESS;
        }

        foreach ($factures as $facture) {
            $facture->setStatut(StatutFacture::ENVOYEE);
            $facture->setDateEmission($aujourdhui);
            $facture->setDateEmissionPrevue(null);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d facture(s) planifiée(s) envoyée(s) avec succès.',
            count($factures)
        ));

        return Command::SUCCESS;
    }
}
