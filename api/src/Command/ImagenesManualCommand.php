<?php

namespace App\Command;

use App\Entity\Producto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

// Asigna fotos puestas a mano en api/public/img/manual/: el archivo se llama como el producto
// ("galaxy-s25-fe.jpg"; el nombre es el mismo que el de su tarjeta). Lista los productos que aún no tienen foto real.
#[AsCommand(name: 'app:imagenes-manual', description: 'Asigna las fotos de api/public/img/manual y lista los productos sin foto real')]
class ImagenesManualCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $carpeta = dirname(__DIR__, 2).'/public/img/manual/';
        $archivos = [];
        foreach (glob($carpeta.'*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $ruta) {
            $archivos[pathinfo($ruta, PATHINFO_FILENAME)] = basename($ruta);
        }

        $slugger = new AsciiSlugger();
        $asignadas = 0;
        $pendientes = [];
        foreach ($this->em->getRepository(Producto::class)->findAll() as $producto) {
            $slug = strtolower($slugger->slug($producto->getNombre()));
            $imagen = $producto->getImagenes()->first();
            if (isset($archivos[$slug]) && $imagen) {
                $imagen->setUrl('https://jokoa.duckdns.org/img/manual/'.$archivos[$slug]);
                unset($archivos[$slug]);
                ++$asignadas;
            } elseif ($imagen && str_contains($imagen->getUrl(), '/img/tarjetas/')) {
                $pendientes[] = $slug;
            }
        }
        $this->em->flush();

        sort($pendientes);
        $output->writeln("Fotos asignadas: $asignadas. Productos aún con tarjeta: ".count($pendientes).'.');
        foreach ($archivos as $slug => $archivo) {
            $output->writeln("Sin producto con ese nombre: $archivo");
        }
        if ($input->getOption('listar')) {
            $output->writeln($pendientes);
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('listar', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Muestra los nombres de archivo que faltan');
    }
}
