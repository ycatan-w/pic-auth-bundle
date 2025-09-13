<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle\Security;

use Crayon\PicAuthBundle\Badge\PicCredentials;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * FormLoginPicAuthenticator class.
 */
class FormLoginPicAuthenticator extends AbstractLoginFormAuthenticator
{
    /**
     * @var array
     */
    private readonly array $options;

    /**
     * @param  HttpUtils $httpUtils
     * @param  array     $options
     */
    public function __construct(
        private HttpUtils $httpUtils,
        array $options,
    ) {
        $this->options = array_merge([
            // form
            'username_parameter' => '_username',
            'image_parameter'    => '_image',
            'enable_csrf'        => true,
            'csrf_parameter'     => '_csrf_token',
            'csrf_token_id'      => 'authenticate',
            // redirect on success
            'always_use_default_target_path' => false,
            'target_path_parameter'          => '_target_path',
            'default_target_path'            => '/',
            'use_referer'                    => false,
            // login urls
            'check_path' => '/login',
            'login_path' => '/login',
        ], $options);
    }

    /**
     * @param  Request $request
     *
     * @return string
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->httpUtils->generateUri($request, $this->options['login_path']);
    }

    /**
     * @param  Request $request
     *
     * @return bool
     */
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $this->httpUtils->checkRequestPath($request, $this->options['check_path'])
            && 'form' === $request->getContentTypeFormat();
    }

    /**
     * @param  Request $request
     *
     * @return Passport
     */
    public function authenticate(Request $request): Passport
    {
        $credentials = $this->getCredentials($request);
        $userBadge   = new UserBadge($credentials['username']);
        $passport    = new Passport($userBadge, new PicCredentials($credentials['image']), [new RememberMeBadge()]);

        if ($this->options['enable_csrf']) {
            $passport->addBadge(new CsrfTokenBadge($this->options['csrf_token_id'], $credentials['csrf_token']));
        }

        return $passport;
    }

    /**
     * @param  Request        $request
     * @param  TokenInterface $token
     * @param  string         $firewallName
     *
     * @return Response
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->httpUtils->createRedirectResponse($request, $this->determineTargetUrl($request));
    }

    /**
     * @param  Request $request
     *
     * @return array
     */
    private function getCredentials(Request $request): array
    {
        $credentials = [
            'csrf_token' => ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']),
            'username'   => ParameterBagUtils::getParameterBagValue($request->request, $this->options['username_parameter']),
            'image'      => ParameterBagUtils::getParameterBagValue($request->files, $this->options['image_parameter']),
        ];

        if (!\is_string($credentials['username']) && !$credentials['username'] instanceof \Stringable) {
            throw new BadRequestHttpException(\sprintf('The key "%s" must be a string, "%s" given.', $this->options['username_parameter'], \gettype($credentials['username'])));
        }

        $credentials['username'] = trim($credentials['username']);

        if ('' === $credentials['username']) {
            throw new BadCredentialsException(\sprintf('The key "%s" must be a non-empty string.', $this->options['username_parameter']));
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $credentials['username']);

        if (!($credentials['image'] instanceof File)) {
            throw new BadRequestHttpException(\sprintf('The key "%s" must be an image, "%s" given.', $this->options['image_parameter'], \gettype($credentials['image'])));
        }

        if (!\is_string($credentials['csrf_token'] ?? '') && !$credentials['csrf_token'] instanceof \Stringable) {
            throw new BadRequestHttpException(\sprintf('The key "%s" must be a string, "%s" given.', $this->options['csrf_parameter'], \gettype($credentials['csrf_token'])));
        }

        return $credentials;
    }

    /**
     * @param  Request $request
     *
     * @return string
     */
    private function determineTargetUrl(Request $request): string
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        $targetUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['target_path_parameter']);

        if (\is_string($targetUrl) && (str_starts_with($targetUrl, '/') || str_starts_with($targetUrl, 'http'))) {
            return $targetUrl;
        }

        if ($this->options['use_referer'] && $targetUrl = $request->headers->get('Referer')) {
            if (false !== $pos = strpos($targetUrl, '?')) {
                $targetUrl = substr($targetUrl, 0, $pos);
            }
            if ($targetUrl && $targetUrl !== $this->getLoginUrl($request)) {
                return $targetUrl;
            }
        }

        return $this->options['default_target_path'];
    }
}
