<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * PicAuthenticateUserInterface interface.
 */
interface PicAuthenticatedUserInterface extends UserInterface
{
    /**
     * @return string
     */
    public function getToken(): string;

    /**
     * @return string
     */
    public function getHash(): string;
}
