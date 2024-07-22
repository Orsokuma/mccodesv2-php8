<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;
use ParagonIE\EasyDB\EasyStatement;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class EventsHandler extends EventsController
{
    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function get(?string $subRoute = null, ?string $id = null): void
    {
        if ($id !== null) {
            $id = (int)$id;
        }
        $page_num = is_numeric($subRoute) ? (int)$subRoute : 1;
        match ($subRoute) {
            'delete' => $this->displayDeleteOneConfirmation($id),
            'delete-all' => $this->displayDeleteAllConfirmation(),
            'send-test-event' => $this->sendTestEvent($id),
            'reset-unread-event-counter' => $this->resetUnreadEventCounter(),
            default => $this->displayEvents($page_num),
        };
    }

    /**
     * @param int|null $id
     * @return void
     */
    private function displayDeleteOneConfirmation(?int $id): void
    {
        $this->displayConfirmationInterstitial([
            '{{ACTION}}' => '/events/delete/' . $id,
            '{{CONTENT}}' => 'delete this event?',
        ]);
    }

    /**
     * @return void
     */
    private function displayDeleteAllConfirmation(): void
    {
        $this->displayConfirmationInterstitial([
            '{{ACTION}}' => '/events/delete-all',
            '{{CONTENT}}' => 'delete all events?',
        ]);
    }

    /**
     * @param int $times
     * @return void
     */
    private function sendTestEvent(int $times = 1): void
    {
        $times = min(250, max(1, $times));
        for ($i = 0; $i < $times; ++$i) {
            $this->func->event_add($this->player['userid'], 'Test event!');
        }
        $_SESSION['success'] = $times.' test event'.($times === 1 ? '' : 's').' sent.';
        header('Location: /events');
        exit;
    }

    /**
     * @return void
     */
    private function resetUnreadEventCounter(): void
    {
        $unread_count = $this->pdo->cell(
            'SELECT COUNT(*) FROM events WHERE evUSER = ? AND evREAD = 0',
            $this->player['userid'],
        );
        if ($unread_count === $this->player['new_events']) {
            $_SESSION['info'] = 'Your unread event count is correct.';
        } else {
            $this->pdo->update(
                'users',
                ['new_events' => $unread_count],
                ['userid' => $this->player['userid']],
            );
            $_SESSION['success'] = 'Your unread event count has been updated.';
        }
        header('Location: /events');
        exit;
    }

    /**
     * @param int $pageNum
     * @return void
     * @throws Throwable
     */
    private function displayEvents(int $pageNum = 1): void
    {
        $count    = $this->pdo->cell(
            'SELECT COUNT(*) FROM events WHERE evUSER = ?',
            $this->player['userid'],
        );
        $pages    = new Pagination($count, $pageNum, '/events/(:num)');
        $template = file_get_contents($this->view . '/auth/events/index.html');
        preg_match('/\{{2}IF:EVENT_COUNT}{2}(.+?)\{{2}ENDIF}{2}/s', $template, $matches);
        $template = $count > 0
            ? str_replace($matches[0], $matches[1], $template)
            : str_replace($matches[0], '', $template);
        echo strtr($template, [
            '{{PAGINATION}}' => $pages->toHtml(),
            '{{EVENTS}}' => $this->renderEventContent($pages),
        ]);
    }

    /**
     * @param Pagination $pages
     * @return string
     * @throws Throwable
     */
    private function renderEventContent(Pagination $pages): string
    {
        $entry_template = file_get_contents($this->view . '/auth/events/entry.html');
        $rows           = $this->pdo->run(
            'SELECT * FROM events WHERE evUSER = ? ORDER BY evID DESC' . $pages->limit,
            $this->player['userid'],
        );
        $content        = '';
        $unread         = [];
        foreach ($rows as $row) {
            if (!$row['evREAD']) {
                $unread[] = $row['evID'];
            }
            $date    = new DateTime('@' . $row['evTIME']);
            $content .= strtr($entry_template, [
                '{{ID}}' => $row['evID'],
                '{{NEW}}' => !$row['evREAD'] ? '<br><strong>New!</strong>' : '',
                '{{DATE}}' => $date->format($this->siteSettings['default_date_format']),
                '{{EVENT}}' => nl2br($row['evTEXT']),
            ]);
        }
        $this->unflagRead($unread);
        return $content;
    }

    /**
     * @param array $unread
     * @return void
     * @throws Throwable
     */
    private function unflagRead(array $unread): void
    {
        $count = count($unread);
        if (!$count) {
            return;
        }
        $save = function () use ($unread, $count) {
            $statement = EasyStatement::open()
                ->in('evID IN (?*)', $unread);
            $this->pdo->safeQuery(
                'UPDATE events SET evREAD = 1 WHERE ' . $statement,
                $statement->values()
            );
            $this->pdo->update(
                'users',
                ['new_events' => new EasyPlaceholder('new_events - ?', $count)],
                ['userid' => $this->player['userid']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
    }

    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function post(?string $subRoute = null, ?string $id = null): void
    {
        $this->sendResponse($this->handlePost($subRoute, $id), 'events');
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
            'delete-all' => $this->doDeleteAll(),
            'delete' => $this->doDeleteOne((int)$id),
            default => [],
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }
}
