<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

global $db, $ir, $h;
require __DIR__ . '/sglobals.php';
check_access('manage_roles');

/**
 *
 */
class StaffRolesManagement
{
    private static ?self $inst = null;
    private string $viewPath = '';
    private ?EasyDB $db = null;
    private array $roles = [];

    /**
     * @param EasyDB $db
     */
    public function __construct(EasyDB $db)
    {
        $this->setViewPath();
        $this->setDb($db);
        $this->setRoles();
        $this->processAction();
    }

    /**
     * @return void
     */
    private function setViewPath(): void
    {
        $this->viewPath = __DIR__ . '/views';
    }

    /**
     * @param EasyDB $db
     * @return void
     */
    private function setDb(EasyDB $db): void
    {
        $this->db = $db;
    }

    /**
     * @return void
     */
    private function setRoles(): void
    {
        $this->roles = [];
        $get_roles   = $this->db->run(
            'SELECT * FROM staff_roles ORDER BY id'
        );
        foreach ($get_roles as $row) {
            $this->roles[$row['id']] = $row;
        }
    }

    /**
     * @return void
     */
    private function processAction(): void
    {
        $_GET['id'] = array_key_exists('id', $_GET) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
        if (array_key_exists('submit', $_POST)) {
            $response = match ($_GET['action'] ?? '') {
                'add' => $this->doUpsertRole(),
                'edit' => $this->doUpsertRole($_GET['id']),
                'remove' => $this->doRemoveRole($_GET['id']),
                'grant' => $this->doGrantRole(),
                'revoke' => $this->doRevokeRole(),
                default => null,
            };
            if (!empty($response)) {
                echo '<div class="alert alert-' . $response['type'] . '">' . $response['message'] . '</div>';
                match ($_GET['action'] ?? '') {
                    'grant' => $this->viewGrantRole(),
                    'revoke' => $this->viewRevokeRole(),
                    default => $this->roleIndex(),
                };
                return;
            }
        }
        match ($_GET['action'] ?? '') {
            'add' => $this->viewUpsertRole(),
            'edit' => $this->viewUpsertRole($_GET['id']),
            'remove' => $this->viewRemoveRole($_GET['id']),
            'grant' => $this->viewGrantRole(),
            'revoke' => $this->viewRevokeRole(),
            default => $this->roleIndex(),
        };
    }

    /**
     * @param int|null $role_id
     * @return string[]
     */
    private function doUpsertRole(?int $role_id = null): array
    {
        $permission_columns = $this->getPermCols();
        $_POST['name']      = array_key_exists('name', $_POST) ? strip_tags(trim($_POST['name'])) : null;
        if (empty($_POST['name'])) {
            return [
                'type' => 'error',
                'message' => 'You didn\'t enter a valid name',
            ];
        }
        $map = [
            'name' => $_POST['name'],
        ];
        foreach ($permission_columns as $column) {
            $_POST[$column] = array_key_exists($column, $_POST) ? strip_tags(trim($_POST[$column])) : null;
            $map[$column]   = isset($_POST[$column]) ? 1 : 0;
        }
        $dupe_check = $role_id
            ? $this->db->exists(
                'SELECT COUNT(*) FROM staff_roles WHERE LOWER(name) = ? AND id <> ?',
                strtolower($_POST['name']),
                $role_id,
            )
            : $this->db->exists(
                'SELECT COUNT(*) FROM staff_roles WHERE LOWER(name) = ?',
                strtolower($_POST['name']),
            );
        if ($dupe_check) {
            return [
                'type' => 'error',
                'message' => 'Another role with that name already exists',
            ];
        }
        $log  = $_GET['action'] . 'ed the staff role: ' . $_POST['name'];
        $save = function () use ($role_id, $map, $log) {
            if (empty($role_id)) {
                $this->db->insert(
                    'staff_roles',
                    $map,
                );
            } else {
                $this->db->update(
                    'staff_roles',
                    $map,
                    ['id' => $role_id],
                );
            }
            stafflog_add(ucfirst($log));
        };
        $this->db->tryFlatTransaction($save);
        $this->setRoles();
        return [
            'type' => 'success',
            'message' => $log,
        ];
    }

    /**
     * @return array
     */
    private function getPermCols(): array
    {
        $get_cols = $this->db->run(
            'SHOW COLUMNS FROM staff_roles'
        );
        $cols     = [];
        foreach ($get_cols as $row) {
            if (in_array($row['Field'], ['id', 'name'])) {
                continue;
            }
            $cols[] = $row['Field'];
        }
        return $cols;
    }

