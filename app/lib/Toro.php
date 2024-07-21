<?php
declare(strict_types=1);

/**
 * PHP Web Router
 */
class Toro
{
    /**
     * @param array $routes
     * @return void
     */
    public static function serve(array $routes): void
    {
        ToroHook::fire('before_request', compact('routes'));

        $request_method = strtolower($_SERVER['REQUEST_METHOD']);
        $path_info      = '/';

        if (!empty($_SERVER['PATH_INFO'])) {
            $path_info = $_SERVER['PATH_INFO'];
        } elseif (!empty($_SERVER['ORIG_PATH_INFO']) && $_SERVER['ORIG_PATH_INFO'] !== '/index.php') {
            $path_info = $_SERVER['ORIG_PATH_INFO'];
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $path_info = str_contains($_SERVER['REQUEST_URI'], '?')
                ? strstr($_SERVER['REQUEST_URI'], '?', true)
                : $_SERVER['REQUEST_URI'];
        }

        $discovered_handler = null;
        $regex_matches      = [];

        if (isset($routes[$path_info])) {
            $discovered_handler = $routes[$path_info];
        } elseif ($routes) {
            $tokens = [
                ':alpha' => '(\w+)',
                ':number' => '(\d+)',
                ':alnum' => '([a-zA-Z0-9]+)',
                ':string' => '([a-zA-Z0-9-_]+)',
            ];
            foreach ($routes as $pattern => $handler_name) {
                $pattern = strtr($pattern, $tokens);
                if (preg_match('#^/?' . $pattern . '/?$#', $path_info, $matches)) {
                    $discovered_handler = $handler_name;
                    $regex_matches      = $matches;
                    break;
                }
            }
        }

        $result           = null;
        $handler_instance = null;

        if ($discovered_handler) {
            if (is_string($discovered_handler) && class_exists($discovered_handler)) {
                $handler_instance = new $discovered_handler();
            } elseif (is_callable($discovered_handler)) {
                $handler_instance = $discovered_handler();
            }
        }

        if ($handler_instance) {
            unset($regex_matches[0]);
            if (self::isXhrRequest() && method_exists($handler_instance, $request_method . '_xhr')) {
                header('Content-type: application/json');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                $request_method .= '_xhr';
            }

            if (method_exists($handler_instance, $request_method)) {
                ToroHook::fire('before_handler', compact('routes', 'discovered_handler', 'request_method', 'regex_matches'));
                $result = call_user_func_array([$handler_instance, $request_method], $regex_matches);
                ToroHook::fire('after_handler', compact('routes', 'discovered_handler', 'request_method', 'regex_matches', 'result'));
            } else {
                ToroHook::fire('404', compact('routes', 'discovered_handler', 'request_method', 'regex_matches'));
            }
        } else {
            ToroHook::fire('404', compact('routes', 'discovered_handler', 'request_method', 'regex_matches'));
        }

        ToroHook::fire('after_request', compact('routes', 'discovered_handler', 'request_method', 'regex_matches', 'result'));
    }

    /**
     * @return bool
     */
    private static function isXhrRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 *
 */
class ToroHook
{
    private static ToroHook $instance;

    private array $hooks = [];

    private function __construct()
    {
    }

    /**
     * @param $hook_name
     * @param $fn
     * @return void
     */
    public static function add($hook_name, $fn): void
    {
        $instance                      = self::getInstance();
        $instance->hooks[$hook_name][] = $fn;
    }

    /**
     * @return ToroHook
     */
    public static function getInstance(): ToroHook
    {
        if (empty(self::$instance)) {
            self::$instance = new ToroHook();
        }
        return self::$instance;
    }

    /**
     * @param $hook_name
     * @param $params
     * @return void
     */
    public static function fire($hook_name, $params = null): void
    {
        $instance = self::getInstance();
        if (isset($instance->hooks[$hook_name])) {
            foreach ($instance->hooks[$hook_name] as $fn) {
                call_user_func_array($fn, [&$params]);
            }
        }
    }

    /**
     * @return void
     */
    private function __clone()
    {
    }
}
