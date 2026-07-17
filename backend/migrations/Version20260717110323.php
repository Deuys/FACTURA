<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717110323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les conditions de délai de paiement par défaut sur l’entreprise et personnalisables par client.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD type_delai_paiement VARCHAR(30) DEFAULT NULL, ADD delai_paiement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entreprise ADD type_delai_paiement VARCHAR(30) DEFAULT \'Jours nets\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP type_delai_paiement, DROP delai_paiement');
        $this->addSql('ALTER TABLE entreprise DROP type_delai_paiement');
    }
}
