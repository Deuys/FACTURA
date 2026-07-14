<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714213546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table entreprise liée en relation un-à-un avec un utilisateur';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE entreprise (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, logo VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, code_postal VARCHAR(20) NOT NULL, pays VARCHAR(100) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, tva_intracom VARCHAR(30) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, iban VARCHAR(50) DEFAULT NULL, bic VARCHAR(20) DEFAULT NULL, conditions_reglement LONGTEXT DEFAULT NULL, devise VARCHAR(10) NOT NULL, taux_tva_defaut NUMERIC(5, 2) NOT NULL, delai_paiement INT NOT NULL, prefixe_devis VARCHAR(20) NOT NULL, prefixe_facture VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_D19FA60A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE entreprise ADD CONSTRAINT FK_D19FA60A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY FK_D19FA60A76ED395');
        $this->addSql('DROP TABLE entreprise');
    }
}
