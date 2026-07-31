<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731151710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend le numéro de devis unique par utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX UNIQ_8B27C52BF55AE19E ON devis'
        );

        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_DEVIS_USER_NUMERO ON devis (user_id, numero)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX UNIQ_DEVIS_USER_NUMERO ON devis'
        );

        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_8B27C52BF55AE19E ON devis (numero)'
        );
    }
}
