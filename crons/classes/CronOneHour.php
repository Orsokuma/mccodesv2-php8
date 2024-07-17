<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

if (!defined('CRON_FILE_INC')) {
    exit;
}

/**
 *
 */
final class CronOneHour extends CronHandler
{
    private static ?self $instance = null;

    /**
     * @param EasyDB|null $db
     * @return self|null
     */
    public static function getInstance(?EasyDB $db): ?self
    {
        parent::getInstance($db);
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * @param int $increments
     * @return void
     * @throws Throwable
     */
    public function doFullRun(int $increments): void
    {
        if (!empty($increments) && empty($this->pendingIncrements)) {
            $this->pendingIncrements = $increments;
        }
        parent::doFullRunActual([
            'processGangCrimes',
            'resetVerifiedStatus',
        ], $this);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function processGangCrimes(): void
    {
        $this->basicQueryWrap(
            'UPDATE gangs SET gangCHOURS = GREATEST(gangCHOURS - ' . $this->pendingIncrements . ', 0) WHERE gangCRIME > 0',
            $this->pendingIncrements,
        );
        $rows = $this->db->run(
            'SELECT gangID, ocSTARTTEXT, ocSUCCTEXT, ocFAILTEXT, ocMINMONEY, ocMAXMONEY, ocID, ocNAME
            FROM gangs AS g
            INNER JOIN orgcrimes AS oc ON g.gangCRIME = oc.ocID
            WHERE g.gangCRIME > 0 AND g.gangCHOURS <= 0'
        );
        foreach ($rows as $r) {
            $suc = rand(0, 1);
            $qm  = $this->db->run(
                'SELECT userid FROM users WHERE gang = ?',
                $r['gangID'],
            );
            if ($suc) {
                $log    = $r['ocSTARTTEXT'] . $r['ocSUCCTEXT'];
                $muny   = rand($r['ocMINMONEY'], $r['ocMAXMONEY']);
                $log    = str_replace('{muny}', (string)$muny, $log);
                $result = 'success';
            } else {
                $log    = $r['ocSTARTTEXT'] . $r['ocFAILTEXT'];
                $muny   = 0;
                $log    = str_replace('{muny}', (string)$muny, $log);
                $result = 'failure';
            }
            $save = function () use ($r, $qm, $muny, $log, $result) {
                $this->basicQueryWrap(
                    'UPDATE gangs SET gangMONEY = gangMONEY + ?, gangCRIME = 0 WHERE gangID = ?',
                    $muny,
                    $r['gangID'],
                );
                $i = $this->db->insert(
                    'oclogs',
                    [
                        'oclOC' => $r['ocID'],
                        'oclGANG' => $r['gangID'],
                        'oclLOG' => $log,
                        'oclRESULT' => $result,
                        'oclMONEY' => $muny,
                        'oclCRIMEN' => $r['ocNAME'],
                        'ocTIME' => time(),
                    ]
                );
                if ($i > 0) {
                    $this->updateAffectedRowCnt(1);
                }
                $past_tense = $result === 'success' ? 'Succeeded' : 'Failed';
                foreach ($qm as $rm) {
                    $event = event_add($rm['userid'], 'Your Gang\'s Organised Crime ' . $past_tense . '. Go <a href="oclog.php?ID=' . $i . '">here</a> to view the details.');
                    $this->updateAffectedRowCnt($event);
                }
            };
            $this->db->tryFlatTransaction($save);
        }
    }

    /**
     * @return void
     */
    public function resetVerifiedStatus(): void
    {
        $this->basicQueryWrap(
            'UPDATE users SET verified = 0 WHERE verified > 0',
        );
    }

    public function getClassName(): string
    {
        return __CLASS__;
    }
}
