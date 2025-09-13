<?php

declare(strict_types=1);

namespace Tests\Crayon\PicAuthBundle\EventListener;

use Crayon\PicAuth\AuthManager;
use Crayon\PicAuth\AuthStamp;
use Crayon\PicAuthBundle\Badge\PicCredentials;
use Crayon\PicAuthBundle\EventListener\CheckPicCredentialsListener;
use Crayon\PicAuthBundle\User\PicAuthenticatedUserInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class CheckPicCredentialsListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals([CheckPassportEvent::class => 'checkPassport'], CheckPicCredentialsListener::getSubscribedEvents());
    }

    public function testCheckPassportWithoutCredentials(): void
    {
        $authManager = $this->getMockBuilder(AuthManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $passport = $this->getMockBuilder(Passport::class)
            ->disableOriginalConstructor()
            ->getMock();
        $passport->expects($this->once())
            ->method('hasBadge')
            ->with(PicCredentials::class)
            ->willReturn(false);

        $event = $this->getMockBuilder(CheckPassportEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPassport')
            ->willReturn($passport);

        $listener = new CheckPicCredentialsListener($authManager);
        $listener->checkPassport($event);
        $this->assertTrue(true);
    }

    public function testCheckPassport(): void
    {
        $image = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $image->expects($this->once())
            ->method('getPathname')
            ->willReturn('file paths');

        $credentials = $this->getMockBuilder(PicCredentials::class)
            ->disableOriginalConstructor()
            ->getMock();
        $credentials->expects($this->once())
            ->method('getImage')
            ->willReturn($image);
        $credentials->expects($this->once())
            ->method('markResolved');

        $user = $this->getMockBuilder(PicAuthenticatedUserInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $user->expects($this->once())
            ->method('getToken')
            ->willReturn('token value');
        $user->expects($this->once())
            ->method('getHash')
            ->willReturn('hash value');

        $passport = $this->getMockBuilder(Passport::class)
            ->disableOriginalConstructor()
            ->getMock();
        $passport->expects($this->once())
            ->method('hasBadge')
            ->with(PicCredentials::class)
            ->willReturn(true);
        $passport->expects($this->once())
            ->method('getBadge')
            ->with(PicCredentials::class)
            ->willReturn($credentials);
        $passport->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event = $this->getMockBuilder(CheckPassportEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPassport')
            ->willReturn($passport);

        $authManager = $this->getMockBuilder(AuthManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authManager->expects($this->once())
            ->method('verifyStamp')
            ->with(
                'file paths',
                $this->logicalAnd(
                    $this->isInstanceOf(AuthStamp::class),
                    $this->equalTo(new AuthStamp('token value', 'hash value'))
                )
            )
            ->willReturn(true);

        $listener = new CheckPicCredentialsListener($authManager);
        $listener->checkPassport($event);
        $this->assertTrue(true);
    }

    public function testCheckPassportIsNotVerified(): void
    {
        $this->expectException(BadCredentialsException::class);
        $image = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $image->expects($this->once())
            ->method('getPathname')
            ->willReturn('file paths');

        $credentials = $this->getMockBuilder(PicCredentials::class)
            ->disableOriginalConstructor()
            ->getMock();
        $credentials->expects($this->once())
            ->method('getImage')
            ->willReturn($image);
        $credentials->expects($this->never())
            ->method('markResolved');

        $user = $this->getMockBuilder(PicAuthenticatedUserInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $user->expects($this->once())
            ->method('getToken')
            ->willReturn('token value');
        $user->expects($this->once())
            ->method('getHash')
            ->willReturn('hash value');

        $passport = $this->getMockBuilder(Passport::class)
            ->disableOriginalConstructor()
            ->getMock();
        $passport->expects($this->once())
            ->method('hasBadge')
            ->with(PicCredentials::class)
            ->willReturn(true);
        $passport->expects($this->once())
            ->method('getBadge')
            ->with(PicCredentials::class)
            ->willReturn($credentials);
        $passport->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event = $this->getMockBuilder(CheckPassportEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPassport')
            ->willReturn($passport);

        $authManager = $this->getMockBuilder(AuthManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authManager->expects($this->once())
            ->method('verifyStamp')
            ->with(
                'file paths',
                $this->logicalAnd(
                    $this->isInstanceOf(AuthStamp::class),
                    $this->equalTo(new AuthStamp('token value', 'hash value'))
                )
            )
            ->willReturn(false);

        $listener = new CheckPicCredentialsListener($authManager);
        $listener->checkPassport($event);
    }

    public function testCheckPassportInvaliUser(): void
    {
        $this->expectException(\LogicException::class);

        $user = $this->getMockBuilder(UserInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $passport = $this->getMockBuilder(Passport::class)
            ->disableOriginalConstructor()
            ->getMock();
        $passport->expects($this->once())
            ->method('hasBadge')
            ->with(PicCredentials::class)
            ->willReturn(true);
        $passport->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $event = $this->getMockBuilder(CheckPassportEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPassport')
            ->willReturn($passport);

        $authManager = $this->getMockBuilder(AuthManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = new CheckPicCredentialsListener($authManager);
        $listener->checkPassport($event);
    }
}
