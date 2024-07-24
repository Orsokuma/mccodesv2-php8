<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class EventsController extends CommonObjects
{
    private const string EVENTS_DELETED = 'All %s of your events have been deleted.';

    /**
     * @param int|null $id
     * @return array
     * @throws Throwable
     */
    protected function doDeleteOne(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_SELECTION, 'event'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT evID, evUSER, evREAD FROM events WHERE evID = ? AND evUSER = ?',
            $id, $this->player['userid'],
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'event'),
            ];
        }
        $save = function () use ($row) {
            $this->pdo->delete(
                'events',
                [
                    'evID' => $row['evID'],
                    'evUSER' => $this->player['userid'],
                ]
            );
            if (!$row['evREAD']) {
                $this->pdo->update(
                    'users',
                    ['new_events' => new EasyPlaceholder('GREATEST(new_events - 1, 0)')],
                    ['userid' => $this->player['userid']],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);

        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_DELETED, 'event'),
        ];
    }

    /**
     * @return array
     * @throws Throwable
     */
    protected function doDeleteAll(): array
    {
        $count = $this->pdo->cell(
            'SELECT COUNT(*) FROM events WHERE evUSER = ?',
            $this->player['userid'],
        );
        if (!$count) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NO_OBJECT_TO_DELETE, 'events'),
            ];
        }
        $deleted = 0;
        $save = function () use ($count, &$deleted) {
            $deleted = $this->pdo->delete(
                'events',
                ['evUSER' => $this->player['userid']],
            );
            if ($this->player['new_events']) {
                $this->pdo->update(
                    'users',
                    ['new_events' => 0],
                    ['userid' => $this->player['userid']],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(self::EVENTS_DELETED, number_format($deleted)),
        ];
    }
}
