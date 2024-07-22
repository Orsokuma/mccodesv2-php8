<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class PreferencesController extends CommonObjects
{
    private const string SETTING_UPDATED = 'Your %s has been updated.';
    private const string INCORRECT_PASSWORD = 'The current password you entered was incorrect.';
    private const string PASSWORDS_NO_MATCH = 'Your "new" and "confirm" passwords don\'t match. Note: Passwords are case-sensitive.';
    private const string INVALID_IMAGE = 'The URL you entered didn\'t validate as an image.';

    /**
     * @return string[]
     */
    protected function doUpdateSex(): array
    {
        if (empty($_POST['verf']) || !$this->func->verify_csrf_code('prefs_sex', $_POST['verf'])) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        $opposite = $this->player['gender'] === 'Male' ? 'Female' : 'Male';
        $this->pdo->update(
            'users',
            ['gender' => $opposite],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::SETTING_UPDATED, 'sex'),
        ];
    }

    /**
     * @return array|string[]
     */
    protected function doUpdatePass(): array
    {
        if (empty($_POST['verf']) || !$this->func->verify_csrf_code('prefs_pass', $_POST['verf'])) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        $strs = ['current', 'new', 'confirm'];
        foreach ($strs as $str) {
            $_POST[$str] = array_key_exists($str, $_POST) && !empty($_POST[$str]) ? trim($_POST[$str]) : null;
            if (empty($_POST[$str])) {
                return [
                    'type' => 'error',
                    'message' => sprintf(self::MISSED_REQUIRED_FIELD, $str . ' password'),
                ];
            }
        }
        if (!$this->func->verify_user_password($_POST['current'], $this->player['pass_salt'], $this->player['userpass'])) {
            return [
                'type' => 'error',
                'message' => self::INCORRECT_PASSWORD,
            ];
        }
        if ($_POST['new'] !== $_POST['confirm']) {
            return [
                'type' => 'error',
                'message' => self::PASSWORDS_NO_MATCH,
            ];
        }
        $new = $this->func->encode_password($_POST['new'], $this->player['pass_salt']);
        $this->pdo->update(
            'users',
            ['userpass' => $new],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::SETTING_UPDATED, 'password'),
        ];
    }

    /**
     * @return array
     */
    protected function doUpdateName(): array
    {
        if (empty($_POST['verf']) || !$this->func->verify_csrf_code('prefs_forum', $_POST['verf'])) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        $_POST['name'] = array_key_exists('name', $_POST) && !empty($_POST['name']) ? trim($_POST['name']) : null;
        if (empty($_POST['name'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'name'),
            ];
        }
        if ($_POST['name'] === $this->player['username']) {
            return [
                'type' => 'info',
                'message' => self::NO_CHANGES,
            ];
        }
        $name_length = strlen($_POST['name']);
        if ($name_length > 32 || $name_length < 3) {
            return [
                'type' => 'error',
                'message' => 'Usernames must be between 3 and 32 characters. You entered ' . $name_length . '.',
            ];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+|\s+/', $_POST['name'])) {
            return [
                'type' => 'error',
                'message' => 'Usernames are limited to letters, numbers, hyphens, and spaces only.',
            ];
        }
        $dupe = $this->pdo->cell(
            'SELECT COUNT(*) FROM users WHERE ? IN (LOWER(username), LOWER(login_name)) AND userid <> ?',
            strtolower($_POST['name']),
            $this->player['userid'],
        );
        if ($dupe) {
            return [
                'type' => 'error',
                'message' => self::USERNAME_TAKEN,
            ];
        }
        $this->pdo->update(
            'users',
            ['username' => $_POST['name']],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::SETTING_UPDATED, 'name'),
        ];
    }

    /**
     * @return array
     */
    protected function doUpdatePic(): array
    {
        if (empty($_POST['verf']) || !$this->func->verify_csrf_code('prefs_pic', $_POST['verf'])) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        $_POST['pic'] = array_key_exists('pic', $_POST) && !empty($_POST['pic']) ? trim($_POST['pic']) : '';
        if (!empty($_POST['pic'])) {
            if (!str_starts_with($_POST['pic'], 'https://')) {
                $_POST['pic'] = 'https://' . $_POST['pic'];
            }
            if (!$this->func->getRemoteFileSize($_POST['pic'])) {
                return [
                    'type' => 'error',
                    'message' => self::INVALID_IMAGE,
                ];
            }
            $parts = @getimagesize($_POST['pic']);
            if (empty($parts) || !is_array($parts)) {
                return [
                    'type' => 'error',
                    'message' => self::INVALID_IMAGE,
                ];
            }
        }
        $this->pdo->update(
            'users',
            ['display_pic' => $_POST['pic']],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::SETTING_UPDATED, 'display picture'),
        ];
    }

    /**
     * @return array
     */
    protected function doUpdateForum(): array
    {
        if (empty($_POST['verf']) || !$this->func->verify_csrf_code('prefs_forum', $_POST['verf'])) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        $_POST['avatar']    = array_key_exists('avatar', $_POST) && !empty($_POST['avatar']) ? trim($_POST['avatar']) : '';
        $_POST['signature'] = array_key_exists('signature', $_POST) && !empty($_POST['signature']) ? trim($_POST['signature']) : '';
        $signature_length   = strlen($_POST['signature']);
        if ($signature_length > 250) {
            return [
                'type' => 'error',
                'message' => 'Forum signatures are limited to 250 characters. You entered ' . $signature_length . '.',
            ];
        }
        if (!empty($_POST['avatar'])) {
            if (!str_starts_with($_POST['avatar'], 'https://')) {
                $_POST['avatar'] = 'https://' . $_POST['avatar'];
            }
            if (!$this->func->getRemoteFileSize($_POST['avatar'])) {
                return [
                    'type' => 'error',
                    'message' => self::INVALID_IMAGE,
                ];
            }
            $parts = @getimagesize($_POST['avatar']);
            if (empty($parts) || !is_array($parts)) {
                return [
                    'type' => 'error',
                    'message' => self::INVALID_IMAGE,
                ];
            }
        }
        $this->pdo->update(
            'users',
            [
                'forums_avatar' => $_POST['avatar'],
                'forums_signature' => $_POST['signature'],
            ],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::SETTING_UPDATED, 'forum info'),
        ];
    }
}
