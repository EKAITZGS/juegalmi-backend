<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tiendas físicas de Jokoa (mapa de las apps)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tienda (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(80) NOT NULL, direccion VARCHAR(150) NOT NULL, telefono VARCHAR(20) NOT NULL, horario VARCHAR(100) NOT NULL, latitud NUMERIC(9, 6) NOT NULL, longitud NUMERIC(9, 6) NOT NULL, UNIQUE INDEX UNIQ_TIENDA_NOMBRE (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tienda ADD CONSTRAINT chk_tienda_latitud CHECK (latitud BETWEEN -90 AND 90)');
        $this->addSql('ALTER TABLE tienda ADD CONSTRAINT chk_tienda_longitud CHECK (longitud BETWEEN -180 AND 180)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tienda');
    }
}
