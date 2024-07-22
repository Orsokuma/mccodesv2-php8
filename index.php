<?php
declare(strict_types=1);
define('PAGE_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/app/lib/global_func.php';
require __DIR__ . '/app/lib/Toro.php';
session_name('MCCSID');
session_start();
const MONO_ON = true;
spl_autoload_register(function ($className) {
    $paths = [
        __DIR__ . '/app/lib/',
        __DIR__ . '/app/controller/',
        __DIR__ . '/app/model/',
    ];
    foreach ($paths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 *
 */
class Index extends CommonObjects
{
    private static ?self $inst = null;
    private array $routes = [];

    public function __construct()
    {
        parent::__construct();
        if (defined('FORCE_SSL') && FORCE_SSL) {
            $this->checkSSL();
        }
        $this->serveSite();
    }

    /**
     * @return void
     */
    private function checkSSL(): void
    {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            $location = 'https://'.$this->func->determine_game_urlbase();
            if (!empty($this->siteSettings['ssl_port']) && $this->siteSettings['ssl_port'] !== 443) {
                $location .= ':' . $this->siteSettings['ssl_port'];
            }
            header('Location: '.$location);
            exit;
        }
    }

    /**
     * @return void
     */
    private function serveSite(): void
    {
        $this->populateRoutes();
        $versions = $this->getLatestVersionTimes();

        $common_replacements = [
            '{{GAME_NAME}}' => $this->siteSettings['game_name'],
            '{{GAME_DESCRIPTION}}' => $this->siteSettings['game_description'],
            '{{GAME_OWNER}}' => $this->siteSettings['game_owner'],
            '{{YEAR}}' => date('Y'),
        ];

        $template_replacements = [
            '{{CSS_VERSION_NONAUTH}}' => $versions['css']['nonauth'],
            '{{CSS_VERSION_GLOBAL}}' => $versions['css']['global'],
            '{{JS_VERSION_NONAUTH}}' => $versions['js']['nonauth'],
        ];

        // Render the route serve's content to the buffer
        ob_start();
        ToroHook::add('404', function () {
            require __DIR__ . '/app/view/template/404.html';
        });
        ToroHook::add('403', function () {
            require __DIR__ . '/app/view/template/403.html';
        });
        ToroHook::add('jail_hosp', function () {
            require __DIR__ . '/app/view/template/403-jail-hosp.html';
        });
        Toro::serve($this->routes);
        $main_content = ob_get_clean();
        $main_content = strtr($main_content, $common_replacements);
        // "Inject" main content into template file
        $template = $this->getTemplate();
        $content  = strtr($template, $template_replacements + $common_replacements);
        // Get {success,info,warning,error} alerts
        $alerts = $this->getAlerts();
        // Inject statuses into template
        $content = str_replace('{{STATUS}}', $this->getStatuses(), $content);
        // Inject alerts into template
        $content = str_replace('{{ALERTS}}', $alerts, $content);
        // Render main menu
        if (!empty($this->player)) {
            if ($this->player['fedjail'] > 0) {
                $row         = $this->pdo->row(
                    'SELECT * FROM fedjail WHERE fed_userid = ?',
                    $this->player['userid'],
                );
                $fed_message = 'You have been put in the ' . $this->siteSettings['game_name'] . ' Federal Jail for ' . $row['fed_days'] . ' day' . ($row['fed_days'] === 1 ? '' : 's') . '.<br>Reason: ' . $row['fed_reason'];
                $content     = strtr($content, [
                    '{{SIDEBAR}}' => '',
                    '{{MAIN_CONTENT}}' => '<span class="text-danger text-bold">' . $fed_message . '</span>',
                ]);
            } elseif ((!defined('MENU_HIDE') || !MENU_HIDE)) {
                $this->playerUpdates();
                $content = str_replace('{{MAIN_CONTENT}}', $main_content, strtr($content, $common_replacements));
                // Conditionally render the menu
                $content = str_replace('{{SIDEBAR}}', $this->renderSidebar(), $content);
            }
        } else {
            $content = str_replace('{{MAIN_CONTENT}}', $main_content, strtr($content, $common_replacements));
        }
        // Set our kinda-mostly-maybe-a-little-bit "end" time and render in
        $page_load = microtime(true) - PAGE_START;
        $content   = str_replace('{{PAGE_LOAD}}', number_format($page_load, 3) . 's', $content);
        // Finally, throw it all at the user!
        echo $content;
    }

    /**
     * @return void
     */
    private function populateRoutes(): void
    {
        if (empty($this->player)) {
            $this->populateRoutesNonAuth();
        } else {
            $this->populateRoutesAuth();
        }
        $this->populateRoutesCommon();
    }

    /**
     * @return void
     */
    private function populateRoutesNonAuth(): void
    {
        $this->routes = [
            '/' => 'AuthHandler',
            '/:alpha' => 'AuthHandler',
        ];
    }

    /**
     * @return void
     */
    private function populateRoutesAuth(): void
    {
        $this->routes = [
            '/' => 'HomeHandler',
            '/auth/:alpha' => 'AuthHandler',
            '/welcome' => 'HomeHandler',
            '/explore' => 'ExploreHandler',
            '/announcements' => 'AnnouncementsHandler',
            '/announcements/:number' => 'AnnouncementsHandler',
            '/crimes' => 'CrimesHandler',
            '/crimes/:number' => 'CrimesHandler',
            '/events' => 'EventsHandler',
            '/events/:string' => 'EventsHandler',
            '/events/:string/:number' => 'EventsHandler',
            '/mailbox' => 'MailboxHandler',
            '/mailbox/:string' => 'MailboxHandler',
            '/mailbox/:string/:number' => 'MailboxHandler',
            '/preferences' => 'PreferencesHandler',
            '/preferences/:alpha' => 'PreferencesHandler',
        ];
        if (!$this->player['jail'] && !$this->player['hospital']) {
            $this->routes += [
                '/explore' => 'ExploreHandler',
                '/bank' => 'BankHandler',
                '/bank/:string' => 'BankHandler',
            ];
        }
    }

    /**
     * @return void
     */
    private function populateRoutesCommon(): void
    {
        $routes = [
            '/auth/:alpha' => 'AuthHandler',
            '/rules' => 'RulesHandler',
            '/privacy-policy' => 'PrivacyPolicyHandler',
            '/terms-conditions' => 'TermsConditionsHandler',
        ];
        foreach ($routes as $route => $class) {
            if (!array_key_exists($route, $this->routes)) {
                $this->routes[$route] = $class;
            }
        }
    }

    /**
     * @return array[]
     */
    private function getLatestVersionTimes(): array
    {
        return [
            'css' => [
                'game' => filemtime(__DIR__ . '/app/view/assets/css/game.css'),
                'nonauth' => filemtime(__DIR__ . '/app/view/assets/css/nonauth.css'),
                'global' => filemtime(__DIR__ . '/app/view/assets/css/global.css'),
            ],
            'js' => [
                'nonauth' => filemtime(__DIR__ . '/app/view/assets/js/nonauth.js'),
                'staff' => filemtime(__DIR__ . '/app/view/assets/js/staff.js'),
            ],
        ];
    }

    /**
     * @return string
     */
    private function getTemplate(): string
    {
        return file_get_contents($this->view . '/template/' . (empty($this->player) ? 'non-' : '') . 'auth.html');
    }

    /**
     * @return string
     */
    private function getAlerts(): string
    {
        $response    = '';
        $template    = file_get_contents($this->view . '/template/alert.html');
        $alerts      = ['error', 'warning', 'info', 'success'];
        $alert_title = function ($type) {
            return $type === 'info' ? 'Information' : ucfirst($type);
        };
        foreach ($alerts as $alert) {
            if (array_key_exists($alert, $_SESSION)) {
                $alert_type = $alert === 'error' ? 'danger' : $alert;
                $response   .= strtr($template, [
                    '{{TYPE}}' => $alert_type,
                    '{{TITLE}}' => $alert_title($alert),
                    '{{MESSAGE}}' => $_SESSION[$alert],
                ]);
                unset($_SESSION[$alert]);
            }
        }
        return $response;
    }

    /**
     * @return string
     */
    private function getStatuses(): string
    {
        if (empty($this->player)) {
            return '';
        }
        $statuses = [];
        if ($this->player['hospital']) {
            $statuses[] = '<strong>NB:</strong> You are currently in hospital for ' . $this->player['hospital'] . ' minutes.';
        }
        if ($this->player['jail']) {
            $statuses[] = '<strong>NB:</strong> You are currently in jail for ' . $this->player['jail'] . ' minutes.';
        }
        $statuses[] = '<a href="/donator" class="text-bold">Donate to ' . $this->siteSettings['game_name'] . ' now for game benefits!</a>';
        return '<div class="py-1 mb-2">' . implode('<br>', $statuses) . '</div>';
    }

    /**
     * @return void
     */
    private function playerUpdates(): void
    {
        $this->pdo->update(
            'users',
            [
                'lastip' => $_SERVER['REMOTE_ADDR'],
                'laston' => $_SERVER['REQUEST_TIME'],
            ],
            ['userid' => $this->player['userid']],
        );
    }

    /**
     * @return string
     */
    private function renderSidebar(): string
    {
        return '<div class="col-sm-3">' . $this->renderUserData() . $this->renderMenu() . '</div>';
    }

    /**
     * @return string
     */
    private function renderUserData(): string
    {
        $content      = file_get_contents($this->view . '/template/user-data.html');
        $donator_icon = '';
        $username     = $this->player['username'];
        if ($this->player['donatordays']) {
            $username     = '<span style="color: #ff0000;">' . $this->player['username'] . '</span>';
            $title_alt    = 'Donator: ' . $this->player['donatordays'] . ' Day' . ($this->player['donatordays'] === 1 ? '' : 's') . ' Left';
            $donator_icon = '<img src="/app/view/assets/images/donator.gif" title="' . $title_alt . '" alt="' . $title_alt . '">';
        }
        $replacements = [
            '{{USERNAME_FORMATTED}}' => $username,
            '{{USERID}}' => $this->player['userid'],
            '{{ICON:DONATOR}}' => $donator_icon,
            '{{MONEY}}' => $this->func->money_formatter($this->player['money']),
            '{{CRYSTALS}}' => number_format($this->player['crystals']),
            '{{LEVEL}}' => number_format($this->player['level']),
        ];
        $this->replaceTemplateUserData($replacements);
        return strtr($content, $replacements);
    }

    /**
     * @param array $replacements
     * @return void
     */
    private function replaceTemplateUserData(array &$replacements): void
    {
        $keys = ['energy', 'will', 'brave', 'hp'];
        foreach ($keys as $key) {
            $upper_key              = strtoupper($key);
            $stat                   = '{{' . $upper_key . '}}';
            $max                    = '{{' . $upper_key . '_MAX}}';
            $percent                = '{{' . $upper_key . '_PERCENT}}';
            $max_key                = 'max' . $key;
            $perc                   = $this->player[$key] > 0 && $this->player[$max_key] > 0 ? (int)($this->player[$key] / $this->player[$max_key] * 100) : 0;
            $replacements[$stat]    = $this->player[$key];
            $replacements[$max]     = $this->player[$max_key];
            $replacements[$percent] = min($perc, 100);
        }
        $replacements['{{EXP}}']         = $this->player['exp'];
        $replacements['{{EXP_MAX}}']     = $this->player['exp_needed'];
        $replacements['{{EXP_PERCENT}}'] = $this->player['exp'] > 0 && $this->player['exp_needed'] > 0 ? (int)($this->player['exp'] / $this->player['exp_needed'] * 100) : 0;
    }

    /**
     * @return string
     */
    private function renderMenu(): string
    {
        global $player;
        $player             = $this->player;
        $player['is_staff'] = $this->func->is_staff();
        $menu               = defined('STAFF') && STAFF ? 'staff-menu' : 'menu';
        ob_start();
        require_once $this->view . '/template/' . $menu . '.php';
        $content = ob_get_clean();
        if (defined('STAFF') && STAFF) {
            $replacements = [
                '{{STAFF}}' => $this->renderOnlineStaff(),
            ];
        } else {
            $replacements = [
                '{{MAIL_COUNT}}' => number_format($this->player['new_mail']),
                '{{EVENTS_COUNT}}' => number_format($this->player['new_events']),
                '{{ANNOUNCEMENTS_COUNT}}' => $this->player['new_announcements'],
                '{{MAIL_BOLD}}' => $this->player['new_mail'] > 0 ? 'class="text-bold"' : '',
                '{{EVENTS_BOLD}}' => $this->player['new_events'] > 0 ? 'class="text-bold"' : '',
                '{{ANNOUNCEMENTS_BOLD}}' => $this->player['new_announcements'] > 0 ? 'class="text-bold"' : '',
                '{{HOSPITAL_COUNT}}' => number_format($this->siteSettings['hospital_count']),
                '{{JAIL_COUNT}}' => number_format($this->siteSettings['jail_count']),
                '{{DATE}}' => date('F jS, Y'),
                '{{TIME}}' => date('g:i:sa'),
            ];
            $content      = $player['is_staff']
                ? str_replace('{{STAFF}}', $this->renderOnlineStaff(), $content)
                : str_replace('{{STAFF}}', '', $content);
        }
        return strtr($content, $replacements);
    }

    /**
     * @return string
     */
    private function renderOnlineStaff(): string
    {
        $staff        = '';
        $online_staff = $this->func->get_online_staff();
        foreach ($online_staff as $r) {
            $staff .= '<a href="/profile/' . $r['userid'] . '">' . $r['username'] . '</a> (' . $this->func->datetime_parse($r['laston']) . ')<br />';
        }
        return $staff;
    }

    /**
     * @return self|null
     */
    public static function getInst(): ?self
    {
        if (self::$inst === null) {
            self::$inst = new self();
        }
        return self::$inst;
    }
}

$site = Index::getInst();
