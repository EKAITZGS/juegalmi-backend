<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

// Envía un correo de prueba para comprobar la cuenta de Gmail antes de activar la verificación
#[AsCommand(name: 'app:correo-prueba', description: 'Envía un correo de prueba con la cuenta configurada')]
class CorreoPruebaCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAIL_REMITENTE)%')] private string $remitente,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('destino', InputArgument::REQUIRED, 'Dirección que recibirá la prueba');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->mailer->send(
            (new Email())
                ->from(new Address($this->remitente, 'Jokoa'))
                ->to((string) $input->getArgument('destino'))
                ->subject('Prueba de correo de Jokoa')
                ->text('Si lees esto, Jokoa ya puede enviar correos de verificación.')
        );
        $output->writeln('Correo de prueba enviado.');

        return Command::SUCCESS;
    }
}
