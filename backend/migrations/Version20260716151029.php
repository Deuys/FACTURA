<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716151029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Définit à 12,40 % le taux de pénalités de retard par défaut pour le second semestre 2026.';
    }

    public function up(Schema $schema): void
    {
        // Met à jour uniquement les entreprises conservant l’ancienne valeur par défaut.
        $this->addSql(
            "UPDATE entreprise
             SET taux_penalites_retard = 12.40
             WHERE taux_penalites_retard = 0.00"
        );

        // Définit la valeur utilisée pour les prochaines entreprises.
        $this->addSql(
            "ALTER TABLE entreprise
             CHANGE taux_penalites_retard
                    taux_penalites_retard
                    NUMERIC(5, 2)
                    DEFAULT '12.40'
                    NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE entreprise
             SET taux_penalites_retard = 0.00
             WHERE taux_penalites_retard = 12.40"
        );

        $this->addSql(
            "ALTER TABLE entreprise
             CHANGE taux_penalites_retard
                    taux_penalites_retard
                    NUMERIC(5, 2)
                    DEFAULT '0.00'
                    NOT NULL"
        );
    }
}
