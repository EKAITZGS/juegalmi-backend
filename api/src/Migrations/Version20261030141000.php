<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'articulo usa la misma colación que producto (las búsquedas del asesor mezclan ambas tablas)';
    }

    public function up(Schema $schema): void
    {
        $colacion = $this->connection->fetchOne("SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'producto'");
        $this->skipIf(!is_string($colacion) || !preg_match('/^[a-z0-9_]+$/', $colacion), 'No se pudo leer la colación de producto');
        $this->addSql("ALTER TABLE articulo CONVERT TO CHARACTER SET utf8mb4 COLLATE $colacion");
    }

    public function down(Schema $schema): void
    {
    }
}
