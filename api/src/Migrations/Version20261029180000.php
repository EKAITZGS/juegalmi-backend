<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mensajes leídos de cada ticket por usuario (notificaciones de soporte)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lectura_ticket (id INT AUTO_INCREMENT NOT NULL, ultimo_leido INT DEFAULT 0 NOT NULL, ticket_id INT NOT NULL, usuario_id INT NOT NULL, INDEX IDX_LECTURA_TICKET (ticket_id), INDEX IDX_LECTURA_USUARIO (usuario_id), UNIQUE INDEX uniq_lectura (ticket_id, usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lectura_ticket ADD CONSTRAINT FK_LECTURA_TICKET FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lectura_ticket ADD CONSTRAINT FK_LECTURA_USUARIO FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lectura_ticket');
    }
}
