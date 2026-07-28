<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728105120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD prenom VARCHAR(100) DEFAULT NULL, ADD nom VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise CHANGE adresse adresse VARCHAR(255) NOT NULL, CHANGE ville ville VARCHAR(100) NOT NULL, CHANGE code_postal code_postal VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE user DROP prenom, DROP nom');
    }
}
