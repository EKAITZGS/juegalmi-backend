<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tickets de reparación: tipo, presupuesto, aceptación del cliente y estados de la reparación';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ticket ADD tipo VARCHAR(20) NOT NULL DEFAULT 'soporte', ADD presupuesto NUMERIC(10, 2) DEFAULT NULL, ADD presupuesto_aceptado TINYINT(1) DEFAULT NULL");
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT chk_ticket_estado');
        $this->addSql("ALTER TABLE ticket ADD CONSTRAINT chk_ticket_estado CHECK (estado IN ('abierto', 'en_proceso', 'cerrado', 'recibido', 'presupuestado', 'en_reparacion', 'listo'))");
        $this->addSql("ALTER TABLE ticket ADD CONSTRAINT chk_ticket_tipo CHECK (tipo IN ('soporte', 'reparacion'))");
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT chk_ticket_presupuesto CHECK (presupuesto IS NULL OR presupuesto >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT chk_ticket_presupuesto');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT chk_ticket_tipo');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT chk_ticket_estado');
        $this->addSql("UPDATE ticket SET estado = 'cerrado' WHERE estado NOT IN ('abierto', 'en_proceso', 'cerrado')");
        $this->addSql("ALTER TABLE ticket ADD CONSTRAINT chk_ticket_estado CHECK (estado IN ('abierto', 'en_proceso', 'cerrado'))");
        $this->addSql('ALTER TABLE ticket DROP tipo, DROP presupuesto, DROP presupuesto_aceptado');
    }
}
