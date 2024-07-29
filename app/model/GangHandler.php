<?php
declare(strict_types=1);

/**
 *
 */
class GangHandler extends GangController
{
    /**
     * @param string|null $subRoute
     * @param string|null $gangId
     * @return void
     */
    public function get(?string $subRoute = null, ?string $gangId = null): void
    {
        match ($subRoute) {
            'create' => $this->displayCreateGang(),
            'view' => $this->viewGang((int)$gangId),
            'members' => $this->viewGangMembers((int)$gangId),
            'apply' => $this->viewGangApplicationForm((int)$gangId),
            default => $this->viewGangList(),
        };
    }

    /**
     * @return void
     */
    private function displayCreateGang(): void
    {
        $template = file_get_contents($this->view . '/auth/gangs/create.html');
        echo strtr($template, [
            '{{GANG_CREATION_COST}}' => $this->func->money_formatter(self::GANG_CREATION_COST),
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('creategang'),
        ]);
    }

    /**
     * @param int $gangId
     * @return void
     */
    private function viewGang(int $gangId): void
    {
        if (empty($gangId)) {
            ToroHook::fire('404');
        }
        $row = $this->pdo->row(
            'SELECT gangID, gangNAME, gangDESC, gangRESPECT, gangPREF, gangPRESIDENT, gangVICEPRES FROM gangs WHERE gangID = ?',
            $gangId,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'gang'),
            ]);
        }
        $gang_staff   = $this->pdo->row(
            'SELECT g.gangPRESIDENT, g.gangVICEPRES, u1.username AS presidentUsername, u2.username AS viceUsername
            FROM gangs AS g
            LEFT JOIN users AS u1 ON g.gangPRESIDENT = u1.userid
            LEFT JOIN users AS u2 ON g.gangVICEPRES = u2.userid
            WHERE g.gangID = ?',
            $row['gangID'],
        );
        $member_count = $this->getMemberCount($row['gangID']);
        $template     = file_get_contents($this->view . '/auth/gangs/view.html');
        echo strtr($template, [
            '{{ID}}' => $row['gangID'],
            '{{NAME}}' => $row['gangNAME'],
            '{{PRESIDENT_ID}}' => $row['gangPRESIDENT'],
            '{{PRESIDENT_NAME}}' => $gang_staff['presidentUsername'],
            '{{VICE_PRESIDENT_ID}}' => $row['gangVICEPRES'],
            '{{VICE_PRESIDENT_NAME}}' => $gang_staff['viceUsername'],
            '{{MEMBER_COUNT}}' => number_format($member_count),
            '{{RESPECT}}' => $row['gangRESPECT'],
            '{{DESCRIPTION}}' => nl2br($row['gangDESC']),
            '{{TAG}}' => $row['gangPREF'],
        ]);
    }

    /**
     * @return void
     */
    private function viewGangList(): void
    {
        $template = file_get_contents($this->view . '/auth/gangs/list.html');
        $entry    = file_get_contents($this->view . '/auth/gangs/list-entry.html');
        $rows     = $this->pdo->run(
            'SELECT g.gangID, g.gangNAME, g.gangRESPECT, g.gangCAPACITY, u.username
            FROM gangs AS g
            INNER JOIN users AS u ON g.gangPRESIDENT = u.userid
            ORDER BY g.gangRESPECT DESC'
        );
        $content  = '';
        foreach ($rows as $row) {
            $member_count = $this->getMemberCount($row['gangID']);
            $content      .= strtr($entry, [
                '{{NAME}}' => $row['gangNAME'],
                '{{MEMBERS}}' => number_format($member_count),
                '{{CAPACITY}}' => $row['gangCAPACITY'],
                '{{PRESIDENT}}' => $row['username'],
                '{{RESPECT}}' => $row['gangRESPECT'],
            ]);
        }
        echo strtr($template, [
            '{{GANG_LIST}}' => $content,
        ]);
    }

    /**
     * @param int $gangId
     * @return void
     */
    private function viewGangMembers(int $gangId): void
    {
        if (empty($gangId)) {
            ToroHook::fire('404');
        }
        $row = $this->pdo->row(
            'SELECT gangID, gangNAME FROM gangs WHERE gangID = ?',
            $gangId,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'gang'),
            ]);
        }
        $template = file_get_contents($this->view . '/auth/gangs/members.html');
        $entry    = file_get_contents($this->view . '/auth/gangs/members-entry.html');
        $members  = $this->pdo->run(
            'SELECT userid, username, level, daysingang FROM users WHERE gang = ? ORDER BY daysingang, level DESC',
            $row['gangID'],
        );
        $content  = '';
        foreach ($members as $member) {
            $content .= strtr($entry, [
                '{{NAME}}' => $member['username'],
                '{{LEVEL}}' => $member['level'],
                '{{MEMBERSHIP_DURATION}}' => number_format($member['daysingang']),
            ]);
        }
        echo strtr($template, [
            '{{NAME}}' => $row['gangNAME'],
            '{{MEMBERS}}' => $content,
        ]);
    }

    /**
     * @param int $gangId
     * @return void
     */
    private function viewGangApplicationForm(int $gangId): void
    {
        if ($this->player['gang']) {
            $this->sendResponse([
                'type' => 'error',
                'message' => self::ALREADY_IN_GANG,
            ]);
        }
        if (empty($gangId)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'gang'),
            ]);
        }
        $row = $this->pdo->row(
            'SELECT gangID, gangNAME FROM gangs WHERE gangID = ?',
            $gangId,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'gang'),
            ]);
        }
        $template = file_get_contents($this->view . '/auth/gangs/apply.html');
        echo strtr($template, [
            '{{ID}}' => $row['gangID'],
            '{{NAME}}' => $row['gangNAME'],
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('apply'),
        ]);
    }

    /**
     * @param string|null $subRoute
     * @param string|null $gangId
     * @return void
     * @throws Throwable
     */
    public function post(?string $subRoute = null, ?string $gangId = null): void
    {
        $this->sendResponse($this->handlePost($subRoute, $gangId), 'gangs');
    }

    /**
     * @param string|null $subRoute
     * @param string|null $gangId
     * @return array
     * @throws Throwable
     */
    private function handlePost(?string $subRoute = null, ?string $gangId = null): array
    {
        $response = match ($subRoute) {
            'create' => $this->doCreateGang(),
            'apply' => $this->doApplyToGang((int)$gangId),
            default => null,
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }
}
