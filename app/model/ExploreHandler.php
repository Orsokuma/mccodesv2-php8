<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

class ExploreHandler extends CommonObjects
{
    public function get(): void
    {
        $this->displayExplore();
    }

    /**
     * @return void
     */
    private function displayExplore(): void
    {
        if ($this->player['hospital'] || $this->player['jail']) {
            ToroHook::fire('jail_hosp');
            return;
        }
        $content = file_get_contents($this->view . '/auth/explore.html');
        echo strtr($content, [
            '{{GAME_NAME}}' => $this->siteSettings['game_name'],
            '{{DOMAIN}}' => $this->func->determine_game_urlbase(),
            '{{USERID}}' => $this->player['userid'],
            '{{RANDOM_CODE}}' => rand(100, 999),
        ]);
    }
}
