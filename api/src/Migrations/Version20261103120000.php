<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favoritos: productos marcados con el corazón por cada usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favorito (id INT AUTO_INCREMENT NOT NULL, usuario_id INT NOT NULL, producto_id INT NOT NULL, INDEX IDX_FAVORITO_USUARIO (usuario_id), INDEX IDX_FAVORITO_PRODUCTO (producto_id), UNIQUE INDEX uniq_favorito (usuario_id, producto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE favorito ADD CONSTRAINT FK_FAVORITO_USUARIO FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorito ADD CONSTRAINT FK_FAVORITO_PRODUCTO FOREIGN KEY (producto_id) REFERENCES producto (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE favorito');
    }
}
