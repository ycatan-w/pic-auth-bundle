<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle;

use Crayon\PicAuth\AuthManager;
use Crayon\PicAuth\Config\AuthConfig;
use Crayon\PicAuth\Hasher\Sha256Hasher;
use Crayon\PicAuth\Stego\Lsb\LsbSteganography;
use Crayon\PicAuth\Token\RandomBytesTokenGenerator;
use Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * CrayonPicAuthBundle class.
 */
class CrayonPicAuthBundle extends AbstractBundle
{
    /**
     * @param  DefinitionConfigurator $definition
     *
     * @return void
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('pic_auth')
                    ->children()
                        ->integerNode('token_length')->defaultValue(32)->end()
                        ->stringNode('hasher')->defaultValue('sha256')->end()
                        ->stringNode('stegano')->defaultValue('lsb')->end()
                        ->scalarNode('pepper')->defaultNull()->end()
                    ->end()
                ->end() // end pic_auth
                ->arrayNode('authenticator')
                    ->children()
                        ->stringNode('username_parameter')->defaultValue('_username')->end()
                        ->stringNode('image_parameter')->defaultValue('_image')->end()
                        ->booleanNode('enable_csrf')->defaultTrue()->end()
                        ->stringNode('csrf_parameter')->defaultValue('_csrf_token')->end()
                        ->stringNode('csrf_token_id')->defaultValue('authenticate')->end()
                        ->booleanNode('always_use_default_target_path')->defaultFalse()->end()
                        ->stringNode('target_path_parameter')->defaultValue('_target_path')->end()
                        ->stringNode('default_target_path')->defaultValue('/')->end()
                        ->booleanNode('use_referer')->defaultFalse()->end()
                        ->stringNode('check_path')->defaultValue('/login')->end()
                        ->stringNode('login_path')->defaultValue('/login')->end()
                    ->end()
                ->end() // end authenticator
            ->end()
        ;
    }

    /**
     * @param  array                 $config
     * @param  ContainerConfigurator $container
     * @param  ContainerBuilder      $builder
     *
     * @return void
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @todo: update this part to use a Factory to create: stegano, hasher, randomizer token */
        $stegano = match ($config['pic_auth']['stegano'] ?? 'lsb') {
            'lsb'   => new ReferenceConfigurator(LsbSteganography::class),
            default => throw new \Exception('Invalid Steganography method'),
        };
        $hasher = match ($config['pic_auth']['hasher'] ?? 'sha256') {
            'sha256' => new ReferenceConfigurator(Sha256Hasher::class),
            default  => throw new \Exception('Invalid Hash method'),
        };

        $container->import(\dirname(__DIR__, 1) . '/config/services.yaml');
        $container->services()
            // authenticator
            ->set(FormLoginPicAuthenticator::class)
                ->arg(0, new ReferenceConfigurator(HttpUtils::class))
                ->arg(1, $config['authenticator'] ?? [])
            // --- PIC-AUTH Lib ---
            // pic-auth options
            ->set(LsbSteganography::class)
            ->set(Sha256Hasher::class)
            ->set(RandomBytesTokenGenerator::class)
            // pic-auth config
            ->set(AuthConfig::class)
                ->arg(0, $stegano)
                ->arg(1, $hasher)
                ->arg(2, new ReferenceConfigurator(RandomBytesTokenGenerator::class))
                ->arg(3, $config['pic_auth']['token_length'] ?? 32)
                ->arg(4, $config['pic_auth']['pepper'] ?? null)
            // pic-auth manager
            ->set(AuthManager::class)
                ->arg(0, new ReferenceConfigurator(AuthConfig::class))
        ;
    }
}
