<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class MailboxController extends CommonObjects
{
    private array $mailSettings = [
        'subject_max_length' => 50,
        'message_max_length' => 250,
    ];

    /**
     * @return array
     */
    protected function doSendMessage(): array
    {
        $strs = ['subject', 'message'];
        $nums = ['user1', 'user2', 'user3'];
        foreach ($strs as $str) {
            $_POST[$str] = array_key_exists($str, $_POST) && $_POST[$str] !== '' ? trim($_POST[$str]) : '';
        }
        foreach ($nums as $num) {
            $_POST[$num] = array_key_exists($num, $_POST) && is_numeric($_POST[$num]) && (int)$_POST[$num] > 0 ? $_POST[$num] : null;
        }
        $_POST['user'] = $_POST['user1'] ?? $_POST['user2'] ?? $_POST['user3'];
        if (empty($_POST['message'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_ENTRY, 'message'),
            ];
        }
        if (strlen($_POST['subject']) > $this->mailSettings['subject_max_length']) {
            return [
                'type' => 'error',
                'messages' => 'Subjects are limited to ' . $this->mailSettings['subject_max_length'] . ' characters.',
            ];
        }
        if (strlen($_POST['message']) > $this->mailSettings['message_max_length']) {
            return [
                'type' => 'error',
                'messages' => 'Messages are limited to ' . $this->mailSettings['message_max_length'] . ' characters.',
            ];
        }
        if (empty($_POST['user'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::INVALID_ENTRY, 'recipient'),
            ];
        }
        $recipient = $this->pdo->cell(
            'SELECT username FROM users WHERE userid = ?',
            $_POST['user'],
        );
        if (empty($recipient)) {
            return [
                'type' => 'error',
                'message' => 'Your intended recipient doesn\'t exist.',
            ];
        }
        $save = function () {
            $this->pdo->insert(
                'mail',
                [
                    'mail_from' => $this->player['userid'],
                    'mail_to' => $_POST['user'],
                    'mail_time' => time(),
                    'mail_subject' => $_POST['subject'],
                    'mail_text' => $_POST['message'],
                ],
            );
            $this->pdo->update(
                'users',
                ['new_mail' => new EasyPlaceholder('new_mail + 1')],
                ['userid' => $_POST['user']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => 'Your message has been sent to '.$recipient,
        ];
    }

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
                'message' => sprintf(self::INVALID_SELECTION, 'message'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT mail_id, mail_read FROM mail WHERE mail_to = ? AND mail_id = ? LIMIT 1',
            $this->player['userid'],
            $id,
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'message'),
            ];
        }
        $save = function () use ($row) {
            $this->pdo->delete(
                'mail',
                ['mail_id' => $row['mail_id']],
            );
            if (!$row['mail_read']) {
                $this->pdo->update(
                    'users',
                    ['new_mail' => new EasyPlaceholder('GREATEST(0, new_mail - 1')],
                    ['userid' => $this->player['userid']],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_DELETED, 'message'),
        ];
    }

    /**
     * @return array
     */
    protected function doDeleteRead(): array
    {
        $count = $this->pdo->cell(
            'SELECT COUNT(*) FROM mail WHERE mail_to = ? AND mail_read = 1',
            $this->player['userid'],
        );
        if (!$count) {
            return [
                'type' => 'info',
                'message' => sprintf(self::NO_OBJECT_TO_DELETE, 'messages'),
            ];
        }
        $this->pdo->delete(
            'mail',
            [
                'mail_to' => $this->player['userid'],
                'mail_read' => 1,
            ]
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECTS_DELETED, number_format($count) . ' read messages'),
        ];
    }

    /**
     * @throws Throwable
     */
    protected function doDeleteAll(): array
    {
        $count = $this->pdo->cell(
            'SELECT COUNT(*) FROM mail WHERE mail_to = ?',
            $this->player['userid'],
        );
        if (!$count) {
            return [
                'type' => 'info',
                'message' => sprintf(self::NO_OBJECT_TO_DELETE, 'messages'),
            ];
        }
        $save = function () {
            $this->pdo->delete(
                'mail',
                ['mail_to' => $this->player['userid']],
            );
            if ($this->player['new_mail']) {
                $this->pdo->update(
                    'users',
                    ['new_mail' => 0],
                    ['userid' => $this->player['userid']],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECTS_DELETED, 'All ' . number_format($count) . ' messages'),
        ];
    }
}
