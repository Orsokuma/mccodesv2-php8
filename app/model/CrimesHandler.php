<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class CrimesHandler extends CrimesController
{
    /**
     * @return void
     */
    public function get(): void
    {
        $this->renderCrimes();
    }

    /**
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function post(?string $id = null): void
    {
        $this->sendResponse($this->handlePost((int)$id), 'criminal');
    }

    /**
     * @param int|null $id
     * @return array
     * @throws Throwable
     */
    private function handlePost(?int $id): array
    {
        $response = $this->doCrime($id);
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }

    /**
     * @return void
     */
    private function renderCrimes(): void
    {
        $data = $this->getGroupedCrimes();
        $template = file_get_contents($this->view.'/auth/crimes/index.html');
        $entry = file_get_contents($this->view.'/auth/crimes/entry.html');
        $content = '';
        foreach ($data as $group_id => $crimes) {
            // Only the "name" key exists. No available crimes. Skip this iteration.
            if (count($crimes) === 1) {
                continue;
            }
            $content .= '<thead><tr><th colspan="3" class="text-center">'.$crimes['name'].'</th></tr>';
            unset($crimes['name']);
            foreach ($crimes as $row) {
                $content .= strtr($entry, [
                    '{{ID}}' => $row['crimeID'],
                    '{{NAME}}' => $row['crimeNAME'],
                    '{{BRAVE}}' => $row['crimeBRAVE'],
                ]);
            }
        }
        echo strtr($template, [
            '{{CRIMES}}' => $content,
        ]);
    }

    /**
     * @return array
     */
    private function getGroupedCrimes(): array
    {
        $crimes = [];
        // get groups
        $groups = $this->pdo->run(
            'SELECT * FROM crimegroups',
        );
        // populate $crimes by group ID, add `group` key for group name
        foreach ($groups as $group) {
            $crimes[$group['cgID']] = ['group' => $group['cgNAME']];
        }
        // get crimes
        $rows = $this->pdo->run(
            'SELECT crimeID, crimeNAME, crimeBRAVE, crimeGROUP FROM crimes WHERE crimeBRAVE <= ? ORDER BY crimeBRAVE',
            $this->player['brave'],
        );
        // populate crime groups
        foreach ($rows as $row) {
            $crimes[$row['crimeGROUP']][] = $row;
        }
        return $crimes;
    }
}
