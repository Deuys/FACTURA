<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714184131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE866410F55AE19E ON facture (numero)');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY `FK_611F5A29F347EFB`');
        $this->addSql('ALTER TABLE ligne_facture CHANGE produit_id produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_FE866410F55AE19E ON facture');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29F347EFB');
        $this->addSql('ALTER TABLE ligne_facture CHANGE produit_id produit_id INT NOT NULL');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT `FK_611F5A29F347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
