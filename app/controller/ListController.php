<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class ListController extends CommonObjects
{
    protected ?string $workingList = null;

    /**
     * @return array
     */
    protected function doAddListEntry(): array
    {
        $nums = ['user1', 'user2'];
        foreach ($nums as $num) {
            $_POST[$num] = array_key_exists($num, $_POST) && (int)$_POST[$num] > 0 ? (int)$_POST[$num] : 0;
        }
        $_POST['user'] = $_POST['user1'] ?? $_POST['user2'];
        if (empty($_POST['user'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list entry'),
            ];
        }
        if ($_POST['user'] === $this->player['userid']) {
            return [
                'type' => 'error',
                'message' => 'You can\'t add yourself to your ' . $this->workingList . ' list',
            ];
        }
        $target = $this->pdo->cell(
            'SELECT username FROM users WHERE userid = ?',
            $_POST['user'],
        );
        if (empty($target)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'user'),
            ];
        }
        $_POST['comment'] = array_key_exists('comment', $_POST) && !empty($_POST['comment']) ? trim($_POST['comment']) : '';
        $dupe             = $this->pdo->exists(
            'SELECT COUNT(*) FROM lists WHERE list_type = ? AND adder_id = ? AND target_id = ?',
            $this->workingList,
            $this->player['userid'],
            $_POST['user'],
        );
        if ($dupe) {
            return [
                'type' => 'info',
                'message' => 'You\'ve already added ' . $target . ' to your ' . $this->workingList . ' list',
            ];
        }
        $this->pdo->insert(
            'lists',
            [
                'adder_id' => $this->player['userid'],
                'target_id' => $_POST['user'],
                'list_type' => $this->workingList,
                'content' => $_POST['comment'],
            ]
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_ADDED, 'list entry'),
        ];
    }

    /**
     * @param int $id
     * @return array
     */
    protected function doEditListEntry(int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list entry'),
            ];
        }
        $row = $this->getListEntry($id);
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'list entry'),
            ];
        }
        $_POST['comment'] = array_key_exists('comment', $_POST) && !empty($_POST['comment']) ? trim($_POST['comment']) : '';
        $this->pdo->update(
            'lists',
            ['content' => $_POST['comment']],
            ['id' => $id],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_EDITED, 'list entry'),
        ];
    }

    /**
     * @param int $id
     * @return array|null
     */
    protected function getListEntry(int $id): ?array
    {
        return $this->pdo->row(
            'SELECT * FROM lists WHERE list_type = ? AND adder_id = ? AND id = ?',
            $this->workingList,
            $this->player['userid'],
            $id,
        );
    }

    /**
     * @param int $id
     * @return array
     */
    protected function doDeleteListEntry(int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'list entry'),
            ];
        }
        $row = $this->getListEntry($id);
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'list entry'),
            ];
        }
        $this->pdo->delete(
            'lists',
            ['id' => $id],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_DELETED, 'list entry'),
        ];
    }
}
