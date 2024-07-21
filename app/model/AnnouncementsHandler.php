<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class AnnouncementsHandler extends CommonObjects
{
    /**
     * @param string|int|null $pageNum
     * @return void
     * @throws Exception
     */
    public function get(string|int|null $pageNum = null): void
    {
        $this->displayAnnouncements((int)$pageNum > 0 ? (int)$pageNum : 1);
    }

    /**
     * @param int|null $pageNum
     * @return void
     * @throws Exception
     */
    private function displayAnnouncements(?int $pageNum): void
    {
        $count = $this->pdo->cell(
            'SELECT COUNT(*) FROM announcements',
        );
        $pages = new Pagination($count, $pageNum, '/announcements/(:num)');
        $template = file_get_contents($this->view . '/auth/announcements/index.html');
        echo strtr($template, [
            '{{PAGINATION}}' => $pages->toHtml(),
            '{{ANNOUNCEMENTS}}' => $this->renderAnnouncementContent($pages),
        ]);
        if ($this->player['new_announcements']) {
            $this->pdo->update(
                'users',
                ['new_announcements' => 0],
                ['userid' => $this->player['userid']],
            );
        }
    }

    /**
     * @param Pagination $pages
     * @return string
     * @throws Exception
     */
    private function renderAnnouncementContent(Pagination $pages): string
    {
        $rows = $this->pdo->run(
            'SELECT * FROM announcements ORDER BY a_time DESC'.$pages->limit,
        );
        $entry_template = file_get_contents($this->view . '/auth/announcements/entry.html');
        $content = '';
        $unread = $this->player['new_announcements'];
        foreach ($rows as $row) {
            $new = $unread > 0 ? '<br><strong>New!</strong>' : '';
            $date = new \DateTime('@'.$row['a_time']);
            $content .= strtr($entry_template, [
                '{{DATE}}' => $date->format($this->siteSettings['default_date_format']),
                '{{NEW}}' => $new,
                '{{CONTENT}}' => nl2br($row['a_text']),
            ]);
            --$unread;
        }
        return $content;
    }
}
