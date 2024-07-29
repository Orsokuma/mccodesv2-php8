<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class BattleTentHandler extends CommonObjects
{
    /**
     * @return void
     */
    public function get(): void
    {
        if ($this->player['hospital'] || $this->player['jail']) {
            ToroHook::fire('jail_hosp');
            return;
        }
        $this->displayChallengeBots();
    }

    /**
     * @return void
     */
    private function displayChallengeBots(): void
    {
        $rows           = $this->pdo->run(
            'SELECT cb.cb_money, c.npcid, cy.cityname, u.userid, username, level, hp, maxhp, location, hospital, jail
            FROM challengebots AS cb
            LEFT JOIN users AS u ON cb.cb_npcid = u.userid
            LEFT JOIN challengesbeaten AS c ON c.npcid = u.userid AND c.userid = ?
            LEFT JOIN cities AS cy ON u.location = cy.cityid',
            $this->player['userid'],
        );
        $content        = '';
        $entry_template = file_get_contents($this->view . '/auth/battle-tent/entry.html');
        foreach ($rows as $row) {
            $owned   = $this->pdo->cell(
                'SELECT COUNT(*) FROM challengesbeaten WHERE userid = ? AND npcid = ?',
                $this->player['userid'],
                $row['userid'],
            );
            $ready   = $row['hp'] >= $row['maxhp'] / 2 &&
                $row['location'] == $this->player['location'] &&
                !$this->player['hospital'] &&
                !$this->player['jail'] &&
                !$row['hospital'] &&
                !$row['jail'];
            $content .= strtr($entry_template, [
                '{{ID}}' => $row['userid'],
                '{{NAME}}' => $row['username'],
                '{{LEVEL}}' => number_format($row['level']),
                '{{OWNED}}' => number_format($owned),
                '{{READY}}' => $ready ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>',
                '{{LOCATION}}' => $row['cityname'],
                '{{MONEY}}' => $this->func->money_formatter($row['cb_money']),
                '{{CHALLENGE}}' => $row['npcid'] ? '<em>Already</em>' : '<a href="/attack/'.$row['userid'].'">Attack</a>',
            ]);
        }
        $main_template = file_get_contents($this->view . '/auth/battle-tent/index.html');
        echo strtr($main_template, [
            '{{CHALLENGE-BOTS}}' => $content,
        ]);
    }
}
