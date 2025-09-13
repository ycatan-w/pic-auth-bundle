<?php

declare(strict_types=1);

namespace Tests\Crayon\PicAuthBundle\Maker;

use Crayon\PicAuthBundle\Maker\MakePicFormLogin;
use Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Yaml\Yaml;

class MakePicFormLoginTest extends TestCase
{
    public function testStaticMethod(): void
    {
        $this->assertEquals('make:pic-auth:form-login', MakePicFormLogin::getCommandName());
        $this->assertEquals('Generate the code needed for the custom_authenticators authenticator with ' . FormLoginPicAuthenticator::class, MakePicFormLogin::getCommandDescription());
    }

    public function testConfigureCommand(): void
    {
        $command = $this->createMockObject(Command::class);
        $command->expects($this->once())
            ->method('setHelp')
            ->with('
The <info>%command.name%</info> command generates a controller and Twig template
to allow users to login using the custom_authenticators authenticator with ' . FormLoginPicAuthenticator::class . '.

The controller name can be customized by answering the
questions asked when running <info>%command.name%</info>.

This will also update your <info>security.yaml</info> for the new authenticator.

<info>php %command.full_name%</info>');

        $ic = new InputConfiguration();

        $maker = $this->createMaker($this->createFileManagerMock());
        $maker->configureCommand($command, $ic);
        $this->assertEmpty($ic->getNonInteractiveArguments());
    }

    public function testConfigureDependencies(): void
    {
        $deps     = new DependencyBuilder();
        $class    = new \ReflectionClass(DependencyBuilder::class);
        $property = $class->getProperty('dependencies');
        $property->setAccessible(true);
        $maker = $this->createMaker($this->createFileManagerMock());
        $maker->configureDependencies($deps);
        $this->assertCount(4, $property->getValue($deps));

        $this->assertEquals([
            ['class' => SecurityBundle::class, 'name' => 'security', 'required' => true],
            ['class' => TwigBundle::class, 'name' => 'twig', 'required' => true],
            ['class' => Yaml::class, 'name' => 'yaml', 'required' => true],
            ['class' => DoctrineBundle::class, 'name' => 'orm', 'required' => true],
        ], $property->getValue($deps));
    }

    public function testInteract(): void
    {
        $fileManager = $this->createFileManagerMock();
        $fileManager->expects($this->once())
            ->method('fileExists')
            ->with('config/packages/security.yaml')
            ->willReturn(true);
        $fileManager->expects($this->once())
            ->method('getFileContents')
            ->with('config/packages/security.yaml')
            ->willReturn('
security:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: username
');

        $input = $this->createMockObject(Input::class);
        // interact to false to use default values
        $input->method('isInteractive')
            ->willReturn(false);
        $output = $this->createMockObject(OutputInterface::class);
        $cmd    = $this->createMockObject(Command::class);

        $maker = $this->createMaker($fileManager);
        $maker->interact($input, new ConsoleStyle($input, $output), $cmd);

        $r         = new \ReflectionClass(MakePicFormLogin::class);
        $propValue = function (string $property) use ($r, $maker): string {
            $p = $r->getProperty($property);
            $p->setAccessible(true);

            return $p->getValue($maker);
        };

        $this->assertEquals('SecurityController', $propValue('controllerName'));
        $this->assertEquals('main', $propValue('firewallToUpdate'));
        $this->assertEquals('username', $propValue('userNameField'));
    }

    public function testInteractWithSecurityFileNotFound(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('The file "config/packages/security.yaml" does not exist. PHP & XML configuration formats are currently not supported.');
        $fileManager = $this->createFileManagerMock();
        $fileManager->expects($this->once())
            ->method('fileExists')
            ->with('config/packages/security.yaml')
            ->willReturn(false);

        $input  = $this->createMockObject(Input::class);
        $output = $this->createMockObject(OutputInterface::class);
        $cmd    = $this->createMockObject(Command::class);

        $maker = $this->createMaker($fileManager);
        $maker->interact($input, new ConsoleStyle($input, $output), $cmd);
    }

    public function testInteractWithNoSecurityData(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('To generate a form login authentication, you must configure at least one entry under "providers" in "security.yaml".');
        $fileManager = $this->createFileManagerMock();
        $fileManager->expects($this->once())
            ->method('fileExists')
            ->with('config/packages/security.yaml')
            ->willReturn(true);
        $fileManager->expects($this->once())
            ->method('getFileContents')
            ->with('config/packages/security.yaml')
            ->willReturn('
security_but_not_really:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: username
    ');

        $input  = $this->createMockObject(Input::class);
        $output = $this->createMockObject(OutputInterface::class);
        $cmd    = $this->createMockObject(Command::class);

        $maker = $this->createMaker($fileManager);
        $maker->interact($input, new ConsoleStyle($input, $output), $cmd);
    }

    public function testGenerate(): void
    {
        $fileManager  = $this->createFileManagerMock();
        $generator    = $this->createMockObject(Generator::class);
        $input        = $this->createMockObject(Input::class);
        $output       = $this->createMockObject(OutputInterface::class);
        $classDetails = new ClassNameDetails('fullClassName', 'namespacePrefix', '');

        $generator->expects($this->once())
            ->method('createClassNameDetails')
            ->willReturn($classDetails);
        $generator->expects($this->once())
            ->method('generateController')
            ->with(
                'fullClassName',
                $this->stringContains('/templates/controller/LoginController.tpl.php'),
                $this->equalTo(
                    [
                        'use_statements' => new UseStatementGenerator([
                            AbstractController::class,
                            Response::class,
                            Route::class,
                            AuthenticationUtils::class,
                        ]),
                        'controller_name' => 'fullClassName',
                        'template_path'   => 'fullclassname',
                    ]
                )
            );
        $generator->expects($this->once())
            ->method('generateTemplate')
            ->with(
                $this->stringContains('/login.html.twig'),
                $this->stringContains('/templates/twig/login_form.tpl.php'),
                $this->equalTo(
                    [
                        'username_label'    => 'User Name Field',
                        'username_is_email' => false,
                    ]
                )
            );
        $generator->expects($this->once())
            ->method('dumpFile')
            ->with('config/packages/security.yaml', '
security:
    firewalls:
        firewallToUpdate:
            custom_authenticators:
                - ' . FormLoginPicAuthenticator::class . '
');
        $generator->expects($this->once())
            ->method('writeChanges');

        $maker = $this->createMaker($fileManager);
        // inject fake value into maker
        $this->injectFakeValueIntoProperty($maker);

        // generate fonction
        $maker->generate($input, new ConsoleStyle($input, $output), $generator);
    }

    private function injectFakeValueIntoProperty(MakePicFormLogin $maker): void
    {
        $r            = new \ReflectionClass(MakePicFormLogin::class);
        $setPropValue = function (string $property, $value) use ($r, $maker): void {
            $p = $r->getProperty($property);
            $p->setAccessible(true);

            $p->setValue($maker, $value);
        };
        $setPropValue('controllerName', 'controllerName');
        $setPropValue('userNameField', 'userNameField');
        $setPropValue('firewallToUpdate', 'firewallToUpdate');

        $manipulator = $this->createMockObject(YamlSourceManipulator::class);
        $manipulator->expects($this->once())
            ->method('getData')
            ->willReturn([
                'security' => [
                    'firewalls' => [
                        'firewallToUpdate' => [],
                    ],
                ],
            ]);
        $manipulator->expects($this->once())
            ->method('setData')
            ->with([
                'security' => [
                    'firewalls' => [
                        'firewallToUpdate' => [
                            'custom_authenticators' => [FormLoginPicAuthenticator::class],
                        ],
                    ],
                ],
            ]);

        $manipulator->expects($this->once())
            ->method('getContents')
            ->willReturn('
security:
    firewalls:
        firewallToUpdate:
            custom_authenticators:
                - ' . FormLoginPicAuthenticator::class . '
');
        $setPropValue('ysm', $manipulator);
    }

    private function createFileManagerMock(): MockObject|FileManager
    {
        return $this->createMockObject(FileManager::class);
    }

    /**
     *
     * @param  string  $classname
     *
     * @return MockObject|Command|Input|OutputInterface|Generator|YamlSourceManipulator
     */
    private function createMockObject(string $classname): MockObject
    {
        return $this->getMockBuilder($classname)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createMaker(FileManager $fileManager): MakePicFormLogin
    {
        return new MakePicFormLogin($fileManager);
    }
}
