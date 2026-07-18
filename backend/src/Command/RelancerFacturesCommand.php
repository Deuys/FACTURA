<?php

namespace App\Command;

use App\Repository\FactureRepository;
use App\Service\FactureMailerService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:factures:relancer',
    description: 'Envoie les relances automatiques des factures en retard.'
)]
final class RelancerFacturesCommand extends Command
{
    private const DELAI_ENTRE_RELANCES = 7;

    private const NOMBRE_MAXIMUM_RELANCES = 3;

    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly FactureMailerService $factureMailerService,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $maintenant = new \DateTimeImmutable();

        $factures = $this->factureRepository
            ->findFacturesARelancer(
                aujourdhui: new \DateTimeImmutable('today'),
                delaiEntreRelances: self::DELAI_ENTRE_RELANCES,
                nombreMaximumRelances: self::NOMBRE_MAXIMUM_RELANCES
            );

        if ($factures === []) {
            $io->success(
                'Aucune facture à relancer aujourd’hui.'
            );

            return Command::SUCCESS;
        }

        $nombreEnvoyees = 0;
        $nombreErreurs = 0;

        foreach ($factures as $facture) {
            try {
                $numeroRelance =
                    $facture->getNombreRelances() + 1;

                $this->factureMailerService
                    ->sendRelance($facture);

                $facture->setDerniereRelanceAt($maintenant);
                $facture->incrementerNombreRelances();

                $user = $facture->getUser();

                if ($user !== null) {
                    $this->notificationService->creer(
                        user: $user,
                        type: 'facture_relancee',
                        titre: 'Relance envoyée',
                        message: sprintf(
                            'La relance n°%d de la facture %s a été envoyée.',
                            $numeroRelance,
                            $facture->getNumero()
                        ),
                        url: null
                    );
                }

                ++$nombreEnvoyees;

                $io->writeln(sprintf(
                    'Relance envoyée pour la facture %s.',
                    $facture->getNumero()
                ));
            } catch (\Throwable $exception) {
                ++$nombreErreurs;

                $io->error(sprintf(
                    'Erreur pour la facture %s : %s',
                    $facture->getNumero(),
                    $exception->getMessage()
                ));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d relance(s) envoyée(s), %d erreur(s).',
            $nombreEnvoyees,
            $nombreErreurs
        ));

        return $nombreErreurs > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
