<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714135227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’unicité du numéro de devis et rend le produit optionnel dans les lignes de devis';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B27C52BF55AE19E ON devis (numero)');
        $this->addSql('ALTER TABLE ligne_devis DROP FOREIGN KEY `FK_888B2F1BF347EFB`');
        $this->addSql('ALTER TABLE ligne_devis CHANGE produit_id produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_devis ADD CONSTRAINT FK_888B2F1BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8B27C52BF55AE19E ON devis');
        $this->addSql('ALTER TABLE ligne_devis DROP FOREIGN KEY FK_888B2F1BF347EFB');
        $this->addSql('ALTER TABLE ligne_devis CHANGE produit_id produit_id INT NOT NULL');
        $this->addSql('ALTER TABLE ligne_devis ADD CONSTRAINT `FK_888B2F1BF347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
