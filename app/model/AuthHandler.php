<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class AuthHandler extends AuthController
{
    /**
     * @param string|null $subRoute
     * @return void
     */
    public function get(?string $subRoute = null): void
    {
        echo 'ROUTE: '.$subRoute;
        match ($subRoute) {
            'logout' => $this->doLogout(),
            'register' => $this->displayRegister(),
            null, '', '/', 'login' => $this->displayLogin(),
            default => ToroHook::fire('404'),
        };
    }

    /**
     * Note: Stops processing at end of method call
     * @return void
     */
    protected function doLogout(): void
    {
        if (!isset($_SESSION['started'])) {
            session_regenerate_id();
            $_SESSION['started'] = true;
        }
        if (isset($_SESSION['userid'])) {
            $sessid = (int)$_SESSION['userid'];
            if (isset($_SESSION['attacking']) && $_SESSION['attacking'] > 0) {
                $this->func->event_add($sessid, 'You logged out during a fight and have lost your exp for this level as a result');
                $this->pdo->update(
                    'users',
                    [
                        'exp' => 0,
                        'attacking' => 0,
                    ],
                    ['userid' => $sessid],
                );
                $_SESSION['attacking'] = 0;
            }
        }
        session_regenerate_id(true);
        session_unset();
        session_destroy();
        header('Location: /');
        exit;
    }

    /**
     * @return void
     */
    private function displayLogin(): void
    {
        $content = file_get_contents($this->view . '/non-auth/login.html');
        echo strtr($content, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('login'),
        ]);
    }

    /**
     * @return void
     */
    private function displayRegister(): void
    {
        $this->setCaptcha();
        $content = file_get_contents($this->view . '/non-auth/register.html');
        preg_match('/\{{2}IF:SETTINGS:REGCAP_ON}{2}(.+?)\{{2}ENDIF}{2}/s', $content, $matches);
        $content = $this->siteSettings['regcap_on']
            ? str_replace($matches[0], $matches[1], $content)
            : str_replace($matches[0], '', $content);
        echo strtr($content, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('register'),
        ]);
    }

    /**
     * @param string|null $subRoute
     * @return void
     * @throws Throwable
     */
    public function post(?string $subRoute = null): void
    {
        $this->afterPost($this->handlePost($subRoute));
    }

    /**
     * @param string|null $subRoute
     * @return array
     * @throws Throwable
     */
    private function handlePost(?string $subRoute = null): array
    {
        return match ($subRoute) {
            'register' => $this->doRegister(),
            default => $this->doLogin(),
        };
    }
}
