<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Block student accounts that have not been confirmed by an admin yet
        if ($user->getRole() === 'student' && !$user->isConfirmed()) {
            throw new CustomUserMessageAuthenticationException(
                'Your account is pending admin approval. You will be notified once it is confirmed.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Nothing needed here
    }
}
