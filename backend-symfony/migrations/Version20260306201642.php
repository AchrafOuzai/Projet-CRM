<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306201642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ads (id VARCHAR(255) NOT NULL, numero_campagne VARCHAR(50) DEFAULT NULL, date DATE NOT NULL, produit VARCHAR(255) DEFAULT NULL, objet_publicitaire VARCHAR(100) DEFAULT NULL, admin VARCHAR(100) DEFAULT NULL, duree_jours VARCHAR(255) DEFAULT NULL, montant_dollars NUMERIC(10, 2) DEFAULT NULL, montant_dh NUMERIC(10, 2) DEFAULT NULL, resultat VARCHAR(100) DEFAULT NULL, evaluation VARCHAR(20) DEFAULT NULL, total_dollars NUMERIC(10, 2) DEFAULT NULL, total_dh NUMERIC(10, 2) DEFAULT NULL, prospect VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE commande (id VARCHAR(255) NOT NULL, date DATE NOT NULL, designation VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(100) NOT NULL, quantite VARCHAR(255) NOT NULL, prix_vente_total NUMERIC(10, 2) NOT NULL, type_cde VARCHAR(50) DEFAULT NULL, agent VARCHAR(100) DEFAULT NULL, confirmation VARCHAR(50) DEFAULT NULL, livraison VARCHAR(50) DEFAULT NULL, ref VARCHAR(50) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, frais_livraison NUMERIC(10, 2) DEFAULT NULL, whatsap VARCHAR(10) DEFAULT NULL, created_at VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE config_data (id VARCHAR(255) NOT NULL, cle VARCHAR(50) NOT NULL, valeur JSON NOT NULL, UNIQUE INDEX UNIQ_3D53AF9341401D17 (cle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE depense (id VARCHAR(255) NOT NULL, date DATE NOT NULL, designation VARCHAR(255) NOT NULL, montant NUMERIC(10, 2) NOT NULL, categorie VARCHAR(100) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE produit (id VARCHAR(255) NOT NULL, reference VARCHAR(50) NOT NULL, designation VARCHAR(255) NOT NULL, categorie VARCHAR(100) DEFAULT NULL, prix_achat NUMERIC(10, 2) NOT NULL, prix_vente NUMERIC(10, 2) NOT NULL, prix_vente2 NUMERIC(10, 2) DEFAULT NULL, prix_vente3 NUMERIC(10, 2) DEFAULT NULL, prix_vente4 NUMERIC(10, 2) DEFAULT NULL, prix_vente5 NUMERIC(10, 2) DEFAULT NULL, stock VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_29A5EC27AEA34913 (reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE `user` (id VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL, roles VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ads');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE config_data');
        $this->addSql('DROP TABLE depense');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
