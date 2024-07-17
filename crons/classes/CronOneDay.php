<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyStatement;

if (!defined('CRON_FILE_INC') || CRON_FILE_INC !== true) {
    exit;
}

/**
 *
 */
final class CronOneDay extends CronHandler
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

    public function getClassName(): string
    {
        return __CLASS__;
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
            'updatePunishments',
            'updateDailyTicks',
            'processCourses',
            'payJobWages',
            'updateBankInterests',
            'clearVotes',
        ], $this);
    }

    /**
     * @return void
     */
    public function clearVotes(): void
    {
        $this->basicQueryWrap('TRUNCATE TABLE votes');
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function processCourses(): void
    {
        $users_on_course = $this->db->run(
            'SELECT userid, course FROM users WHERE cdays <= 0 AND course > 0',
        );
        $course_cache    = [];
        foreach ($users_on_course as $row) {
            if (!array_key_exists($row['course'], $course_cache)) {
                $course                       = $this->db->row(
                    'SELECT crSTR, crGUARD, crLABOUR, crAGIL, crIQ, crNAME FROM courses WHERE crID = ?',
                    $row['course'],
                );
                $course_cache[$row['course']] = $course;
            } else {
                $course = $course_cache[$row['course']];
            }
        }
        foreach ($users_on_course as $row) {
            $save = function () use ($row, $course_cache) {
                $course   = $course_cache[$row['course']];
                $inserted = $this->db->insert(
                    'coursesdone',
                    [
                        'userid' => $row['userid'],
                        'courseid' => $row['course'],
                    ]
                );
                if ($inserted > 0) {
                    $this->updateAffectedRowCnt(1);
                }
                $statement = '';
                $params    = [];
                $ev        = '';
                if ($course['crSTR'] > 0) {
                    $statement .= ', us.strength = us.strength + ?';
                    $params[]  = $course['crSTR'];
                    $ev        .= ', ' . $course['crSTR'] . ' strength';
                }
                if ($course['crGUARD'] > 0) {
                    $statement .= ', us.guard = us.guard + ?';
                    $params[]  = $course['crGUARD'];
                    $ev        .= ', ' . $course['crGUARD'] . ' guard';
                }
                if ($course['crLABOUR'] > 0) {
                    $statement .= ', us.labour = us.labour + ?';
                    $params[]  = $course['crLABOUR'];
                    $ev        .= ', ' . $course['crLABOUR'] . ' labour';
                }
                if ($course['crAGIL'] > 0) {
                    $statement .= ', us.agility = us.agility + ?';
                    $params[]  = $course['crAGIL'];
                    $ev        .= ', ' . $course['crAGIL'] . ' agility';
                }
                if ($course['crIQ'] > 0) {
                    $statement .= ', us.IQ = us.IQ + ?';
                    $params[]  = $course['crIQ'];
                    $ev        .= ', ' . $course['crIQ'] . ' IQ';
                }
                $params[] = $row['userid'];
                $ev       = substr($ev, 1);
                /** @noinspection SqlWithoutWhere */
                $this->basicQueryWrap(
                    'UPDATE users AS u
                    INNER JOIN userstats AS us ON u.userid = us.userid
                    SET u.course = 0' . $statement . '
                    WHERE u.userid = ?',
                    ...$params
                );
                $event = event_add($row['userid'], 'Congratulations, you completed the ' . $course['crNAME'] . ' and gained ' . $ev . '!');
                $this->updateAffectedRowCnt($event);
            };
            $this->db->tryFlatTransaction($save);
        }
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function updateDailyTicks(): void
    {
        $save = function () {
            $this->basicQueryWrap(
                'UPDATE users SET 
                 daysingang = daysingang + IF(gang > 0, ?, 0),
                 boxes_opened = 0,
                 mailban = mailban - IF(mailban > 0, GREATEST(?, 1), 0),
                 donatordays = donatordays - IF(donatordays > 0, GREATEST(?, 1), 0),
                 cdays = cdays - IF(course > 0, GREATEST(?, 1), 0)
             WHERE gang > 0 OR mailban > 0 OR donatordays > 0 OR cdays > 0 OR boxes_opened <> 0',
                $this->pendingIncrements,
                $this->pendingIncrements,
                $this->pendingIncrements,
                $this->pendingIncrements,
            );
            $this->basicQueryWrap(
                'UPDATE users SET daysold = daysold + ? WHERE user_level > 0',
                $this->pendingIncrements,
            );
        };
        $this->db->tryFlatTransaction($save);
    }

    /**
     * @throws Exception|Throwable
     */
    public function updatePunishments(): void
    {
        $this->basicQueryWrap(
            'UPDATE fedjail SET fed_days = GREATEST(fed_days - ?, 0)',
            $this->pendingIncrements,
        );
        $fedjail = $this->db->run(
            'SELECT * FROM fedjail WHERE fed_days <= 0',
        );
        $ids     = [];
        foreach ($fedjail as $row) {
            $ids[] = $row['fed_userid'];
        }
        $save = function () use ($ids) {
            if (count($ids) > 0) {
                $statement = EasyStatement::open()
                    ->in('userid IN (?*)', $ids);
                $this->basicQueryWrap(
                    'UPDATE users SET fedjail = 0 WHERE ' . $statement,
                    ...$statement->values(),
                );
            }
            $this->basicQueryWrap('DELETE FROM fedjail WHERE fed_days <= 0');
        };
        $this->db->tryFlatTransaction($save);
    }

    /**
     * @throws Exception
     */
    public function payJobWages(): void
    {
        $params = [];
        $params = array_pad($params, 8, $this->pendingIncrements);
        $this->basicQueryWrap(
            'UPDATE users AS u
            INNER JOIN userstats AS us ON u.userid = us.userid
            LEFT JOIN jobranks AS jr ON jr.jrID = u.jobrank
            SET 
                u.money = u.money + (jr.jrPAY * ?),
                u.exp = u.exp + ((jr.jrPAY / 20) * ?),
                us.strength = (us.strength + ?) + jr.jrSTRG - ?,
                us.labour = (us.labour + ?) + jr.jrLABOURG - ?,
                us.IQ = (us.IQ + ?) + jr.jrIQG - ?
            WHERE u.job > 0 AND u.jobrank > 0',
            ...$params,
        );
    }

    /**
     * @throws Exception
     */
    public function updateBankInterests(): void
    {
        $rates    = [
            'bank' => pow(1 + 2 / 100, 1 / 365.2425),
            'cyber' => pow(1 + 7 / 100, 1 / 365.2425),
        ];
        $partials = [
            'bank' => pow($rates['bank'], $this->pendingIncrements),
            'cyber' => pow($rates['cyber'], $this->pendingIncrements),
        ];
        $this->basicQueryWrap(
            'UPDATE users SET 
            bankmoney = IF(bankmoney > 0, ? * bankmoney, bankmoney),
            cybermoney = IF(cybermoney > 0, ? * cybermoney, cybermoney)
            WHERE bankmoney > 0 OR cybermoney > 0',
            $partials['bank'],
            $partials['cyber'],
        );
    }
}
