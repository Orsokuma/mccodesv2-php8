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
     * @return string[]
     * @throws Throwable
     * @noinspection PhpVariableNamingConventionInspection
     */
    protected function doApplyToGang(int $gangId): array
    {
        if ($this->player['gang']) {
            return [
                'type' => 'error',
                'message' => self::ALREADY_IN_GANG,
            ];
        }
        $_POST['application'] = array_key_exists('application', $_POST) && !empty($_POST['application']) && is_string($_POST['application']) ? trim($_POST['application']) : null;
        if (empty($_POST['application'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_ENTRY, 'application'),
            ];
        }
        if (empty($gangId)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'gang'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT gangID, gangNAME FROM gangs WHERE gangID = ?',
            $gangId,
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'gang'),
            ];
        }
        $save = function () use ($gangId) {
            $this->pdo->insert(
                'applications',
                [
                    'appUSER' => $this->player['userid'],
                    'appGANG' => $gangId,
                    'appTEXT' => $_POST['application'],
                ],
            );
            $event_text = '<a href="/profile/'.$this->player['userid'].'">'.$this->player['username'].'</a> sent an application to join this gang.';
            $this->pdo->insert(
                'gangevents',
                [
                    'gevGANG' => $_GET['ID'],
                    'gevTIME' => time(),
                    'gevTEXT' => $event_text,
                ],
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => 'Your application to join '.$row['gangNAME'].' has been sent.',
        ];
    }

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
