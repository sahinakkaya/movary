<?php declare(strict_types=1);

namespace Movary\HttpController;

use Movary\Domain\User\Exception\PasswordTooShort;
use Movary\Domain\User\Exception\UsernameInvalidFormat;
use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\Util\SessionWrapper;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Movary\ValueObject\Config;
use Twig\Environment;
use Throwable;

class CreateUserController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
        private readonly Config $config,
        private readonly SessionWrapper $sessionWrapper,
    ) {
    }

    public function render() : Response
    {
        if ($this->userApi->hasUsers() === true && strtolower($this->config->getAsString('REGISTRATION', 'false')) == 'false' || $this->authenticationService->isUserAuthenticated() === true) {
            return Response::createSeeOther('/');
        }

        $errorPasswordTooShort = $this->sessionWrapper->find('errorPasswordTooShort');
        $errorPasswordNotEqual = $this->sessionWrapper->find('errorPasswordNotEqual');
        $errorUsernameInvalidFormat = $this->sessionWrapper->find('errorUsernameInvalidFormat');
        $errorGeneric = $this->sessionWrapper->find('errorGeneric');

        $this->sessionWrapper->unset('errorPasswordTooShort', 'errorPasswordNotEqual', 'errorUsernameInvalidFormat', 'errorGeneric');

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('page/create-user.html.twig', [
                'errorPasswordTooShort' => $errorPasswordTooShort,
                'errorPasswordNotEqual' => $errorPasswordNotEqual,
                'errorUsernameInvalidFormat' => $errorUsernameInvalidFormat,
                'errorGeneric' => $errorGeneric,
            ]),
        );
    }

    public function createUser(Request $request) : Response
    {
        if ($this->userApi->hasUsers() === true && strtolower($this->config->getAsString('REGISTRATION', 'false')) == 'false') {
            return Response::createSeeOther('/');
        }

        $postParameters = $request->getPostParameters();

        $email = isset($postParameters['email']) === false ? null : (string)$postParameters['email'];
        $name = isset($postParameters['name']) === false ? null : (string)$postParameters['name'];
        $password = isset($postParameters['password']) === false ? null : (string)$postParameters['password'];
        $repeatPassword = isset($postParameters['password']) === false ? null : (string)$postParameters['repeatPassword'];

        if ($email === null || $name === null || $password === null || $repeatPassword === null) {
            $this->sessionWrapper->set('missingFormData', true);

            return Response::create(
                StatusCode::createSeeOther(),
                null,
                [Header::createLocation($_SERVER['HTTP_REFERER'])],
            );
        }

        if ($password !== $repeatPassword) {
            $this->sessionWrapper->set('errorPasswordNotEqual', true);

            return Response::create(
                StatusCode::createSeeOther(),
                null,
                [Header::createLocation($_SERVER['HTTP_REFERER'])],
            );
        }

        try {
            $this->userApi->createUser($email, $password, $name);

            $this->authenticationService->login($email, $password, false);
        } catch (PasswordTooShort) {
            $this->sessionWrapper->set('errorPasswordTooShort', true);
        } catch (UsernameInvalidFormat) {
            $this->sessionWrapper->set('errorUsernameInvalidFormat', true);
        } catch (Throwable) {
            $this->sessionWrapper->set('errorGeneric', true);
        }

        return Response::create(
            StatusCode::createSeeOther(),
            null,
            [Header::createLocation($_SERVER['HTTP_REFERER'])],
        );
    }
}
