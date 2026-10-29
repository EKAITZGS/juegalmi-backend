<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversación de los tickets de soporte (mensajes del cliente y del administrador)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mensaje_ticket (id INT AUTO_INCREMENT NOT NULL, texto LONGTEXT NOT NULL, fecha DATETIME NOT NULL, ticket_id INT NOT NULL, usuario_id INT NOT NULL, INDEX IDX_MENSAJE_TICKET (ticket_id), INDEX IDX_MENSAJE_USUARIO (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mensaje_ticket ADD CONSTRAINT FK_MENSAJE_TICKET FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mensaje_ticket ADD CONSTRAINT FK_MENSAJE_USUARIO FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE mensaje_ticket ADD CONSTRAINT chk_mensaje_texto CHECK (CHAR_LENGTH(texto) BETWEEN 1 AND 1000)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mensaje_ticket');
    }
}
