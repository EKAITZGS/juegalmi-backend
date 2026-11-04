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
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">Activa tu cuenta de Jokoa con un solo clic. El enlace caduca en 24 horas.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eaf2fa;padding:28px 12px;font-family:Roboto,Helvetica,Arial,sans-serif;color:#1b2430">
 <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #d5deea;border-radius:20px;overflow:hidden;border-collapse:separate">
   <tr><td style="background:#172131;padding:22px 28px">
    <table role="presentation" cellpadding="0" cellspacing="0"><tr>
     <td style="padding-right:12px">
      <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate"><tr>
       <td width="14" height="14" style="width:14px;height:14px;background:#4a90d9;border-radius:3px;font-size:0;line-height:0">&nbsp;</td><td width="4" style="font-size:0">&nbsp;</td>
       <td width="14" height="14" style="width:14px;height:14px;background:#4a90d9;border-radius:3px;font-size:0;line-height:0">&nbsp;</td></tr>
       <tr><td colspan="3" height="4" style="font-size:0;line-height:0">&nbsp;</td></tr>
       <tr><td width="14" height="14" style="width:14px;height:14px;background:#4a90d9;border-radius:3px;font-size:0;line-height:0">&nbsp;</td><td width="4" style="font-size:0">&nbsp;</td>
       <td width="14" height="14" style="width:14px;height:14px;background:#d85a30;border-radius:3px;font-size:0;line-height:0">&nbsp;</td></tr>
      </table>
     </td>
     <td style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:.3px">Jokoa</td>
    </tr></table>
   </td></tr>
   <tr><td style="background:#185fa5;padding:26px 28px 24px;color:#ffffff">
    <div style="font-size:13px;letter-spacing:1.5px;text-transform:uppercase;opacity:.85">Bienvenido a la tienda</div>
    <div style="margin-top:6px;font-size:26px;line-height:1.25;font-weight:700">Hola, $nombre</div>
   </td></tr>
   <tr><td style="padding:28px">
    <p style="margin:0 0 20px;font-size:16px;line-height:1.6">Gracias por crear tu cuenta en <b>Jokoa</b>. Solo falta un paso: confirma que este correo es tuyo para empezar a comprar, alquilar juegos y pedir soporte técnico.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px"><tr><td style="background:#b8461f;border-radius:14px">
     <a href="$enlace" style="display:inline-block;padding:15px 30px;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none">Verificar mi cuenta</a>
    </td></tr></table>
    <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#5b6b82">Si el botón no funciona, copia este enlace en tu navegador:</p>
    <p style="margin:0 0 20px;font-size:12px;line-height:1.5;word-break:break-all"><a href="$enlace" style="color:#185fa5">$enlace</a></p>
    <p style="margin:0;font-size:13px;line-height:1.5;color:#5b6b82">El enlace caduca en 24 horas. Si no has creado una cuenta en Jokoa, ignora este mensaje: no se activará nada.</p>
   </td></tr>
   <tr><td style="background:#f4f7fb;border-top:1px solid #d5deea;padding:16px 28px;font-size:12px;color:#5b6b82;line-height:1.5">
    Jokoa · Videojuegos, móviles y gaming en Bilbao, Barakaldo, Getxo y Leioa.
   </td></tr>
  </table>
 </td></tr>
</table>
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
