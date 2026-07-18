<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718111409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’origine et l’identifiant externe des paiements et convertit les anciens statuts.';
    }

    public function up(Schema $schema): void
    {
        // Ajout des nouvelles colonnes.
        // origine est d'abord nullable pour gérer les paiements déjà existants.
        $this->addSql(
            'ALTER TABLE paiement
             ADD origine VARCHAR(20) DEFAULT NULL,
             ADD external_payment_id VARCHAR(255) DEFAULT NULL'
        );

        // Les paiements déjà présents sont considérés comme manuels.
        $this->addSql(
            "UPDATE paiement
             SET origine = 'manuel'
             WHERE origine IS NULL"
        );

        // Conversion des anciennes valeurs de statut.
        $this->addSql(
            "UPDATE paiement
             SET statut = 'Confirmé'
             WHERE statut = 'Validé'"
        );

        $this->addSql(
            "UPDATE paiement
             SET statut = 'Échoué'
             WHERE statut = 'Rejeté'"
        );

        // L'origine devient obligatoire après la mise à jour des anciennes lignes.
        $this->addSql(
            'ALTER TABLE paiement
             MODIFY origine VARCHAR(20) NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        // Restauration des anciennes valeurs en cas de retour arrière.
        $this->addSql(
            "UPDATE paiement
             SET statut = 'Validé'
             WHERE statut = 'Confirmé'"
        );

        $this->addSql(
            "UPDATE paiement
             SET statut = 'Rejeté'
             WHERE statut = 'Échoué'"
        );

        $this->addSql(
            'ALTER TABLE paiement
             DROP origine,
             DROP external_payment_id'
        );
    }
}
