<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617214720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D68B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63868B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_config ADD CONSTRAINT FK_4784B3A79033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE livraison ADD CONSTRAINT FK_A60C9F1F82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE retour ADD CONSTRAINT FK_ED6FD32168B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE retour ADD CONSTRAINT FK_ED6FD32182EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE retour ADD CONSTRAINT FK_ED6FD3219033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX UNIQ_16473BA2FE9AAC6C ON tiers');
        $this->addSql('ALTER TABLE tiers ADD CONSTRAINT FK_16473BA29033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_tiers_referent_tenant ON tiers (referent, tenant_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6499033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_7BA2F5EBA76ED395');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D68B77723');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D9033212A');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63868B77723');
        $this->addSql('ALTER TABLE ecommerce_config DROP FOREIGN KEY FK_4784B3A79033212A');
        $this->addSql('ALTER TABLE livraison DROP FOREIGN KEY FK_A60C9F1F82EA2E54');
        $this->addSql('ALTER TABLE retour DROP FOREIGN KEY FK_ED6FD32168B77723');
        $this->addSql('ALTER TABLE retour DROP FOREIGN KEY FK_ED6FD32182EA2E54');
        $this->addSql('ALTER TABLE retour DROP FOREIGN KEY FK_ED6FD3219033212A');
        $this->addSql('ALTER TABLE tiers DROP FOREIGN KEY FK_16473BA29033212A');
        $this->addSql('DROP INDEX uniq_tiers_referent_tenant ON tiers');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_16473BA2FE9AAC6C ON tiers (referent)');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6499033212A');
    }
}
