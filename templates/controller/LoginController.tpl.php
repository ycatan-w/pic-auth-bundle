<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

<?php echo $use_statements; ?>

class <?php echo $controller_name; ?> extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('<?php echo $template_path; ?>/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}