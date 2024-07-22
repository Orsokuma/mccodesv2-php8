<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

use ParagonIE\EasyDB\EasyPlaceholder;

/**
 *
 */
abstract class AuthController extends CommonObjects
{
    private const string CREDENTIALS_DO_NOT_MATCH = 'Either the username/password you entered was incorrect, or they don\'t match an account.';
    private const string CAPTCHA_FAILED = 'Captcha test failed.';
    private const string PASSWORD_MISMATCH = 'The passwords you entered do not match. Note: Passwords <strong>are</strong> case-sensitive.';
    private const string REFERRER_NOT_EXISTS = 'Your "referrer" doesn\'t exist.';
    private const string MULTIPLE_ACCOUNTS_NOT_PERMITTED = 'Multiple accounts are not permitted.';

    /**
     * @return array
     */
    protected function doLogin(): array
    {
        $_POST['username'] = array_key_exists('username', $_POST) && !empty(trim($_POST['username'])) ? trim($_POST['username']) : null;
        $password          = array_key_exists('password', $_POST) && !empty(trim($_POST['password'])) ? trim($_POST['password']) : null;
        if (empty($_POST['username'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'username'),
            ];
        }
        if (empty($password)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'password'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT userid, userpass, pass_salt FROM users WHERE LOWER(login_name) = ?',
            strtolower($_POST['username']),
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => self::CREDENTIALS_DO_NOT_MATCH,
            ];
        }
        $login_failed = false;
        if (!empty($row['pass_salt'])) {
            $login_failed = !($this->func->verify_user_password($password, $row['pass_salt'], $row['userpass']));
        } else {
            if (md5($password) !== $row['userpass']) {
                $login_failed = true;
            }
            $salt    = $this->func->generate_pass_salt();
            $enc_psw = $this->func->encode_password($row['userpass'], $salt, true);
            $this->pdo->update(
                'users',
                [
                    'pass_salt' => $salt,
                    'userpass' => $enc_psw,
                ],
                ['userid' => $row['userid']],
            );
        }
        if ($login_failed) {
            return [
                'type' => 'error',
                'message' => self::CREDENTIALS_DO_NOT_MATCH,
            ];
        }
        session_regenerate_id();
        $_SESSION['loggedin'] = 1;
        $_SESSION['userid']   = $row['userid'];
        $this->pdo->update(
            'users',
            [
                'lastip_login' => $_SERVER['REMOTE_ADDR'],
                'last_login' => $_SERVER['REQUEST_TIME'],
            ],
            ['userid' => $row['userid']],
        );
        if ($this->siteSettings['validate_period'] === 'login' && $this->siteSettings['validate_on']) {
            $this->pdo->update(
                'users',
                ['verified' => 0],
                ['userid' => $row['userid']],
            );
        }
        return [
            'type' => 'success',
            'message' => 'Logged in successfully.',
            'redirect' => 'welcome',
        ];
    }

    /**
     * @return array|string[]
     * @throws Throwable
     */
    protected function doRegister(): array
    {
        $response = $this->checkRegisterPostData();
        if ($response) {
            return $response;
        }
        $response = $this->checkRegisterDataInUse();
        if ($response) {
            return $response;
        }
        $referral_ip_addr = '';
        $_POST['ref']     = isset($_POST['ref']) && is_numeric($_POST['ref']) ? abs(intval($_POST['ref'])) : '';
        $ip_addr          = $_SERVER['REMOTE_ADDR'];
        if ($_POST['ref']) {
            $referral_ip_addr = $this->pdo->cell(
                'SELECT lastip FROM users WHERE userid = ?',
                $_POST['ref'],
            );
            if (empty($referral_ip_addr)) {
                return [
                    'type' => 'error',
                    'message' => self::REFERRER_NOT_EXISTS,
                ];
            }
            if ($referral_ip_addr == $ip_addr) {
                return [
                    'type' => 'error',
                    'message' => self::MULTIPLE_ACCOUNTS_NOT_PERMITTED,
                ];
            }
        }
        $save = function () use ($ip_addr, $referral_ip_addr) {
            $sm = 100;
            if (isset($_POST['promo']) && $_POST['promo'] == $this->siteSettings['promo_code']) {
                $sm += 100;
            }
            $salt   = $this->func->generate_pass_salt();
            $encpsw = $this->func->encode_password($_POST['password'], $salt);
            $i      = $this->pdo->insert(
                'users',
                [
                    'username' => $_POST['username'],
                    'login_name' => $_POST['username'],
                    'userpass' => $encpsw,
                    'pass_salt' => $salt,
                    'email' => $_POST['email'],
                    'gender' => $_POST['gender'],
                    'lastip' => $ip_addr,
                    'lastip_signup' => $ip_addr,
                    'signedup' => time(),
                    'level' => 1,
                    'money' => $sm,
                    'user_level' => 1,
                    'energy' => 12,
                    'maxenergy' => 12,
                    'brave' => 5,
                    'maxbrave' => 5,
                    'will' => 100,
                    'maxwill' => 100,
                    'hp' => 100,
                    'maxhp' => 100,
                    'bankmoney' => -1,
                    'cybermoney' => -1,
                    'location' => 1,
                    'display_pic' => '',
                    'staffnotes' => '',
                    'voted' => '',
                    'user_notepad' => '',
                ],
            );
            $this->pdo->insert(
                'userstats',
                [
                    'userid' => $i,
                    'strength' => 10,
                    'agility' => 10,
                    'guard' => 10,
                    'labour' => 10,
                    'IQ' => 10,
                ],
            );

            if ($_POST['ref']) {
                $this->pdo->update(
                    'users',
                    ['crystals' => new EasyPlaceholder('crystals + 2')],
                    ['userid' => $_POST['ref']],
                );
                $this->func->event_add($_POST['ref'], 'For referring ' . $_POST['username'] . ' to the game, you have earned 2 valuable crystals!');
                $this->pdo->insert(
                    'referals',
                    [
                        'refREFER' => $_POST['ref'],
                        'refREFED' => $i,
                        'refTIME' => time(),
                        'refREFERIP' => $referral_ip_addr,
                        'refREFEDIP' => $ip_addr,
                    ],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => 'Registration complete',
            'redirect' => '',
        ];
    }

    /**
     * @return array|false
     */
    private function checkRegisterPostData(): array|false
    {
        $_POST['username']  = array_key_exists('username', $_POST) && !empty($_POST['username']) ? trim($_POST['username']) : null;
        $_POST['email']     = array_key_exists('email', $_POST) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? strtolower(trim($_POST['email'])) : null;
        $_POST['password']  = isset($_POST['password']) && is_string($_POST['password']) ? stripslashes($_POST['password']) : '';
        $_POST['cpassword'] = isset($_POST['cpassword']) && is_string($_POST['cpassword']) ? stripslashes($_POST['cpassword']) : '';
        if (empty($_POST['username'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'username'),
            ];
        }
        if ($this->siteSettings['regcap_on']) {
            $cap = $_SESSION['captcha'] ?? null;
            unset($_SESSION['captcha']);
            if (!$_SESSION['captcha'] || !isset($_POST['captcha']) || $cap !== $_POST['captcha']) {
                return [
                    'type' => 'error',
                    'message' => self::CAPTCHA_FAILED,
                ];
            }
        }
        if (empty($_POST['email'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'email'),
            ];
        }
        if (!in_array($_POST['gender'] ?? '', ['Male', 'Female'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'gender'),
            ];
        }
        if (empty($_POST['password']) || empty($_POST['cpassword'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'password/password confirmation'),
            ];
        }
        if ($_POST['password'] !== $_POST['cpassword']) {
            return [
                'type' => 'error',
                'message' => self::PASSWORD_MISMATCH,
            ];
        }
        return false;
    }

    /**
     * @return array|false
     */
    private function checkRegisterDataInUse(): array|false
    {
        $u_check = $this->pdo->exists(
            'SELECT COUNT(userid) FROM users WHERE ? IN (username, login_name)',
            $_POST['username'],
        );
        if ($u_check > 0) {
            return [
                'type' => 'error',
                'message' => self::USERNAME_TAKEN,
            ];
        }
        $e_check = $this->pdo->exists(
            'SELECT COUNT(userid) FROM users WHERE email = ?',
            $_POST['email'],
        );
        if ($e_check > 0) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'email'),
            ];
        }
        return false;
    }
}
