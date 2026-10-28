<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027115209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alquiler (id INT AUTO_INCREMENT NOT NULL, fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL, fecha_devolucion DATE DEFAULT NULL, precio_dia NUMERIC(10, 2) NOT NULL, usuario_id INT NOT NULL, videojuego_id INT NOT NULL, INDEX IDX_655BED39DB38439E (usuario_id), INDEX IDX_655BED3982925A85 (videojuego_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE compra (id INT AUTO_INCREMENT NOT NULL, fecha DATETIME NOT NULL, usuario_id INT NOT NULL, INDEX IDX_9EC131FFDB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE departamento (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_40E497EB3A909126 (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE imagen (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(255) NOT NULL, orden SMALLINT NOT NULL, producto_id INT NOT NULL, INDEX IDX_8319D2B37645698E (producto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE linea_compra (id INT AUTO_INCREMENT NOT NULL, cantidad INT NOT NULL, precio_unitario NUMERIC(10, 2) NOT NULL, compra_id INT NOT NULL, producto_id INT NOT NULL, INDEX IDX_42111654F2E704D7 (compra_id), INDEX IDX_421116547645698E (producto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE movil (marca VARCHAR(50) NOT NULL, almacenamiento_gb SMALLINT NOT NULL, ram_gb SMALLINT NOT NULL, producto_id INT NOT NULL, PRIMARY KEY (producto_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE plataforma (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_A0FE8A1E3A909126 (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE producto (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(150) NOT NULL, descripcion LONGTEXT NOT NULL, precio NUMERIC(10, 2) NOT NULL, stock INT NOT NULL, fecha_alta DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, asunto VARCHAR(150) NOT NULL, descripcion LONGTEXT NOT NULL, dispositivo VARCHAR(100) NOT NULL, estado VARCHAR(20) NOT NULL, fecha_apertura DATETIME NOT NULL, fecha_cierre DATETIME DEFAULT NULL, usuario_id INT NOT NULL, departamento_id INT NOT NULL, INDEX IDX_97A0ADA3DB38439E (usuario_id), INDEX IDX_97A0ADA35A91C08D (departamento_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ubicacion (id INT AUTO_INCREMENT NOT NULL, latitud NUMERIC(9, 6) NOT NULL, longitud NUMERIC(9, 6) NOT NULL, fecha DATETIME NOT NULL, accion VARCHAR(100) NOT NULL, usuario_id INT NOT NULL, INDEX IDX_DC158CB8DB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, nombre VARCHAR(100) NOT NULL, apellidos VARCHAR(150) NOT NULL, telefono VARCHAR(20) DEFAULT NULL, roles JSON NOT NULL, fecha_alta DATETIME NOT NULL, UNIQUE INDEX UNIQ_2265B05DE7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE videojuego (genero VARCHAR(50) NOT NULL, pegi SMALLINT NOT NULL, precio_alquiler_dia NUMERIC(10, 2) NOT NULL, producto_id INT NOT NULL, plataforma_id INT NOT NULL, INDEX IDX_AA5E6DFAEB90E430 (plataforma_id), PRIMARY KEY (producto_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE alquiler ADD CONSTRAINT FK_655BED39DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE alquiler ADD CONSTRAINT FK_655BED3982925A85 FOREIGN KEY (videojuego_id) REFERENCES videojuego (producto_id)');
        $this->addSql('ALTER TABLE compra ADD CONSTRAINT FK_9EC131FFDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE imagen ADD CONSTRAINT FK_8319D2B37645698E FOREIGN KEY (producto_id) REFERENCES producto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE linea_compra ADD CONSTRAINT FK_42111654F2E704D7 FOREIGN KEY (compra_id) REFERENCES compra (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE linea_compra ADD CONSTRAINT FK_421116547645698E FOREIGN KEY (producto_id) REFERENCES producto (id)');
        $this->addSql('ALTER TABLE movil ADD CONSTRAINT FK_64824ACB7645698E FOREIGN KEY (producto_id) REFERENCES producto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA35A91C08D FOREIGN KEY (departamento_id) REFERENCES departamento (id)');
        $this->addSql('ALTER TABLE ubicacion ADD CONSTRAINT FK_DC158CB8DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE videojuego ADD CONSTRAINT FK_AA5E6DFA7645698E FOREIGN KEY (producto_id) REFERENCES producto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE videojuego ADD CONSTRAINT FK_AA5E6DFAEB90E430 FOREIGN KEY (plataforma_id) REFERENCES plataforma (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alquiler DROP FOREIGN KEY FK_655BED39DB38439E');
        $this->addSql('ALTER TABLE alquiler DROP FOREIGN KEY FK_655BED3982925A85');
        $this->addSql('ALTER TABLE compra DROP FOREIGN KEY FK_9EC131FFDB38439E');
        $this->addSql('ALTER TABLE imagen DROP FOREIGN KEY FK_8319D2B37645698E');
        $this->addSql('ALTER TABLE linea_compra DROP FOREIGN KEY FK_42111654F2E704D7');
        $this->addSql('ALTER TABLE linea_compra DROP FOREIGN KEY FK_421116547645698E');
        $this->addSql('ALTER TABLE movil DROP FOREIGN KEY FK_64824ACB7645698E');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3DB38439E');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA35A91C08D');
        $this->addSql('ALTER TABLE ubicacion DROP FOREIGN KEY FK_DC158CB8DB38439E');
        $this->addSql('ALTER TABLE videojuego DROP FOREIGN KEY FK_AA5E6DFA7645698E');
        $this->addSql('ALTER TABLE videojuego DROP FOREIGN KEY FK_AA5E6DFAEB90E430');
        $this->addSql('DROP TABLE alquiler');
        $this->addSql('DROP TABLE compra');
        $this->addSql('DROP TABLE departamento');
        $this->addSql('DROP TABLE imagen');
        $this->addSql('DROP TABLE linea_compra');
        $this->addSql('DROP TABLE movil');
        $this->addSql('DROP TABLE plataforma');
        $this->addSql('DROP TABLE producto');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ubicacion');
        $this->addSql('DROP TABLE usuario');
        $this->addSql('DROP TABLE videojuego');
    }
}