    /**
     * @param int|null $role_id
     * @return string[]
     */
    private function doRemoveRole(?int $role_id): array
    {
        $role = $this->getRole($role_id);
        if (empty($role)) {
            return [
                'type' => 'error',
                'message' => 'The role you selected doesn\'t exist',
            ];
        }
        if (!array_key_exists('confirm', $_POST) || !$_POST['confirm']) {
            return [
                'type' => 'error',
                'message' => 'You must confirm the desire to remove the staff role: ' . $role['name'],
            ];
        }
        $log  = 'deleted the staff role: ' . $role['name'];
        $save = function () use ($role_id, $log) {
            $this->db->delete(
                'users_roles',
                ['staff_role' => $role_id],
            );
            $this->db->delete(
                'staff_roles',
                ['id' => $role_id],
            );
            stafflog_add(ucfirst($log));
        };
        $this->db->tryFlatTransaction($save);
        $this->setRoles();
        return [
            'type' => 'success',
            'message' => 'You\'ve ' . $log,
        ];
    }

    /**
     * @param int $role_id
     * @return array|null
     */
    private function getRole(int $role_id): ?array
    {
        return $this->roles[$role_id] ?? null;
    }

    /**
     * @return string[]
     */
    private function doGrantRole(): array
    {
        $data = $this->processGrantRevokePostData();
        if ($data['type'] !== 'success') {
            return $data;
        }
        $has_role = $this->db->exists(
            'SELECT COUNT(*) FROM users_roles WHERE userid = ? AND staff_role = ?',
            $data['user']['userid'],
            $data['role']['id'],
        );
        if ($has_role) {
            return [
                'type' => 'error',
                'message' => $data['user']['username'] . ' already has the ' . $data['role']['name'] . ' role',
            ];
        }
        $log  = 'granted staff role ' . $data['role']['name'] . ' to ' . $data['user']['username'] . ' [' . $data['user']['userid'] . ']';
        $save = function () use ($data, $log) {
            $this->db->insert(
                'users_roles',
                [
                    'userid' => $data['user']['userid'],
                    'staff_role' => $data['role']['name'],
                ],
            );
            stafflog_add(ucfirst($log));
        };
        $this->db->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => 'You\'ve ' . $log,
        ];
    }

    /**
     * @return array|string[]
     */
    private function processGrantRevokePostData(): array
    {
        $nums = ['user', 'role'];
        foreach ($nums as $num) {
            $_POST[$num] = array_key_exists($num, $_POST) && is_numeric($_POST[$num]) ? (int)$_POST[$num] : null;
        }
        if (empty($_POST['user'])) {
            return [
                'type' => 'error',
                'message' => 'Invalid user given',
            ];
        }
        if (empty($_POST['role'])) {
            return [
                'type' => 'error',
                'message' => 'Invalid role given',
            ];
        }
        $user = $this->db->row(
            'SELECT userid, username FROM users WHERE userid = ?',
            $_POST['user'],
        );
        if (empty($user)) {
            return [
                'type' => 'error',
                'message' => 'User not found',
            ];
        }
        $role = $this->db->row(
            'SELECT id, name FROM staff_roles WHERE id = ?',
            $_POST['role'],
        );
        if (empty($role)) {
            return [
                'type' => 'error',
                'message' => 'Role not found',
            ];
        }
        return [
            'type' => 'success',
            'user' => $user,
            'role' => $role,
        ];
    }

    /**
     * @return array|string[]
     */
    private function doRevokeRole(): array
    {
        $data = $this->processGrantRevokePostData();
        if ($data['type'] !== 'success') {
            return $data;
        }
        $has_role = $this->db->exists(
            'SELECT COUNT(*) FROM users_roles WHERE userid = ? AND staff_role = ?',
            $data['user']['userid'],
            $data['role']['id'],
        );
        if (!$has_role) {
            return [
                'type' => 'error',
                'message' => $data['user']['username'] . ' doesn\'t have the ' . $data['role']['name'] . ' role',
            ];
        }
        $log  = 'revoked staff role ' . $data['role']['name'] . ' from ' . $data['user']['username'] . ' [' . $data['user']['userid'] . ']';
        $save = function () use ($data, $log) {
            $this->db->delete(
                'users_roles',
                [
                    'userid' => $data['user']['userid'],
                    'staff_role' => $data['role']['id'],
                ],
            );
            stafflog_add(ucfirst($log));
        };
        $this->db->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => 'You\'ve ' . $log,
        ];
    }

    /**
     * @return void
     */
    private function viewGrantRole(): void
    {
        $template = file_get_contents($this->viewPath . '/staff-roles/role-grant.html');
        echo strtr($template, [
            '{{USER-MENU}}' => $this->renderUserMenuOpts(),
            '{{ROLE-MENU}}' => $this->renderRoleMenuOpts(),
        ]);
    }

    /**
     * @return string
     */
    private function renderUserMenuOpts(): string
    {
        $ret       = '';
        $get_users = $this->db->run(
            'SELECT userid, username FROM users ORDER BY username'
        );
        foreach ($get_users as $row) {
            $ret .= '<option value="' . $row['userid'] . '">' . $row['username'] . '</option>';
        }
        return $ret;
    }

    /**
     * @return string
     */
    private function renderRoleMenuOpts(): string
    {
        $ret   = '';
        $roles = $this->getRoles();
        foreach ($roles as $id => $role) {
            $ret .= '<option value="' . $id . '">' . $role['name'] . '</a>';
        }
        return $ret;
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return void
     */
    private function viewRevokeRole(): void
    {
        $template = file_get_contents($this->viewPath . '/staff-roles/role-revoke.html');
        echo strtr($template, [
            '{{USER-MENU}}' => $this->renderRoledUserMenuOpts(),
            '{{ROLE-MENU}}' => $this->renderRoleMenuOpts(),
        ]);
    }

    /**
     * @return string
     */
    private function renderRoledUserMenuOpts(): string
    {
        $ret       = '';
        $get_users = $this->db->run(
            'SELECT userid, username FROM users WHERE userid IN (SELECT userid FROM users_roles WHERE staff_role > 0) ORDER BY username'
        );
        foreach ($get_users as $row) {
            $ret .= '<option value="' . $row['userid'] . '">' . $row['username'] . '</option>';
        }
        return $ret;
    }

    /**
     * @return void
     */
    private function roleIndex(): void
    {
        $template = file_get_contents($this->viewPath . '/staff-roles/role-index.html');
        $roles    = $this->renderRoles();
        echo strtr($template, [
            '{{STAFF-ROLES}}' => $roles,
        ]);
    }

    /**
     * @return string
     */
    private function renderRoles(): string
    {
        $ret      = '';
        $template = file_get_contents($this->viewPath . '/staff-roles/role-index-entry.html');
        $roles    = $this->getRoles();
        foreach ($roles as $id => $role) {
            $ret .= strtr($template, [
                '{{ROLE-ID}}' => $id,
                '{{ROLE-NAME}}' => $role['name'],
                '{{ROLE-PERMISSIONS}}' => $this->expandPermissions($role),
            ]);
        }
        return $ret;
    }

    /**
     * @param array $role
     * @return string
     */
    private function expandPermissions(array $role): string
    {
        $has = [];
        foreach ($role as $key => $value) {
            if (in_array($key, ['id', 'name'])) {
                continue;
            }
            if ($key === 'administrator' && $value) {
                $has[] = '<em style="color:#008800;">all</em>';
                break;
            }
            if ($value) {
                $has[] = ucwords(str_replace('_', ' ', $key));
            }
        }
        return $has ? implode(', ', $has) : '<em style="color: #ff0000;">none</em>';
    }

    /**
     * @param int|null $role_id
     * @return void
     */
    private function viewUpsertRole(?int $role_id = null): void
    {
        if ($_GET['action'] === 'edit' && !$role_id) {
            $this->displayRoleSelectionMenu();
            return;
        }
        $role     = $role_id ? $this->getRole($role_id) : null;
        $template = file_get_contents($this->viewPath . '/staff-roles/role-upsert.html');
        echo strtr($template, [
            '{{ROLE-ID}}' => $role['id'] ?? '',
            '{{ROLE-NAME}}' => $role['name'] ?? '',
            '{{ROLE-PERMISSIONS}}' => $this->upsertRolePermissionsForm($role),
            '{{BTN-ACTION}}' => $role_id ? 'Edit' : 'Add',
            '{{FORM-ACTION}}' => $role_id ? 'staff_roles.php?action=edit&id=' . $role_id : 'staff_roles.php?action=add',
        ]);
    }

    /**
     * @return void
     */
    private function displayRoleSelectionMenu(): void
    {
        $template = file_get_contents($this->viewPath . '/staff-roles/role-selection-menu.html');
        echo strtr($template, [
            '{{FORM-LOCATION}}' => 'staff_roles.php',
            '{{FORM-ACTION}}' => $_GET['action'],
            '{{ROLES}}' => $this->renderRoleMenuOpts(),
        ]);
    }

    /**
     * @param array|null $role
     * @return string
     */
    private function upsertRolePermissionsForm(?array $role): string
    {
        $permission_columns = $this->getPermCols();
        $ret                = '';
        foreach ($permission_columns as $column) {
            $ret .= '
                <div style="display: inline-block; width: 33%;">
                    <label for="' . $column . '">
                        <input type="checkbox" name="' . $column . '" id="' . $column . '" value="1"' . ($role && $role[$column] ? ' checked' : '') . '>
                        ' . ucwords(str_replace('_', ' ', $column)) . '
                    </label>
                </div>
            ';
        }
        return $ret;
    }

    /**
     * @param int|null $role_id
     * @return void
     */
    private function viewRemoveRole(?int $role_id = null): void
    {
        if (!$role_id) {
            $this->displayRoleSelectionMenu();
            return;
        }
        $role     = $this->getRole($role_id);
        $template = file_get_contents($this->viewPath . '/staff-roles/role-remove.html');
        echo strtr($template, [
            '{{ROLE-NAME}}' => $role['name'],
            '{{FORM-ACTION}}' => 'staff_roles.php?action=remove&id=' . $role['id'],
        ]);
    }

    /**
     * @param EasyDB $db
     * @return self|null
     */
    public static function getInstance(EasyDB $db): ?self
    {
        if (self::$inst === null) {
            self::$inst = new self($db);
        }
        return self::$inst;
    }
}

$module = StaffRolesManagement::getInstance($db);
$h->endpage();
