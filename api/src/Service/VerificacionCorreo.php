<?php

namespace App\Service;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

// Verificación de la cuenta por correo: crea un enlace de un solo uso (caduca a las 24 h) y lo envía
class VerificacionCorreo
{
    public const CADUCA = '+24 hours';
    public const ESPERA_REENVIO = 60; // segundos

    public function __construct(
        private MailerInterface $mailer,
        private UsuarioRepository $usuarios,
        #[Autowire('%env(bool:VERIFICAR_EMAIL)%')] private bool $activa,
        #[Autowire('%env(APP_URL)%')] private string $urlBase,
        #[Autowire('%env(MAIL_REMITENTE)%')] private string $remitente,
    ) {
    }

    // Si no hay cuenta de correo configurada, las altas siguen funcionando sin verificar
    public function activa(): bool
    {
        return $this->activa;
    }

    public function puedeReenviar(Usuario $u): bool
    {
        $ultimo = $u->getVerificacionEnviada();

        return $ultimo === null || (time() - $ultimo->getTimestamp()) >= self::ESPERA_REENVIO;
    }

    public function enviar(Usuario $u): void
    {
        $token = bin2hex(random_bytes(32));
        // En la base de datos solo se guarda el resumen del token: el enlace del correo es lo único que lo contiene
        $u->setTokenVerificacion(hash('sha256', $token));
        $u->setVerificacionCaduca(new \DateTime(self::CADUCA));
        $u->setVerificacionEnviada(new \DateTime());
        $this->usuarios->add($u);

        $enlace = rtrim($this->urlBase, '/').'/verificar?email='.urlencode($u->getEmail()).'&token='.$token;
        $nombre = htmlspecialchars((string) $u->getNombre(), ENT_QUOTES);

        $correo = (new Email())
            ->from(new Address($this->remitente, 'Jokoa'))
            ->to($u->getEmail())
            ->subject('Verifica tu cuenta de Jokoa')
            ->text("Hola {$u->getNombre()},\n\nPara activar tu cuenta de Jokoa abre este enlace (caduca en 24 horas):\n$enlace\n\nSi no has creado la cuenta, ignora este mensaje.\n\nJokoa")
            ->html(<<<HTML
<div style="margin:0;padding:24px;background:#f4f7fb;font-family:Roboto,Arial,sans-serif;color:#1b2430">
  <div style="max-width:480px;margin:0 auto;background:#fff;border:1px solid #d5deea;border-radius:16px;overflow:hidden">
    <div style="padding:20px 28px;background:#185fa5;color:#fff;font-size:22px;font-weight:700">Jokoa</div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:22px">Hola, $nombre</h1>
      <p style="margin:0 0 24px;line-height:1.5">Solo falta un paso para activar tu cuenta. Pulsa el botón para verificar tu correo.</p>
      <p style="margin:0 0 24px"><a href="$enlace" style="display:inline-block;padding:14px 28px;background:#b8461f;color:#fff;border-radius:14px;font-weight:700;text-decoration:none">Verificar mi cuenta</a></p>
      <p style="margin:0;font-size:13px;color:#5b6b82;line-height:1.5">El enlace caduca en 24 horas. Si no has creado una cuenta en Jokoa, ignora este mensaje.</p>
    </div>
  </div>
</div>
HTML);
        $this->mailer->send($correo);
    }

    // Devuelve 'ok', 'caducado' o 'invalido'
    public function confirmar(string $email, string $token): string
    {
        $u = $this->usuarios->findOneBy(['email' => $email]);
        if ($u === null) {
            return 'invalido';
        }
        // Cuenta ya verificada o sin enlace pendiente: la misma respuesta que un correo inexistente, para no revelar quién está registrado
        if ($u->isVerificado() || $u->getTokenVerificacion() === null || !hash_equals($u->getTokenVerificacion(), hash('sha256', $token))) {
            return 'invalido';
        }
        if ($u->getVerificacionCaduca() === null || $u->getVerificacionCaduca() < new \DateTime()) {
            return 'caducado';
        }

        $u->setVerificado(true);
        $u->setTokenVerificacion(null);
        $u->setVerificacionCaduca(null);
        $this->usuarios->add($u);

        return 'ok';
    }
}
