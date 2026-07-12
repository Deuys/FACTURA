<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260712160452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table client et de sa relation avec user';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, entreprise VARCHAR(150) DEFAULT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(100) NOT NULL, siret VARCHAR(14) DEFAULT NULL, tva_intracom VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_C7440455A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455A76ED395');
        $this->addSql('DROP TABLE client');
    }
}
