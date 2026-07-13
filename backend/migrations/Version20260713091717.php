<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713091717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table ligne_facture et de ses relations avec facture et produit';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ligne_facture (id INT AUTO_INCREMENT NOT NULL, quantite NUMERIC(10, 2) NOT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, tva NUMERIC(5, 2) NOT NULL, remise NUMERIC(5, 2) DEFAULT NULL, total_ht NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, designation VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, unite VARCHAR(30) DEFAULT NULL, total_tva NUMERIC(10, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, facture_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_611F5A297F2DEE08 (facture_id), INDEX IDX_611F5A29F347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A297F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
        $this->addSql('ALTER TABLE ligne_facture ADD CONSTRAINT FK_611F5A29F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A297F2DEE08');
        $this->addSql('ALTER TABLE ligne_facture DROP FOREIGN KEY FK_611F5A29F347EFB');
        $this->addSql('DROP TABLE ligne_facture');
    }
}
