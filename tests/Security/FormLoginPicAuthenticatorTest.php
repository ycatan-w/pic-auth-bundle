<?php

declare(strict_types=1);

namespace Tests\Crayon\PicAuthBundle\Security;

use Crayon\PicAuthBundle\Badge\PicCredentials;
use Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\HttpUtils;

class FormLoginPicAuthenticatorTest extends TestCase
{
    public function testHandleWhenUsernameEmpty(): void
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The key "_username" must be a non-empty string.');

        $request = Request::create('/login', 'POST', ['_username' => ''], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        $authenticator->authenticate($request);
    }

    public function testHandleNonStringUsernameWithArray(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');

        $request = Request::create('/login', 'POST', ['_username' => []]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        $authenticator->authenticate($request);
    }

    public function testHandleWhenImageEmpty(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_image" must be an image, "array" given.');

        $request = Request::create('/login', 'POST', ['_username' => 'foo'], files: ['_image' => []]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        $authenticator->authenticate($request);
    }

    public function testHandleNonStringCsrfTokenWithArray(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "array" given.');

        $request = Request::create('/login', 'POST', ['_username' => 'foo', '_csrf_token' => []], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        $authenticator->authenticate($request);
    }

    public function testWithoutCsrfProtection(): void
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo'], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator(['enable_csrf' => false]);
        $passport      = $authenticator->authenticate($request);
        $this->assertFalse($passport->hasBadge(CsrfTokenBadge::class));
    }

    public function testWithoutDefaultBadges(): void
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo'], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        $passport      = $authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(UserBadge::class));
        $this->assertTrue($passport->hasBadge(PicCredentials::class));
        $this->assertTrue($passport->hasBadge(RememberMeBadge::class));
        $this->assertTrue($passport->hasBadge(CsrfTokenBadge::class));
    }

    public function testSupports(): void
    {
        // POST + check_url + form data
        $request = Request::create('/login', 'POST', ['_username' => 'foo'], files: ['_image' => $this->createFile()], server: ['CONTENT_TYPE' => 'multipart/form-data']);
        $request->setSession($this->createSession());
        $authenticator = $this->setUpAuthenticator();
        $this->assertTrue($authenticator->supports($request));

        // POST + form data
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo'], files: ['_image' => $this->createFile()], server: ['CONTENT_TYPE' => 'multipart/form-data']);
        $request->setSession($this->createSession());
        $this->assertFalse($authenticator->supports($request));

        // check_url + form data
        $request = Request::create('/login', 'GET', ['_username' => 'foo'], files: ['_image' => $this->createFile()], server: ['CONTENT_TYPE' => 'multipart/form-data']);
        $request->setSession($this->createSession());
        $this->assertFalse($authenticator->supports($request));

        // POST + check_url
        $request = Request::create('/login', 'GET', ['_username' => 'foo'], files: ['_image' => $this->createFile()], server: ['CONTENT_TYPE' => 'application/json']);
        $request->setSession($this->createSession());
        $this->assertFalse($authenticator->supports($request));
    }

    private function setUpAuthenticator(array $options = []): FormLoginPicAuthenticator
    {
        return new FormLoginPicAuthenticator(new HttpUtils(), $options);
    }

    public function testOnAuthenticationSuccessDefault(): void
    {
        $request = Request::create('/login', 'GET', ['_username' => 'foo'], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        /** @var RedirectResponse $response */
        $response = $authenticator->onAuthenticationSuccess($request, $this->createToken(), 'firewall_name');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://localhost/', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessAlwayDefault(): void
    {
        $request = Request::create('/login', 'GET', ['_username' => 'foo'], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator(['always_use_default_target_path' => true]);
        /** @var RedirectResponse $response */
        $response = $authenticator->onAuthenticationSuccess($request, $this->createToken(), 'firewall_name');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://localhost/', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithTargetParameter(): void
    {
        $request = Request::create('/login', 'GET', ['_username' => 'foo', '_target_path' => '/redirect-to-this-url'], files: ['_image' => $this->createFile()]);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator();
        /** @var RedirectResponse $response */
        $response = $authenticator->onAuthenticationSuccess($request, $this->createToken(), 'firewall_name');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://localhost/redirect-to-this-url', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithReferer(): void
    {
        $request = Request::create('/login', 'GET', ['_username' => 'foo'], files: ['_image' => $this->createFile()], server: ['HTTP_REFERER' => '/previous-url?param=value']);
        $request->setSession($this->createSession());

        $authenticator = $this->setUpAuthenticator(['use_referer' => true]);
        /** @var RedirectResponse $response */
        $response = $authenticator->onAuthenticationSuccess($request, $this->createToken(), 'firewall_name');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://localhost/previous-url', $response->getTargetUrl());
    }

    private function createFile()
    {
        return $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createToken()
    {
        return $this->getMockBuilder(TokenInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createSession()
    {
        return $this->getMockBuilder(SessionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
