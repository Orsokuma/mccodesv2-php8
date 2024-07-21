<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class HomeHandler extends HomeController
{
    private array $statColumns = ['strength', 'agility', 'guard', 'labour', 'IQ'];

    /**
     * @param string|null $subRoute
     * @return void
     */
    public function get(?string $subRoute = null): void
    {
        match ($subRoute) {
            default => $this->displayHome(),
        };
    }

    /**
     * @return void
     */
    private function displayHome(): void
    {
        $stats              = $this->getStats();
        $template           = file_get_contents($this->view . '/auth/home.html');
        $exp                = (int)($this->player['exp'] / $this->player['exp_needed'] * 100);
        $basic_replacements = [
            '{{USERNAME}}' => $this->player['username'],
            '{{MONEY}}' => $this->func->money_formatter($this->player['money']),
            '{{CRYSTALS}}' => number_format($this->player['crystals']),
            '{{LEVEL}}' => $this->player['level'],
            '{{EXP}}' => $exp,
            '{{HP}}' => $this->player['hp'],
            '{{HP_MAX}}' => $this->player['maxhp'],
            '{{PROPERTY}}' => (new PropertyHandler())->getHouseByWill($this->player['maxwill'])['hNAME'],
            '{{USER_NOTEPAD}}' => htmlentities($this->player['user_notepad'], ENT_QUOTES, 'ISO-8859-1'),
        ];
        $stat_replacements  = [];
        foreach ($this->statColumns as $key) {
            $upper_key = strtoupper($key);
            $stat                     = '{{' . $upper_key . '}}';
            $rank                     = '{{' . $upper_key . '_RANK}}';
            $stat_replacements[$stat] = $stats['stats'][$key];
            $stat_replacements[$rank] = $stats['ranks'][$key];
        }
        $stat_replacements += [
            '{{TOTAL_STATS}}' => $stats['stats']['total'],
            '{{TOTAL_RANK}}' => $stats['ranks']['total'],
        ];
        echo strtr($template, $basic_replacements + $stat_replacements);
    }

    /**
     * @return array|array[]
     */
    private function getStats(): array
    {
        $data = [
            'stats' => [],
            'ranks' => [],
        ];
        foreach ($this->statColumns as $key) {
            $data['stats'][$key] = $this->player[$key];
            $data['ranks'][$key] = $this->func->getRank($this->player[$key], $key);
        }
        $data['stats']['total'] = array_sum($data['stats']);
        $data['stats']['total-formatted'] = number_format($data['stats']['total']);
        $data['ranks']['total'] = $this->func->getRank($data['stats']['total'], 'strength+agility+guard+labour+IQ');
        return $data;
    }

    /**
     * @param string|null $subRoute
     * @return void
     */
    public function post(?string $subRoute = null): void
    {
        $this->afterPost($this->handlePost($subRoute));
    }

    /**
     * @param string|null $subRoute
     * @return array
     */
    private function handlePost(?string $subRoute): array
    {
        return match ($subRoute) {
            'notepad' => $this->doNotepadUpdate(),
            default => self::UNKNOWN_ACTION_TAKEN,
        };
    }
}
