<?php

namespace App\Security;

use App\Entity\Usuario;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Un usuario desactivado desde el panel no puede entrar ni usar un token que ya tuviera
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Usuario && !$user->isActivo()) {
            throw new CustomUserMessageAccountStatusException('Esta cuenta está desactivada');
        }
        if ($user instanceof Usuario && !$user->isVerificado()) {
            throw new CustomUserMessageAccountStatusException('Debes verificar tu correo antes de entrar');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
