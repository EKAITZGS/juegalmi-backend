<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recoger en tienda: la compra guarda la tienda elegida';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE compra ADD tienda_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE compra ADD CONSTRAINT FK_COMPRA_TIENDA FOREIGN KEY (tienda_id) REFERENCES tienda (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_COMPRA_TIENDA ON compra (tienda_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE compra DROP FOREIGN KEY FK_COMPRA_TIENDA');
        $this->addSql('DROP INDEX IDX_COMPRA_TIENDA ON compra');
        $this->addSql('ALTER TABLE compra DROP tienda_id');
    }
}
