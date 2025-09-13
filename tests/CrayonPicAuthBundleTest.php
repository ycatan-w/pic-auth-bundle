<?php

declare(strict_types=1);

namespace Tests\Crayon\PicAuthBundle;

use Crayon\PicAuth\AuthManager;
use Crayon\PicAuth\Config\AuthConfig;
use Crayon\PicAuth\Hasher\Sha256Hasher;
use Crayon\PicAuth\Stego\Lsb\LsbSteganography;
use Crayon\PicAuth\Token\RandomBytesTokenGenerator;
use Crayon\PicAuthBundle\CrayonPicAuthBundle;
use Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\StringNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class CrayonPicAuthBundleTest extends TestCase
{
    public function testConfigure(): void
    {
        $definition = $this->getMockBuilder(DefinitionConfigurator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rootNode = $this->getMockBuilder(ArrayNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rootChildren = $this->getMockBuilder(NodeBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $rootChildren->expects($this->exactly(2))
            ->method('arrayNode')
            ->willReturnCallback(function ($nodeStr) use ($rootChildren) {
                return match ($nodeStr) {
                    'pic_auth'      => $this->createPicAuthNode($rootChildren),
                    'authenticator' => $this->createAuthenticatorNode($rootChildren),
                };
            });
        $rootChildren->expects($this->once())
            ->method('end')
            ->willReturn($rootNode);

        $definition->expects($this->once())
            ->method('rootNode')
            ->willReturn($rootNode);
        $rootNode->expects($this->once())
            ->method('children')
            ->willReturn($rootChildren);
        $bundle = new CrayonPicAuthBundle();
        $bundle->configure($definition);
        $this->assertTrue(true);
    }

    public function testLoadExtension(): void
    {
        $phpFileLoader = $this->getMockBuilder(PhpFileLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $arr           = [];
        $builder       = new ContainerBuilder();
        $containerConf = new ContainerConfigurator($builder, $phpFileLoader, $arr, '', '');

        $bundle = new CrayonPicAuthBundle();
        $bundle->loadExtension([], $containerConf, $builder);
        $this->assertTrue($builder->has(FormLoginPicAuthenticator::class));
        $this->assertTrue($builder->has(LsbSteganography::class));
        $this->assertTrue($builder->has(Sha256Hasher::class));
        $this->assertTrue($builder->has(RandomBytesTokenGenerator::class));
        $this->assertTrue($builder->has(AuthConfig::class));
        $this->assertTrue($builder->has(AuthManager::class));
    }

    public function testLoadExtensionInvalidStegano(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Steganography method');
        $builder = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerConf = $this->getMockBuilder(ContainerConfigurator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bundle = new CrayonPicAuthBundle();
        $bundle->loadExtension(['pic_auth' => ['stegano' => 'fake']], $containerConf, $builder);
    }

    public function testLoadExtensionInvalidHasher(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Hash method');
        $builder = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerConf = $this->getMockBuilder(ContainerConfigurator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bundle = new CrayonPicAuthBundle();
        $bundle->loadExtension(['pic_auth' => ['hasher' => 'fake']], $containerConf, $builder);
    }

    private function createPicAuthNode($rootChildren)
    {
        $picAuthNode = $this->getMockBuilder(ArrayNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $picAuthChildrenNode = $this->getMockBuilder(NodeBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $picAuthChildrenIntegerNode = $this->getMockBuilder(IntegerNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $picAuthChildrenStringNode = $this->getMockBuilder(StringNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $picAuthChildrenScalarNode = $this->getMockBuilder(ScalarNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();

        $picAuthChildrenNode->expects($this->once())
            ->method('integerNode')
            ->with('token_length')
            ->willReturn($picAuthChildrenIntegerNode);

        $picAuthChildrenNode->expects($this->exactly(2))
            ->method('stringNode')
            ->with(
                $this->logicalOr(
                    $this->equalTo('hasher'),
                    $this->equalTo('stegano'),
                )
            )
            ->willReturn($picAuthChildrenStringNode);
        $picAuthChildrenNode->expects($this->once())
            ->method('scalarNode')
            ->with('pepper')
            ->willReturn($picAuthChildrenScalarNode);

        $picAuthChildrenIntegerNode->expects($this->once())
            ->method('defaultValue')
            ->with(32)
            ->willReturnSelf();
        $picAuthChildrenIntegerNode->expects($this->once())
            ->method('end')
            ->willReturn($picAuthChildrenNode);

        $picAuthChildrenStringNode->expects($this->exactly(2))
            ->method('defaultValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo('sha256'),
                    $this->equalTo('lsb'),
                )
            )
            ->willReturnSelf();
        $picAuthChildrenStringNode->expects($this->exactly(2))
            ->method('end')
            ->willReturn($picAuthChildrenNode);
        $picAuthChildrenScalarNode->expects($this->once())
            ->method('defaultNull')
            ->willReturnSelf();
        $picAuthChildrenScalarNode->expects($this->once())
            ->method('end')
            ->willReturn($picAuthChildrenNode);
        $picAuthChildrenNode->expects($this->once())
            ->method('end')
            ->willReturn($picAuthNode);

        $picAuthNode->expects($this->once())
            ->method('children')
            ->willReturn($picAuthChildrenNode);
        $picAuthNode->expects($this->once())
            ->method('end')
            ->willReturn($rootChildren);

        return $picAuthNode;
    }

    private function createAuthenticatorNode($rootChildren)
    {
        $authenticatorNode = $this->getMockBuilder(ArrayNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authenticatorChildrendNode = $this->getMockBuilder(NodeBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authenticatorChildrenStringdNode = $this->getMockBuilder(StringNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authenticatorChildrenLastStringdNode = $this->getMockBuilder(StringNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authenticatorChildrenBooleandNode = $this->getMockBuilder(BooleanNodeDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();

        $authenticatorChildrenStringdNode->expects($this->exactly(7))
            ->method('defaultValue')
            ->with(
                $this->logicalOr(
                    $this->equalTo('_username'),
                    $this->equalTo('_image'),
                    $this->equalTo('_csrf_token'),
                    $this->equalTo('authenticate'),
                    $this->equalTo('_target_path'),
                    $this->equalTo('/'),
                    $this->equalTo('/login'),
                )
            )
            ->willReturnSelf();
        $authenticatorChildrenStringdNode->expects($this->exactly(7))
            ->method('end')
            ->willReturn($authenticatorChildrendNode);
        $authenticatorChildrenLastStringdNode->expects($this->once())
            ->method('defaultValue')
            ->with('/login')
            ->willReturnSelf();
        $authenticatorChildrenLastStringdNode->expects($this->once())
            ->method('end')
            ->willReturn($authenticatorChildrendNode);
        $authenticatorChildrenBooleandNode->expects($this->once())
            ->method('defaultTrue')
            ->willReturnSelf();
        $authenticatorChildrenBooleandNode->expects($this->exactly(2))
            ->method('defaultFalse')
            ->willReturnSelf();
        $authenticatorChildrenBooleandNode->expects($this->exactly(3))
            ->method('end')
            ->willReturn($authenticatorChildrendNode);

        $authenticatorChildrendNode->expects($this->exactly(8))
            ->method('stringNode')
            ->willReturnCallback(function ($str) use ($authenticatorChildrenLastStringdNode, $authenticatorChildrenStringdNode) {
                return match ($str) {
                    'login_path' => $authenticatorChildrenLastStringdNode,
                    'username_parameter', 'image_parameter', 'csrf_parameter', 'csrf_token_id', 'target_path_parameter', 'default_target_path', 'check_path' => $authenticatorChildrenStringdNode,
                };
            });
        $authenticatorChildrendNode->expects($this->exactly(3))
           ->method('booleanNode')
           ->with(
               $this->logicalOr(
                   $this->equalTo('enable_csrf'),
                   $this->equalTo('always_use_default_target_path'),
                   $this->equalTo('use_referer'),
               )
           )
           ->willReturn($authenticatorChildrenBooleandNode);

        $authenticatorChildrendNode->expects($this->once())
            ->method('end')
            ->willReturn($authenticatorNode);

        $authenticatorNode->expects($this->once())
            ->method('children')
            ->willReturn($authenticatorChildrendNode);

        $authenticatorNode->expects($this->once())
            ->method('end')
            ->willReturn($rootChildren);

        return $authenticatorNode;
    }
}
