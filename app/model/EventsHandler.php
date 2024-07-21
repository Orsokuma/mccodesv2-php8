<?php
declare(strict_types=1);
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
     * @throws Exception
     */
    public function get(?string $subRoute = null, ?string $id = null): void
    {
        if ($id !== null) {
            $id = (int)$id;
        }
        match ($subRoute) {
            'delete' => $this->displayDeleteOneConfirmation($id),
            'delete-all' => $this->displayDeleteAllConfirmation(),
            default => $this->displayEvents((int)$subRoute),
        };
    }

    /**
     * @param int $pageNum
     * @return void
     * @throws Exception
     */
    private function displayEvents(int $pageNum = 1): void
    {
        $count    = $this->pdo->cell(
            'SELECT COUNT(*) FROM events WHERE evUSER = ?',
            $this->player['userid'],
        );
        $pages    = new Pagination($count, $pageNum, '/events/(:num)');
        $template = file_get_contents($this->view . '/auth/events/index.html');
        echo strtr($template, [
            '{{PAGINATION}}' => $pages->toHtml(),
            '{{EVENTS}}' => $this->renderEventContent($pages),
        ]);
    }

    /**
     * @param Pagination $pages
     * @return string
     * @throws Exception
     */
    private function renderEventContent(Pagination $pages): string
    {
        $entry_template = file_get_contents($this->view . '/auth/events/entry.html');
        $rows           = $this->pdo->run(
            'SELECT * FROM events WHERE evUSER = ? ORDER BY evID DESC' . $pages->limit,
            $this->player['userid'],
        );
        $content        = '';
        foreach ($rows as $row) {
            $date    = new \DateTime('@' . $row['evTIME']);
            $content .= strtr($entry_template, [
                '{{ID}}' => $row['evID'],
                '{{DATE}}' => $date->format($this->siteSettings['default_date_format']),
                '{{EVENT}}' => nl2br($row['evTEXT']),
            ]);
        }
        return $content;
    }
}
