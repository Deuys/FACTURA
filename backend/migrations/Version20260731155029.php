<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731155029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_FE866410F55AE19E ON facture');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FACTURE_USER_NUMERO ON facture (user_id, numero)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_FACTURE_USER_NUMERO ON facture');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE866410F55AE19E ON facture (numero)');
    }
}
