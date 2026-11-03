<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Garantía ampliada opcional en las líneas de compra (meses y precio por unidad)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE linea_compra ADD garantia_meses SMALLINT DEFAULT NULL, ADD precio_garantia NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE linea_compra DROP garantia_meses, DROP precio_garantia');
    }
}
