<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

$nohdr = true;
global $db;
require __DIR__ . '/sglobals.php';
if (!is_staff()) {
    echo '403: Forbidden access.';
    exit;
}

/**
 *
 */
class StaffAPI
{
    private static ?self $inst = null;
    private ?EasyDB $db = null;

    /**
     * @param EasyDB|null $db
     */
    public function __construct(?EasyDB $db)
    {
        $this->setDb($db);
        $this->processIncoming();
    }

    /**
     * @param EasyDB|null $db
     * @return void
     */
    private function setDb(?EasyDB $db): void
    {
        $this->db = $db;
    }

    /**
     * @return void
     */
    private function processIncoming(): void
    {
        $_GET['id'] = array_key_exists('id', $_GET) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
        $data       = [
            'type' => 'error',
            'message' => 'No options given',
        ];
        if (array_key_exists('get', $_GET)) {
            $data = $this->processGet($_GET['get'], $_GET['id']);
        }
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * @param string $get
     * @param int $id
     * @return array|string[]
     */
    private function processGet(string $get, int $id): array
    {
        return match ($get) {
            'non-user-roles' => $this->getNonUserRoles($id),
            'user-roles' => $this->getUserRoles($id),
            default => [
                'type' => 'error',
                'message' => 'Invalid "get" value',
            ],
        };
    }

    /**
     * @param int $target_id
     * @return array
     */
    private function getNonUserRoles(int $target_id): array
    {
        $get_non_roles = $this->db->run(
            'SELECT id, name FROM staff_roles WHERE id NOT IN (SELECT staff_role FROM users_roles WHERE userid = ' . $target_id . ') ORDER BY name, id'
        );
        $data          = [];
        foreach ($get_non_roles as $role) {
            $data[] = $role;
        }
        return [
            'type' => 'success',
            'message' => 'See data key',
            'data' => $data,
        ];
    }

    /**
     * @param int $target_id
     * @return array
     */
    private function getUserRoles(int $target_id): array
    {
        $get_roles = $this->db->run(
            'SELECT id, name FROM staff_roles WHERE id IN (SELECT staff_role FROM users_roles WHERE userid = ?) ORDER BY name, id',
            $target_id,
        );
        $data      = [];
        foreach ($get_roles as $role) {
            $data[] = $role;
        }
        return [
            'type' => 'success',
            'message' => 'See data key',
            'data' => $data,
        ];
    }

    /**
     * @param EasyDB|null $db
     * @return self|null
     */
    public static function getInstance(?EasyDB $db): ?self
    {
        if (self::$inst === null) {
            self::$inst = new self($db);
        }
        return self::$inst;
    }
}

$api = StaffAPI::getInstance($db);
