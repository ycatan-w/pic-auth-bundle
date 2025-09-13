<?php

declare(strict_types=1);

namespace Crayon\PicAuthBundle\Maker;

use Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Security\InteractiveSecurityHelper;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Yaml\Yaml;

/**
 * MakePicFormLogin class.
 */
class MakePicFormLogin extends AbstractMaker
{
    private const SECURITY_CONFIG_PATH = 'config/packages/security.yaml';
    private YamlSourceManipulator $ysm;
    private string $controllerName;
    private string $firewallToUpdate;
    private string $userNameField;

    /**
     * @param  FileManager $fileManager
     */
    public function __construct(
        private readonly FileManager $fileManager,
    ) {
    }

    /**
     * @return string
     */
    public static function getCommandName(): string
    {
        return 'make:pic-auth:form-login';
    }

    /**
     * @return string
     */
    public static function getCommandDescription(): string
    {
        return 'Generate the code needed for the custom_authenticators authenticator with ' . FormLoginPicAuthenticator::class;
    }

    /**
     * @param  Command            $command
     * @param  InputConfiguration $inputConfig
     *
     * @return void
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp('
The <info>%command.name%</info> command generates a controller and Twig template
to allow users to login using the custom_authenticators authenticator with ' . FormLoginPicAuthenticator::class . '.

The controller name can be customized by answering the
questions asked when running <info>%command.name%</info>.

This will also update your <info>security.yaml</info> for the new authenticator.

<info>php %command.full_name%</info>'
            );
    }

    /**
     * @param  DependencyBuilder $dependencies
     *
     * @return void
     */
    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(SecurityBundle::class, 'security');
        $dependencies->addClassDependency(TwigBundle::class, 'twig');

        // // needed to update the YAML files
        $dependencies->addClassDependency(Yaml::class, 'yaml');
        $dependencies->addClassDependency(DoctrineBundle::class, 'orm');
    }

    /**
     * @param  InputInterface $input
     * @param  ConsoleStyle   $io
     * @param  Command        $command
     *
     * @return void
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$this->fileManager->fileExists(self::SECURITY_CONFIG_PATH)) {
            throw new RuntimeCommandException(\sprintf('The file "%s" does not exist. PHP & XML configuration formats are currently not supported.', self::SECURITY_CONFIG_PATH));
        }

        $this->ysm    = new YamlSourceManipulator($this->fileManager->getFileContents(self::SECURITY_CONFIG_PATH));
        $securityData = $this->ysm->getData();

        if (!isset($securityData['security']['providers']) || !$securityData['security']['providers']) {
            throw new RuntimeCommandException('To generate a form login authentication, you must configure at least one entry under "providers" in "security.yaml".');
        }

        $securityHelper       = new InteractiveSecurityHelper();
        $this->controllerName = $io->ask(
            'Choose a name for the controller class (e.g. <fg=yellow>SecurityController</>)',
            'SecurityController',
            Validator::validateClassName(...)
        );
        $this->firewallToUpdate = $securityHelper->guessFirewallName($io, $securityData);
        $userClass              = $securityHelper->guessUserClass($io, $securityData['security']['providers']);
        $this->userNameField    = $securityHelper->guessUserNameField($io, $userClass, $securityData['security']['providers']);
    }

    /**
     * @param  InputInterface $input
     * @param  ConsoleStyle   $io
     * @param  Generator      $generator
     *
     * @return void
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $useStatements = new UseStatementGenerator([
            AbstractController::class,
            Response::class,
            Route::class,
            AuthenticationUtils::class,
        ]);
        $controllerNameDetails = $generator->createClassNameDetails($this->controllerName, 'Controller\\', 'Controller');
        $templatePath          = strtolower($controllerNameDetails->getRelativeNameWithoutSuffix());

        $generator->generateController(
            $controllerNameDetails->getFullName(),
            \dirname(__DIR__, 2) . '/templates/controller/LoginController.tpl.php',
            [
                'use_statements'  => $useStatements,
                'controller_name' => $controllerNameDetails->getShortName(),
                'template_path'   => $templatePath,
            ]
        );

        $generator->generateTemplate(
            \sprintf('%s/login.html.twig', $templatePath),
            \dirname(__DIR__, 2) . '/templates/twig/login_form.tpl.php',
            [
                'username_label'    => Str::asHumanWords($this->userNameField),
                'username_is_email' => false !== stripos($this->userNameField, 'email'),
            ]
        );

        $securityConfig                                                                            = $this->ysm->getData();
        $securityConfig['security']['firewalls'][$this->firewallToUpdate]['custom_authenticators'] = [FormLoginPicAuthenticator::class];
        $this->ysm->setData($securityConfig);
        $generator->dumpFile(self::SECURITY_CONFIG_PATH, $this->ysm->getContents());

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            \sprintf('Next: Review and adapt the login template: <info>%s/login.html.twig</info> to suit your needs.', $templatePath),
        ]);
    }
}
