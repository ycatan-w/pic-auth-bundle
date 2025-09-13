<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle\EventListener;

use Crayon\PicAuth\AuthManager;
use Crayon\PicAuth\AuthStamp;
use Crayon\PicAuthBundle\Badge\PicCredentials;
use Crayon\PicAuthBundle\User\PicAuthenticatedUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * CheckPicCredentialsListener class.
 */
class CheckPicCredentialsListener implements EventSubscriberInterface
{
    /**
     * @param  AuthManager $authManager
     */
    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    /**
     * @param  CheckPassportEvent $event
     *
     * @return void
     */
    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(PicCredentials::class)) {
            return;
        }
        $user = $passport->getUser();
        if (!$user instanceof PicAuthenticatedUserInterface) {
            throw new \LogicException(\sprintf('Class "%s" must implement "%s" for using image-based authentication.', get_debug_type($user), PicAuthenticatedUserInterface::class));
        }
        $badge      = $passport->getBadge(PicCredentials::class);
        $img        = $badge->getImage();
        $isVerified = $this->authManager->verifyStamp($img->getPathname(), new AuthStamp($user->getToken(), $user->getHash()));
        if (!$isVerified) {
            throw new BadCredentialsException('The presented image is invalid.');
        }
        $badge->markResolved();
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => 'checkPassport'];
    }
}
