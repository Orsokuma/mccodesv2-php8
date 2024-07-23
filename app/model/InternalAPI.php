<?php
declare(strict_types=1);

session_name('MCCSID');
session_start();
if (!isset($_SESSION['started'])) {
    session_regenerate_id();
    $_SESSION['started'] = true;
}

/**
 *
 */
class InternalAPI extends CommonObjects
{
    /**
     * @param string|null $subRoute
     * @param string|null $content
     * @return void
     */
    public function get_xhr(?string $subRoute = null, ?string $content = null): void
    {
        $response = match ($subRoute) {
            'is-name-used' => $this->isNameUsed($content),
            'is-email-used' => $this->isEmailUsed($content),
            default => null,
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * @param string|null $name
     * @return bool
     */
    private function isNameUsed(?string $name): bool
    {
        if (empty($name)) {
            return false;
        }
        return $this->pdo->exists(
            'SELECT COUNT(*) FROM users WHERE ? IN (LOWER(username), LOWER(login_name)) LIMIT 1',
            strtolower($name),
        );
    }

    /**
     * @param string|null $email
     * @return bool
     */
    private function isEmailUsed(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }
        return $this->pdo->exists(
            'SELECT COUNT(*) FROM users WHERE LOWER(email) = ? LIMIT 1',
            strtolower($email),
        );
    }
}
