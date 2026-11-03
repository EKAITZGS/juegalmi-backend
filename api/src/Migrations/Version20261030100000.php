<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Características extra del producto (JSON) para el asistente de compra';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE producto ADD caracteristicas JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE producto DROP caracteristicas');
    }
}
