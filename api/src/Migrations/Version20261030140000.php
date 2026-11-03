<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla articulo: ordenadores, tablets y zona gaming (1:1 con producto)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE articulo (producto_id INT NOT NULL, categoria VARCHAR(20) NOT NULL, marca VARCHAR(50) NOT NULL, montaje NUMERIC(8, 2) DEFAULT '0.00' NOT NULL, INDEX idx_articulo_categoria (categoria), PRIMARY KEY (producto_id)) ENGINE = InnoDB");
        $this->addSql('ALTER TABLE articulo ADD CONSTRAINT FK_articulo_producto FOREIGN KEY (producto_id) REFERENCES producto (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE articulo');
    }
}
