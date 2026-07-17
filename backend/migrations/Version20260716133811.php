<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716133811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les informations juridiques et les paramètres de règlement des entreprises.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE entreprise
                ADD mode_paiement_defaut VARCHAR(30) NOT NULL DEFAULT 'Virement bancaire',
                ADD taux_penalites_retard NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
                ADD escompte_paiement_anticipe LONGTEXT DEFAULT NULL,
                ADD indemnite_recouvrement NUMERIC(10, 2) NOT NULL DEFAULT 40.00,
                ADD forme_juridique VARCHAR(100) DEFAULT NULL,
                ADD capital_social NUMERIC(15, 2) DEFAULT NULL,
                ADD rcs VARCHAR(100) DEFAULT NULL,
                ADD ville_rcs VARCHAR(100) DEFAULT NULL,
                ADD mention_tva LONGTEXT DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE entreprise
                DROP mode_paiement_defaut,
                DROP taux_penalites_retard,
                DROP escompte_paiement_anticipe,
                DROP indemnite_recouvrement,
                DROP forme_juridique,
                DROP capital_social,
                DROP rcs,
                DROP ville_rcs,
                DROP mention_tva'
        );
    }
}
