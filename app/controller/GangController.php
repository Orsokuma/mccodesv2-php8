<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;

/**
 *
 */
class GangController extends CommonObjects
{
    protected const int GANG_CREATION_COST = 500000;

    /**
     * @param int $gangId
     * @return int
     */
    protected function getMemberCount(int $gangId): int
    {
        return (int)$this->pdo->cell(
            'SELECT COUNT(*) FROM users WHERE gang = ?',
            $gangId,
        );
    }

    /**
     * @return array
     * @throws Throwable
     */
    protected function doCreateGang(): array
    {
        if (!isset($_POST['verf']) || !$this->func->verify_csrf_code('creategang', stripslashes($_POST['verf']))) {
            return [
                'type' => 'error',
                'message' => self::CSRF_FAILURE,
            ];
        }
        if ($this->player['gang']) {
            ToroHook::fire('404');
        }
        $strs = ['name', 'description'];
        foreach ($strs as $str) {
            $_POST[$str] = array_key_exists($str, $_POST) && !empty($_POST[$str]) && is_string($_POST[$str]) ? trim($_POST[$str]) : '';
        }
        if (empty($_POST['name'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_ENTRY, 'name'),
            ];
        }
        $exists = $this->pdo->cell(
            'SELECT COUNT(*) FROM gangs WHERE LOWER(gangNAME) = ?',
            strtolower($_POST['name']),
        );
        if ($exists > 0) {
            return [
                'type' => 'error',
                'message' => sprintf(self::ALREADY_EXISTS, 'gang', 'name'),
            ];
        }
        $save = function () {
            $gang_id = $this->pdo->insert(
                'gangs',
                [
                    'gangNAME' => $_POST['name'],
                    'gangDESC' => $_POST['description'],
                    'gangPRESIDENT' => $this->player['userid'],
                    'gangVICEPRES' => $this->player['userid'],
                    'gangAMENT' => 'Welcome to ' . $_POST['name'],
                    'gangRESPECT' => 100,
                    'gangCAPACITY' => 5,
                ]
            );
            $this->pdo->update(
                'users',
                [
                    'gang' => $gang_id,
                    'money' => new EasyPlaceholder('money - ?', self::GANG_CREATION_COST),
                ],
                ['userid' => $this->player['userid']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(self::PERSONAL_OBJECT_CREATED, 'gang'),
        ];
    }
}
