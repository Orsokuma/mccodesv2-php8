<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class ListHandler extends ListController
{

    /**
     * @param string|null $list
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     */
    public function get(?string $list = null, ?string $subRoute = null, ?string $id = null): void
    {
        if (empty($list)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list'),
            ]);
        }
        $this->workingList = $list;
        match ($subRoute) {
            'add' => $this->addListEntry((int)$id),
            'edit' => $this->editListEntry((int)$id),
            'delete' => $this->deleteListEntry((int)$id),
            default => ToroHook::fire('404'),
        };
    }

    /**
     * @param int $id
     * @return void
     */
    private function addListEntry(int $id): void
    {
        $template = file_get_contents($this->view . '/auth/lists/add.html');
        echo strtr($template, [
            '{{MENU:USERS}}' => $this->renderUserMenuOpts($id),
            '{{LIST}}' => $this->workingList,
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('list_add'),
        ]);
    }

    /**
     * @param int $id
     * @return string
     */
    private function renderUserMenuOpts(int $id): string
    {
        $rows    = $this->pdo->run(
            'SELECT userid, username FROM users WHERE userid NOT IN (SELECT target_id FROM lists WHERE adder_id = ? AND list_type = ?)',
            $this->player['userid'],
            $this->workingList,
        );
        $content = '';
        foreach ($rows as $row) {
            $content .= sprintf(
                '<option value="%u" %s>%s</option>%s',
                $row['userid'],
                $row['userid'] === $id ? 'selected' : '',
                $row['username'],
                PHP_EOL,
            );
        }
        return $content;
    }

    /**
     * @param int $id
     * @return void
     */
    private function editListEntry(int $id): void
    {
        if (empty($id)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list entry'),
            ]);
        }
        $row = $this->getListEntry($id);
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'list entry'),
            ]);
        }
        $template = file_get_contents($this->view . '/auth/lists/edit.html');
        echo strtr($template, [
            '{{LIST}}' => $this->workingList,
            '{{ID}}' => $id,
            '{{COMMENT}}' => $row['content'],
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('list_edit'),
        ]);
    }

    /**
     * @param int $id
     * @return void
     */
    private function deleteListEntry(int $id): void
    {
        if (empty($id)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list entry'),
            ]);
        }
        $row = $this->pdo->row(
            'SELECT l.*, u.username
            FROM lists AS l
            LEFT JOIN users AS u ON l.target_id = u.userid
            WHERE l.list_type = ? AND l.adder_id = ? AND l.id = ?',
            $this->workingList,
            $this->player['userid'],
            $id,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'list entry'),
            ]);
        }
        $this->displayConfirmationInterstitial([
            '{{ACTION}}' => '/list/' . $this->workingList . '/' . $id,
            '{{CONTENT}}' => 'remove ' . $row['username'] . ' from your ' . $this->workingList . '?',
        ]);
    }

    /**
     * @param string|null $list
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     */
    public function post(?string $list = null, ?string $subRoute = null, ?string $id = null): void
    {
        $this->sendResponse($this->handlePost($list, $subRoute, $id));
    }

    /**
     * @param string|null $list
     * @param string|null $subRoute
     * @param string|null $id
     * @return array
     */
    private function handlePost(?string $list = null, ?string $subRoute = null, ?string $id = null): array
    {
        if (empty($list)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list'),
            ];
        }
        $this->workingList = $list;
        $response          = match ($subRoute) {
            'add' => $this->doAddListEntry((int)$id),
            'edit' => $this->doEditListEntry((int)$id),
            'delete' => $this->doDeleteListEntry((int)$id),
            default => null,
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }
}
