<?php
declare(strict_types=1);

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class EventsController extends CommonObjects
{
    private const string EVENTS_DELETED = 'All %s of your events have been deleted.';

    protected function deleteOne(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'event'),
            ];
        }
        $recipient = $this->pdo->cell(
            'SELECT evUSER FROM events WHERE evID = ? AND evUSER = ?',
            $id, $this->player['userid'],
        );
        if (!$recipient) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NO_EXIST_OR_NOT_YOURS, 'event'),
            ];
        }
        $this->pdo->delete(
            'events',
            [
                'evID' => $id,
                'evUSER' => $this->player['userid'],
            ]
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_DELETED, 'event'),
        ];
    }
    protected function deleteAll(): array
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
        $deleted = $this->pdo->delete(
            'events',
            ['evUSER' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::EVENTS_DELETED, number_format($deleted)),
        ];
    }
}
