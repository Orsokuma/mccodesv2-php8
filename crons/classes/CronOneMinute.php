<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

if (!defined('CRON_FILE_INC') || CRON_FILE_INC !== true) {
    exit;
}

/**
 *
 */
final class CronOneMinute extends CronHandler
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
     * @return string
     */
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
            'updateJailHospitalTimes',
        ], $this);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function updateJailHospitalTimes(): void
    {
        $this->basicQueryWrap(
            'UPDATE users SET hospital = GREATEST(hospital - ?, 0), jail = GREATEST(jail - ?, 0) WHERE jail > 0 OR hospital > 0',
            $this->pendingIncrements,
            $this->pendingIncrements,
        );
        $counts = $this->db->row(
            'SELECT 
            SUM(IF(hospital > 0, 1, 0)) AS hc,
            SUM(IF(jail > 0, 1, 0)) AS jc
            FROM users'
        );
        $this->basicQueryWrap(
            'UPDATE settings SET 
                    conf_value = IF(conf_name = \'hospital_count\', ?, conf_value),
                    conf_value = IF(conf_name = \'jail_count\', ?, conf_value)
                WHERE conf_name IN (\'hospital_count\', \'jail_count\')',
            $counts['hc'],
            $counts['jc'],
        );
    }
}
