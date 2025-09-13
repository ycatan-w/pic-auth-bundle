<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle\Badge;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * PicCredentials class.
 */
class PicCredentials implements CredentialsInterface
{
    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @param  File $image
     */
    public function __construct(
        private readonly File $image,
    ) {
    }

    /**
     * @return File
     */
    public function getImage(): File
    {
        return $this->image;
    }

    /**
     * @return void
     */
    public function markResolved(): void
    {
        $this->resolved = true;
    }

    /**
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
