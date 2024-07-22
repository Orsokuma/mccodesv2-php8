<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Factory;


/**
 *
 */
abstract class CommonObjects
{
    protected const string MISSED_REQUIRED_FIELD = 'You didn\'t enter a valid %s.';
    protected const string MISSED_REQUIRED_OBJECT = 'You didn\'t select a valid %s.';
    protected const string NOT_EXIST_OR_NOT_YOURS = 'Either that %s doesn\'t exist, or it\'s not yours.';
    protected const string OBJECT_DELETED = 'That %s has been deleted.';
    protected const string OBJECTS_DELETED = '%s have been deleted.';
    protected const string NO_OBJECT_TO_DELETE = 'You have no %s to delete.';
    protected const array UNKNOWN_ACTION_TAKEN = [
        'type' => 'error',
        'message' => 'You get a 404, and you get a 404, and you get a 404. Everyone gets a 404. Well, not everyone, but you sure did!',
    ];
    protected ?string $view = null;
    protected ?EasyDB $pdo = null;
    protected ?array $player = null;
    protected ?array $siteSettings = null;
    protected ?SiteFunctions $func = null;
    private ?array $config = null;

    public function __construct()
    {
        $this->setConfig();
        $this->setPaths();
        $this->setPdo();
        $this->setSiteSettings();
        $this->setPlayer();
        $this->setFunc();
    }

    /**
     * @param array|null $config
     * @return void
     */
    protected function setConfig(#[\SensitiveParameter] ?array $config = null): void
    {
        global $_CONFIG;
        $this->config = $config ?? $_CONFIG;
    }

    /**
     * @return void
     */
    private function setPaths(): void
    {
        $this->view = $_SERVER['DOCUMENT_ROOT'] . '/app/view';
    }

    /**
     * @param EasyDB|null $pdo
     * @return void
     */
    protected function setPdo(?EasyDB $pdo = null): void
    {
        $this->pdo = $pdo ?? Factory::fromArray([
            'mysql:host=' . $this->config['hostname'] . ';dbname=' . $this->config['database'],
            $this->config['username'],
            $this->config['password'],
        ]);
    }

    /**
     * @return void
     */
    private function setSiteSettings(): void
    {
        $this->siteSettings = [];
        $rows               = $this->pdo->run(
            'SELECT * FROM settings',
        );
        foreach ($rows as $row) {
            $this->siteSettings[$row['conf_name']] = $row['conf_value'];
            settype($this->siteSettings[$row['conf_name']], $row['data_type']);
        }
    }

    /**
     * @param array|null $player
     * @return void
     */
    protected function setPlayer(?array $player = null): void
    {
        if (!empty($player)) {
            $this->player = $player;
            return;
        }
        $userid = array_key_exists('userid', $_SESSION) && isset($_SESSION['userid']) && is_numeric($_SESSION['userid']) && (int)$_SESSION['userid'] > 0 ? (int)$_SESSION['userid'] : null;
        if (empty($userid)) {
            $this->player = null;
            return;
        }
        $this->player = $this->pdo->row(
            'SELECT u.*, us.*
            FROM users AS u
            INNER JOIN userstats AS us ON u.userid = us.userid
            WHERE u.userid = ?
            LIMIT 1',
            $userid,
        );
        if (!empty($this->player)) {
            $this->setUserdataDataTypes($this->player);
        }
        $this->player['exp_needed'] = (int)(($this->player['level'] + 1) * ($this->player['level'] + 1) * ($this->player['level'] + 1) * 2.2);
    }

    /**
     * Typecasting the player data array - tasty - to set values to have the expected data types.
     * Ideally, this engine should be updated to use db abstraction from column types.
     * Note: The match() array is not an exhaustive list, simply the types found in the default bundled dbdata.sql.
     * Note: At the time of writing, there are no overlapping column types. Consider that a growing project will likely hit this "gotcha!".
     * @param array $player
     * @return void
     */
    private function setUserdataDataTypes(array &$player): void
    {
        $rows = $this->pdo->run(
            'SELECT COLUMN_NAME AS colName, DATA_TYPE AS dataType
            FROM information_schema.COLUMNS
            WHERE COLUMNS.TABLE_SCHEMA = \'' . $this->config['database'] . '\'
              AND COLUMNS.TABLE_NAME IN (\'users\', \'userstats\', \'houses\', \'jobs\', \'jobranks\')'
        );
        $data = [];
        foreach ($rows as $row) {
            $data[$row['colName']] = match ($row['dataType']) {
                'tinyint', 'bool' => 'bool',
                'smallint', 'int', 'bigint' => 'int',
                'varchar', 'tinytext', 'smalltext', 'text', 'longtext', 'enum' => 'string',
                'float', 'decimal' => 'float',
                default => null,
            };
        }
        foreach ($player as $column => $value) {
            if (array_key_exists($column, $data) && $data[$column] !== null) {
                settype($player[$column], $data[$column]);
            }
        }
    }

    /**
     * @return void
     */
    private function setFunc(): void
    {
        $this->func = SiteFunctions::getInstance($this->pdo, $this->config, $this->player);
    }

    /**
     * @param array $response
     * @param string|null $subRoute
     * @return void
     */
    protected function sendResponse(array $response, ?string $subRoute = null): void
    {
        $this->setResponseSession($response);
        $location = '/' . ltrim(($subRoute ?? '') . (!empty($response['redirect']) ? '/' . $response['redirect'] : ''), '/');
        header('Location: https://' . $this->func->determine_game_urlbase() . $location);
        exit;
    }

    /**
     * @param array $response
     * @return void
     */
    protected function setResponseSession(array $response): void
    {
        $_SESSION[$response['type']] = $response['message'];
    }

    /**
     * @param array $response
     * @return void
     */
    protected function sendPost(array $response): void
    {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * @return void
     */
    protected function setCaptcha(): void
    {
        unset($_SESSION['captcha']);
        if ($this->siteSettings['regcap_on']) {
            /** @noinspection SpellCheckingInspection */
            $chars               = '0123456789abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ!?%^';
            $len                 = strlen($chars);
            $_SESSION['captcha'] = '';
            for ($i = 0; $i < 6; $i++) {
                $_SESSION['captcha'] .= $chars[rand(0, $len - 1)];
            }
        }
    }

    /**
     * @param array $opts
     * @return void
     */
    protected function displayConfirmationInterstitial(array $opts): void
    {
        $template = file_get_contents($this->view . '/template/confirm-action.html');
        echo strtr($template, $opts);
    }
}
