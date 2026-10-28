<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// Doctrine no genera CHECK a partir de las entidades: se añaden a mano.
final class Version20261027115242 extends AbstractMigration
{
    private const CHECKS = [
        ['producto', 'chk_producto_precio', 'precio >= 0'],
        ['producto', 'chk_producto_stock', 'stock >= 0'],
        ['videojuego', 'chk_videojuego_precio_alquiler', 'precio_alquiler_dia >= 0'],
        ['linea_compra', 'chk_linea_cantidad', 'cantidad > 0'],
        ['linea_compra', 'chk_linea_precio', 'precio_unitario >= 0'],
        ['alquiler', 'chk_alquiler_fechas', 'fecha_fin >= fecha_inicio'],
        ['alquiler', 'chk_alquiler_devolucion', 'fecha_devolucion IS NULL OR fecha_devolucion >= fecha_inicio'],
        ['alquiler', 'chk_alquiler_precio', 'precio_dia >= 0'],
        ['ticket', 'chk_ticket_estado', "estado IN ('abierto', 'en_proceso', 'cerrado')"],
        ['ticket', 'chk_ticket_cierre', 'fecha_cierre IS NULL OR fecha_cierre >= fecha_apertura'],
        ['ubicacion', 'chk_ubicacion_latitud', 'latitud BETWEEN -90 AND 90'],
        ['ubicacion', 'chk_ubicacion_longitud', 'longitud BETWEEN -180 AND 180'],
    ];

    public function getDescription(): string
    {
        return 'Reglas de integridad CHECK del modelo E-R';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CHECKS as [$tabla, $nombre, $regla]) {
            $this->addSql("ALTER TABLE $tabla ADD CONSTRAINT $nombre CHECK ($regla)");
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CHECKS as [$tabla, $nombre]) {
            $this->addSql("ALTER TABLE $tabla DROP CONSTRAINT $nombre");
        }
    }
}
