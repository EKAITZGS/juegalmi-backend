<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verificación del correo de los usuarios (las cuentas existentes quedan verificadas)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD verificado TINYINT(1) DEFAULT 1 NOT NULL, ADD token_verificacion VARCHAR(64) DEFAULT NULL, ADD verificacion_caduca DATETIME DEFAULT NULL, ADD verificacion_enviada DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP verificado, DROP token_verificacion, DROP verificacion_caduca, DROP verificacion_enviada');
    }
}
