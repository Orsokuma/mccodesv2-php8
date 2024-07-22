<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

use ParagonIE\EasyDB\EasyPlaceholder;

/**
 *
 */
class CrimesController extends CommonObjects
{
    /**
     * @param int|null $id
     * @return array
     * @throws Throwable
     */
    protected function doCrime(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'crime'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT * FROM crimes WHERE crimeID = ?',
            $id,
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'crime'),
            ];
        }
        if ($row['crimeBRAVE'] > $this->player['brave']) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_ENOUGH, 'brave'),
            ];
        }
        $type = '';
        $message = nl2br($row['crimeITEXT']).'<br>';
        $save        = function () use ($row, &$type, &$message) {
            $sucrate = 1;
            $ec      = '$sucrate=' . strtr($row['crimePERCFORM'], [
                    'LEVEL' => $this->player['level'],
                    'CRIMEXP' => $this->player['crimexp'],
                    'EXP' => $this->player['exp'],
                    'WILL' => $this->player['will'],
                    'IQ' => $this->player['IQ'],
                ]) . ';';
            eval($ec);
            $this->pdo->update(
                'users',
                ['brave' => $this->player['brave']],
                ['userid' => $this->player['userid']],
            );
            if (rand(1, 100) <= $sucrate) {
                $type = 'success';
                $message .= str_replace('{money}', $row['crimeSUCCESSMUNY'], nl2br($row['crimeSTEXT']));
                $this->player['money']    += $row['crimeSUCCESSMUNY'];
                $this->player['crystals'] += $row['crimeSUCCESSCRYS'];
                $this->player['exp']      += (int)($row['crimeSUCCESSMUNY'] / 8);
                $this->pdo->update(
                    'users',
                    [
                        'money' => $this->player['money'],
                        'crystals' => $this->player['crystals'],
                        'exp' => $this->player['exp'],
                        'crimexp' => new EasyPlaceholder('crimexp + ?', $row['crimeXP']),
                    ],
                    ['userid' => $this->player['userid']],
                );
                if ($row['crimeSUCCESSITEM']) {
                    $this->func->item_add($this->player['userid'], $row['crimeSUCCESSITEM'], 1);
                }
            } elseif (rand(1, 2) == 1) {
                $type = 'info';
                $message .= nl2br($row['crimeFTEXT']);
            } else {
                $type = 'warning';
                $message .= nl2br($row['crimeJTEXT']);
                $this->pdo->update(
                    'users',
                    [
                        'jail' => $row['crimeJAILTIME'],
                        'jail_reason' => $row['crimeJREASON'],
                    ],
                    ['userid' => $this->player['userid']],
                );
            }
        };
        $this->pdo->tryFlatTransaction($save);
        return compact('type', 'message');
    }
}
