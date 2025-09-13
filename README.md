# CrayonPicAuthBundle

A Symfony bundle providing integration with [crayon/pic-auth](https://github.com/ycatan-w/pic-auth),
enabling authentication through **badge- and listener-based security mechanisms**.
It offers a ready-to-use authenticator, user interface contract, event listeners, and maker commands
to quickly set up form-based authentication in your Symfony project.

**⚠️ Note:** This bundle, like `pic-auth` lib, is intended for fun and experimental purposes. It is **not recommended for production or security-critical projects**.

---

## Features

- Symfony Security **authenticator** powered by `pic-auth`.
- Custom **badge** (`PicCredentials`) to extend authentication logic.
- User **interface contract** (`PicAuthenticatedUserInterface`) to implement in your User entity.
- Security **event listener** (`CheckPicCredentialsListener`) to react to login and authentication events.
- Symfony **Maker command** (`make:pic-auth:form-login`) for generating controllers and login forms.

---

## Installation

Before installing the bundle, you must add the repositories to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:ycatan-w/pic-auth.git"
    },
    {
      "type": "vcs",
      "url": "git@github.com:ycatan-w/pic-auth-bundle.git"
    }
  ]
}
```

Then install via Composer:

```bash
composer require crayon/pic-auth-bundle
```

Make sure the bundle is registered in your Symfony app (usually auto-registered):

```php
// config/bundles.php
return [
    CrayonPic\AuthBundle\CrayonPicAuthBundle::class => ['all' => true],
];
```

---

## Configuration

To configure the bundle, copy the example configuration file into your project:

```bash
cp vendor/crayon/pic-auth-bundle/config/packages/pic_auth.yaml.dist config/packages/pic_auth.yaml
```

This file contains all configurable options with their default values. You can adjust them as needed:

```yaml
# config/packages/pic_auth.yaml.dist
crayon_pic_auth:
  pic_auth:
    token_length: 32
    hasher: sha256
    stegano: lsb
    pepper: ~
  authenticator:
    username_parameter: _username
    image_parameter: _image
    enable_csrf: true
    csrf_parameter: _csrf_token
    csrf_token_id: authenticate
    always_use_default_target_path: false
    target_path_parameter: _target_path
    default_target_path: /
    use_referer: false
    check_path: /login
    login_path: /login
```

> The values defined here are used by default if the configuration file is not present, so copying it is optional but recommended for customization.

---

## Usage

1. **Create your User entity**
   Follow the Symfony documentation to create a user: [Symfony Security: Users](https://symfony.com/doc/current/security.html#the-user), but **do not enable password usage**.

   Implement the `PicAuthenticatedUserInterface` in your `User` entity to make it compatible with `pic-auth`.

   Update your entity to include two string properties for pic-auth authentication:

   ```php
   private string $hash;
   private string $token;
   ```

   If needed, you can use the Maker command to update your entity:

   ```bash
   bin/console make:entity User
   ```

2. **Setup the authenticator**
   You have two options:

   - **Quick setup with form login**:
     Use the Maker command to scaffold a login form and controller:

     ```bash
     bin/console make:pic-auth:form-login
     ```

     Then update the generated Twig template to fit your application’s UI.

   - **Manual setup**:
     Add the authenticator to your firewall in `security.yaml`:

     ```yaml
     firewalls:
       main:
         custom_authenticators:
           - Crayon\PicAuthBundle\Security\FormLoginPicAuthenticator
     ```

     Configure your login form following Symfony’s [Form Login documentation](https://symfony.com/doc/current/security.html#form-login), but replace the password input with a **file input** named `_image`.
     Don’t forget to set `enctype="multipart/form-data"` on the `<form>` tag.

     Most options from Symfony’s form login (CSRF, redirect target, parameter names) work as expected and can be customized via your `pic_auth.yaml`.

---

## Registering Users via AuthManager

You can register users using the `Crayon\PicAuth\AuthManager` service provided by `pic-auth`. Follow these steps to correctly generate the user's credentials:

1. **Update the registration form**

   - Replace the standard input with a **file input** for the image.
   - Make sure your form is properly configured for file uploads (use `enctype="multipart/form-data"`).

2. **Inject the AuthManager service**
   In your controller or service responsible for registering users:

   ```php
   use Crayon\PicAuth\AuthManager;

   public function __construct(private AuthManager $authManager) {}
   ```

3. **Validate the uploaded image**
   Ensure the user uploaded a valid image before generating credentials.

4. **Generate user credentials**
   Call the `stamp()` method of `AuthManager`:

   ```php
   $authStamp = $this->authManager->stamp($imagePath);
   ```

   This returns an `AuthStamp` object.

5. **Update the User entity**
   Set the `hash` and `token` properties:

   ```php
   $user->setHash($authStamp->hash);
   $user->setToken($authStamp->token);
   ```

6. **Persist the User**
   Save your user entity as usual with Doctrine or your persistence layer.

7. **Important:**
   The `AuthStamp` object also contains a `stampedImage` property. **This image should NOT be stored** in your database or in your application repository. It must be returned to the user immediately after registration, as it is required for login. Without it, the user will not be able to authenticate.

> More information about pic-auth can be found here: [pic-auth GitHub repository](https://github.com/ycatan-w/pic-auth)

---

## Example Integration Repository

For a fully functional example of `CrayonPicAuthBundle` integrated in a Symfony project, you can check out the following repository:

- [CrayonPicAuthBundle Demo](https://github.com/ycatan-w/pic-auth-examples)

This repository demonstrates:

- User registration with `AuthManager`
- Form login using `FormLoginPicAuthenticator`
- Proper handling of `_image` uploads and CSRF protection
- Full working security configuration for pic-auth
- Integration of Maker commands for scaffolding login forms

It is highly recommended to use this repository as a reference for integrating the bundle into your own projects.

---

## Project Structure

- `Security/FormLoginPicAuthenticator.php` – main authenticator logic.
- `Badge/PicCredentials.php` – custom badge for extending authentication.
- `User/PicAuthenticatedUserInterface.php` – contract for your User entity.
- `EventListener/CheckPicCredentialsListener.php` – reacts to authentication-related events.
- `Maker/MakePicFormLogin.php` – Maker command for generating login-related code.
- `templates/` – templates for login form and controller generation.
