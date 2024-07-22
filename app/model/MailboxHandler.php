<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyStatement;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class MailboxHandler extends MailboxController
{
    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function get(?string $subRoute = null, ?string $id = null): void
    {
        if ($this->player['mailban']) {
            $this->sendResponse([
                'type' => 'error',
                'message' => 'You\'re banned from the mail system for ' . $this->player['mailban'] . ' day' . ($this->player['mailban'] === 1 ? '' : 's') . '. Reason: ' . $this->player['mb_reason'],
            ]);
        }
        $this->renderMailboxMenu($subRoute);
        match ($subRoute) {
            'outbox' => $this->displayBox('from', (int)$id),
            'compose' => $this->displayCompose((int)$id),
            'delete' => $this->displayInterstitial('/mailbox/delete/' . (int)$id, 'delete this message'),
            'delete-read' => $this->displayInterstitial('/mailbox/delete-read', 'delete read messages'),
            'delete-all' => $this->displayInterstitial('/mailbox/delete-all', 'delete all messages'),
            'archive' => $this->displayArchiveOptions(),
            default => $this->displayBox('to', (int)$id),
        };
    }

    /**
     * @param string|null $subRoute
     * @return void
     */
    private function renderMailboxMenu(?string $subRoute = null): void
    {
        $subRoute ??= 'inbox';
        $template     = file_get_contents($this->view . '/auth/mail/menu.html');
        $replacements = [];
        $keys         = ['inbox', 'outbox', 'compose', 'delete-read', 'delete-all', 'archive'];
        foreach ($keys as $key) {
            $active                = '{{ACTIVE:' . strtoupper($key) . '}}';
            $replacements[$active] = $key === $subRoute ? 'text-bold' : '';
        }
        echo strtr($template, $replacements);
    }

    /**
     * @param string $type
     * @param int|null $id
     * @return void
     * @throws Throwable
     */
    private function displayBox(string $type, ?int $id): void
    {
        $box      = $type === 'to' ? 'inbox' : 'outbox';
        $opposite = $type === 'to' ? 'from' : 'to';
        $count    = $this->pdo->cell(
            'SELECT COUNT(*) FROM mail WHERE mail_' . $type . ' = ?',
            $this->player['userid'],
        );
        $pages    = new Pagination($count, $id ?? 1, '/mailbox/' . $box . '/(:num)');
        $template = file_get_contents($this->view . '/auth/mail/box.html');
        echo strtr($template, [
            '{{PAGINATION}}' => $pages->toHtml(),
            '{{TYPE}}' => ucfirst($opposite),
            '{{MAILBOX}}' => $this->renderMailboxContent($pages, $type, $opposite),
        ]);
    }

    /**
     * @param Pagination $pages
     * @param string $type
     * @param string $opposite
     * @return string
     * @throws Throwable
     */
    private function renderMailboxContent(Pagination $pages, string $type, string $opposite): string
    {
        $rows     = $this->pdo->run(
            'SELECT m.*, u.username
            FROM mail AS m
            LEFT JOIN users AS u ON m.mail_' . $opposite . ' = u.userid
            WHERE m.mail_' . $type . ' = ?
            ORDER BY m.mail_time DESC' . $pages->limit,
            $this->player['userid'],
        );
        $template = file_get_contents($this->view . '/auth/mail/entry.html');
        preg_match_all('/\{\{IF:!SYSTEM}}(.+?)\{\{ENDIF}}/s', $template, $matches);
        $unread  = [];
        $content = '';
        foreach ($rows as $row) {
            if (!$row['mail_read']) {
                $unread[] = $row['mail_id'];
            }
            $other = $row['mail_from'] === $this->player['userid'] ? $row['mail_to'] : $row['mail_from'];
            $i     = 0;
            $tmp   = $template;
            foreach ($matches[0] as $match) {
                $tmp = str_replace($match, $other > 0 ? $matches[1][$i] : '', $tmp);
                ++$i;
            }
            $date    = new DateTime('@' . $row['mail_time']);
            $content .= strtr($tmp, [
                '{{ID}}' => $row['mail_id'],
                '{{DATE}}' => $date->format($this->siteSettings['default_date_format']),
                '{{SUBJECT}}' => $row['mail_subject'],
                '{{MESSAGE}}' => $row['mail_text'],
                '{{OTHER_ID}}' => $other,
                '{{OTHER_USERNAME}}' => !empty($row['username']) ? '<a href="/profile/' . $other . '">' . $row['username'] . '</a> [' . $other . ']' : 'SYSTEM',
            ]);
        }
        $this->unflagUnread($unread);
        return $content;
    }

    /**
     * @param array $unread
     * @return void
     * @throws Throwable
     */
    private function unflagUnread(array $unread): void
    {
        $unread_count = count($unread);
        if (!$unread_count) {
            return;
        }
        $remaining_unread_count = 0;
        if ($this->player['new_mail'] !== 0) {
            $remaining_statement    = EasyStatement::open()
                ->with('mail_to = ?', $this->player['userid'])
                ->andWith('mail_read = 0')
                ->andIn('mail_id NOT IN (?*)', $unread);
            $remaining_unread_count = $this->pdo->cell(
                'SELECT COUNT(*) FROM mail WHERE ' . $remaining_statement,
                ...$remaining_statement->values(),
            );
        }
        $save = function () use ($unread, $remaining_unread_count) {
            $statement = EasyStatement::open()
                ->with('mail_to = ?', $this->player['userid'])
                ->andIn('mail_id IN (?*)', $unread);

            $this->pdo->safeQuery(
                'UPDATE mail SET mail_read = 1 WHERE ' . $statement,
                $statement->values(),
            );
            $this->pdo->update(
                'users',
                ['new_mail' => $remaining_unread_count],
                ['userid' => $this->player['userid']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
    }

    /**
     * @param int|null $id
     * @return void
     * @throws Exception
     */
    private function displayCompose(?int $id = null): void
    {
        if ($id) {
            $exists = $this->pdo->exists(
                'SELECT COUNT(*) FROM users WHERE userid = ?',
                $id,
            );
            if (!$exists) {
                $id = null;
            }
        }
        $template = file_get_contents($this->view . '/auth/mail/compose.html');
        echo strtr($template, [
            '{{ID}}' => $id,
            '{{MENU:USERS}}' => $this->renderUserMenuOpts($id),
            '{{MENU:CONTACTS}}' => $this->renderContactMenuOpts($id),
            '{{MESSAGE_HISTORY}}' => $this->renderMessageHistory($id),
        ]);
    }

    /**
     * @param int|null $id
     * @return string
     */
    private function renderUserMenuOpts(?int $id): string
    {
        $rows = $this->pdo->run(
            'SELECT userid, username FROM users ORDER BY username',
        );
        return $this->renderMenu($rows, $id);
    }

    /**
     * @param array $rows
     * @param int|null $id
     * @return string
     */
    private function renderMenu(array $rows, ?int $id): string
    {
        $opts = '';
        foreach ($rows as $row) {
            $opts .= sprintf(
                '<option value="%s" %s>%s</option>%s',
                $row['userid'],
                $row['userid'] === $id ? 'selected' : '',
                $row['username'],
                PHP_EOL,
            );
        }
        return $opts;
    }

    /**
     * @param int|null $id
     * @return string
     */
    private function renderContactMenuOpts(?int $id): string
    {
        $rows = $this->pdo->run(
            'SELECT u.userid, u.username
            FROM contactlist AS c
            INNER JOIN users AS u ON c.cl_ADDED = u.userid
            WHERE c.cl_ADDER = ?
            ORDER BY username',
            $this->player['userid'],
        );
        return $this->renderMenu($rows, $id);
    }

    /**
     * @param int|null $id
     * @return string
     * @throws Exception
     */
    private function renderMessageHistory(?int $id): string
    {
        if (empty($id)) {
            return '';
        }
        $rows = $this->pdo->run(
            'SELECT m.mail_id, m.mail_from, m.mail_subject, m.mail_text, m.mail_time, m.mail_to, u.username
            FROM mail AS m
            INNER JOIN users AS u ON m.mail_from = u.userid OR m.mail_to = u.userid
            WHERE (m.mail_from = ? AND m.mail_to = ?) OR (m.mail_to = ? AND m.mail_from = ?)
            ORDER BY m.mail_time DESC
            LIMIT 5',
            $this->player['userid'],
            $id,
            $this->player['userid'],
            $id,
        );
        if (empty($rows)) {
            return '';
        }
        $template      = file_get_contents($this->view . '/auth/mail/history.html');
        $history_entry = file_get_contents($this->view . '/auth/mail/history-entry.html');
        $content       = '';
        foreach ($rows as $row) {
            $other   = $row['mail_from'] === $this->player['userid'] ? $row['mail_to'] : $row['mail_from'];
            $date    = new DateTime('@' . $row['mail_time']);
            $content .= strtr($history_entry, [
                '{{ID}}' => $row['mail_id'],
                '{{DATE}}' => $date->format($this->siteSettings['default_date_format']),
                '{{SUBJECT}}' => $row['mail_subject'],
                '{{MESSAGE}}' => $row['mail_text'],
                '{{OTHER_ID}}' => $other,
                '{{OTHER_USERNAME}}' => !empty($row['username']) ? '<a href="/profile/' . $other . '">' . $row['username'] . '</a> [' . $other . ']' : 'SYSTEM',
            ]);
        }
        return strtr($template, [
            '{{HISTORY}}' => $content,
        ]);
    }

    /**
     * @param string $action
     * @param string $content
     * @return void
     */
    private function displayInterstitial(string $action, string $content): void
    {
        $this->displayConfirmationInterstitial([
            '{{ACTION}}' => $action,
            '{{CONTENT}}' => $content,
        ]);
    }

    /**
     * @return void
     */
    private function displayArchiveOptions(): void
    {
        echo file_get_contents($this->view . '/auth/mail/archive.html');
    }

    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function post(?string $subRoute = null, ?string $id = null): void
    {
        $this->sendResponse($this->handlePost($subRoute, $id), 'mailbox');
    }

    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return array
     * @throws Throwable
     */
    private function handlePost(?string $subRoute = null, ?string $id = null): array
    {
        $response = match ($subRoute) {
            'compose' => $this->doSendMessage(),
            'delete' => $this->doDeleteOne((int)$id),
            'delete-read' => $this->doDeleteRead(),
            'delete-all' => $this->doDeleteAll(),
            default => null,
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }
}
