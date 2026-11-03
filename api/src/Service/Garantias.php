<?php

namespace App\Service;

// Garantía ampliada de los móviles: el precio lo calcula siempre el servidor, nunca el cliente
final class Garantias
{
    /** meses => porcentaje sobre el precio del producto */
    public const PLANES = [12 => 0.08, 24 => 0.14];

    /** @return list<array{meses:int, precio:float}> */
    public static function planes(string $precioProducto): array
    {
        $lista = [];
        foreach (array_keys(self::PLANES) as $meses) {
            $lista[] = ['meses' => $meses, 'precio' => (float) self::precio($precioProducto, $meses)];
        }

        return $lista;
    }

    public static function precio(string $precioProducto, int $meses): ?string
    {
        return isset(self::PLANES[$meses]) ? number_format(round((float) $precioProducto * self::PLANES[$meses], 2), 2, '.', '') : null;
    }
}
