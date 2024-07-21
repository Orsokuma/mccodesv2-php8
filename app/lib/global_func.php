<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyPlaceholder;
use ParagonIE\EasyDB\EasyStatement;

/**
 *
 */
class SiteFunctions
{
    private static ?self $siteFunctions = null;
    protected ?EasyDB $pdo = null;
    protected ?array $player = null;
    protected array $config;

    /**
     * @param EasyDB|null $pdo
     * @param array $config
     * @param array|null $player
     */
    public function __construct(?EasyDB $pdo, #[\SensitiveParameter] array $config, ?array $player)
    {
        $this->config = $config;
        $this->pdo    = $pdo;
        $this->player = $player;
    }

    /**
     * @param EasyDB|null $pdo
     * @param array $config
     * @param array|null $player
     * @return self|null
     */
    public static function getInstance(?EasyDB $pdo, #[\SensitiveParameter] array $config, ?array $player): ?self
    {
        if (self::$siteFunctions === null) {
            self::$siteFunctions = new self($pdo, $config, $player);
        }
        return self::$siteFunctions;
    }

    /**
     * Return the difference between the current time and a given time, formatted in appropriate units so the number is not too big or small.
     * @param string|int $time_stamp The timestamp to find the difference to.
     * @return string The difference formatted in units so that the numerical component is not less than 1 or absurdly large.
     */
    public function datetime_parse(string|int $time_stamp): string
    {
        $time_difference = ($_SERVER['REQUEST_TIME'] - (int)$time_stamp);
        $unit            =
            ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];
        $lengths         = [60, 60, 24, 7, 4.35, 12];
        for ($i = 0; $time_difference >= $lengths[$i]; $i++) {
            $time_difference = $time_difference / $lengths[$i];
        }
        $time_difference = round($time_difference);
        return $time_difference . ' ' . $unit[$i]
            . (($time_difference > 1 or $time_difference < 1) ? 's'
                : '') . ' ago';
    }

    /**
     * Constructs a drop-down listbox of all the item types in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the item type which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first item type alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function itemtype_dropdown(string $ddname = 'item_type', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT itmtypeid, itmtypename FROM itemtypes ORDER BY itmtypename'
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'itmtypeid',
            'name' => 'itmtypename',
        ]);
    }

    /**
     * @param array $data
     * @param array $config
     * @return string
     */
    public function dropdown(array $data, array $config): string
    {
        $ret = '<select name="' . $config['menu'] . '" id="' . $config['menu'] . '" class="form-control">';
        foreach ($data as $row) {
            $ret .= sprintf(
                '<option value="%u" %s>%s</option>%s',
                $row[$config['id']],
                $row[$config['id']] == $config['selected'] ? 'selected' : '',
                $row[$config['name']],
                PHP_EOL,
            );
        }
        $ret .= '</select>';
        return $ret;
    }

    /**
     * Constructs a drop-down listbox of all the items in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the item which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first item alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function item_dropdown(string $ddname = 'item', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT itmid, itmname FROM items ORDER BY itmname',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'itmid',
            'name' => 'itmname',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the items in the game to let the user select one, including a "None" option.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the item which should be selected by default.<br />
     * Not specifying this or setting it to a number less than 1 makes "None" selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function item2_dropdown(string $ddname = 'item', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT itmid, itmname FROM items ORDER BY itmname',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'itmid',
            'name' => 'itmname',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the locations in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the location which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first item alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function location_dropdown(string $ddname = 'location', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT cityid, cityname FROM cities ORDER BY cityname',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'cityid',
            'name' => 'cityname',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the shops in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the shop which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first shop alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function shop_dropdown(string $ddname = 'shop', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT shopID, shopNAME FROM shops ORDER BY shopNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'shopID',
            'name' => 'shopNAME',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the registered users in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the user who should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first user alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function user_dropdown(string $ddname = 'user', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT userid, username FROM users ORDER BY username',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the challenge bot NPC users in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the bot who should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first bot alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function challengebot_dropdown(string $ddname = 'bot', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT u.userid, u.username
            FROM challengebots AS cb
            INNER JOIN users AS u ON cb.cb_npcid = u.userid
            ORDER BY u.username',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the users in federal jail in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the user who should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first user alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function fed_user_dropdown(string $ddname = 'user', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT userid, username FROM users WHERE fedjail = 1 ORDER BY username',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the mail banned users in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the user who should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first user alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function mailb_user_dropdown(string $ddname = 'user', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT userid, username FROM users WHERE mailban > 0 ORDER BY username',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the forum banned users in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the user who should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first user alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function forumb_user_dropdown(string $ddname = 'user', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT userid, username FROM users WHERE forumban > 0 ORDER BY username',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the jobs in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the job which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first job alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function job_dropdown(string $ddname = 'job', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT jID, jNAME FROM jobs ORDER BY jNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'userid',
            'name' => 'username',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the job ranks in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the job rank which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first job's first job rank alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function jobrank_dropdown(string $ddname = 'jobrank', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT jrID, jNAME, jrNAME
            FROM jobranks AS jr
            INNER JOIN jobs AS j ON jr.jrJOB = j.jID
            ORDER BY j.jNAME, jr.jrNAME',
        );
        $ret  = '<select name="' . $ddname . '" id="' . $ddname . '" class="form-control">';
        foreach ($data as $row) {
            $ret .= sprintf(
                '<option value="%s" %s>%s - %s</option>%s',
                $row['jrID'],
                $row['jrID'] == $selected ? ' selected' : '',
                $row['jNAME'],
                $row['jrNAME'],
                PHP_EOL,
            );
        }
        $ret .= '</select>';
        return $ret;
    }

    /**
     * Constructs a drop-down listbox of all the houses in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the house which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first house alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function house_dropdown(string $ddname = 'house', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT hID, hNAME FROM houses ORDER BY hNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'hID',
            'name' => 'hNAME',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the houses in the game to let the user select one.<br />
     * However, the values in the list box return the house's maximum will value instead of its ID.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the house which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first house alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function house2_dropdown(string $ddname = 'house', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT hWILL, hNAME FROM houses ORDER BY hNAME'
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'hWILL',
            'name' => 'hNAME',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the courses in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the course which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first course alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function course_dropdown(string $ddname = 'course', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT crID, crNAME FROM courses ORDER BY crNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'crID',
            'name' => 'crNAME',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the crimes in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the crime which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first crime alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function crime_dropdown(string $ddname = 'crime', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT crimeID, crimeNAME FROM crimes ORDER BY crimeNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'crimeID',
            'name' => 'crimeNAME',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the crime groups in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the crime group which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first crime group alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function crimegroup_dropdown(string $ddname = 'crimegroup', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT cgID, cgNAME FROM crimegroups ORDER BY cgNAME',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'cgID',
            'name' => 'cgNAME',
        ]);
    }

    /**
     * Sends a user an event, given their ID and the text.
     * @param int $userid The user ID to be sent the event
     * @param string $text The event's text. This should be fully sanitized for HTML, but not pre-escaped for database insertion.
     * @return int 1
     */
    public function event_add(int $userid, string $text): int
    {
        $inserted = $this->pdo->insert(
            'events',
            [
                'evUSER' => $userid,
                'evTIME' => time(),
                'evTEXT' => $text,
            ]
        );
        $updated  = $this->pdo->update(
            'users',
            ['new_events' => new EasyPlaceholder('new_events + 1')],
            ['userid' => $userid],
        );
        return $updated + ($inserted > 0);
    }

    /**
     * Internal function: used to see if a user is due to level up, and if so, perform that levelup.
     */
    public function check_level(): void
    {
        $this->player['exp_needed'] = (int)(($this->player['level'] + 1) * ($this->player['level'] + 1) * ($this->player['level'] + 1) * 2.2);
        if ($this->player['exp'] >= $this->player['exp_needed']) {
            $expu                       = $this->player['exp'] - $this->player['exp_needed'];
            $this->player['level']      += 1;
            $this->player['exp']        = $expu;
            $this->player['energy']     += 2;
            $this->player['brave']      += 2;
            $this->player['maxenergy']  += 2;
            $this->player['maxbrave']   += 2;
            $this->player['hp']         += 50;
            $this->player['maxhp']      += 50;
            $this->player['exp_needed'] = (int)(($this->player['level'] + 1) * ($this->player['level'] + 1) * ($this->player['level'] + 1) * 2.2);
            $this->pdo->update(
                'users',
                [
                    'level' => new EasyPlaceholder('level + 1'),
                    'exp' => $expu,
                    'energy' => new EasyPlaceholder('energy + 2'),
                    'maxenergy' => new EasyPlaceholder('maxenergy + 2'),
                    'brave' => new EasyPlaceholder('brave + 2'),
                    'maxbrave' => new EasyPlaceholder('maxbrave + 2'),
                    'hp' => new EasyPlaceholder('hp + 50'),
                    'maxhp' => new EasyPlaceholder('maxhp + 50'),
                ],
                ['userid' => $this->player['userid'],]
            );
        }
    }

    /**
     * Get the "rank" a user has for a particular stat - if the return is n, then the user has the nth-highest value for that stat.
     * @param int|float $stat The value of the current user's stat.
     * @param string $mykey The stat to be ranked in. Must be a valid column name in the userstats table
     * @return int The user's rank in the stat
     */
    public function getRank(int|float $stat, string $mykey): int
    {
        $current = $this->pdo->cell(
            'SELECT COUNT(u.userid)
            FROM userstats AS us
            LEFT JOIN users AS u ON us.userid = u.userid
            WHERE ' . $mykey . ' > ?
              AND us.userid != ?
              AND u.user_level != 0',
            $stat,
            $this->player['userid'],
        );
        return $current + 1;
    }

    /**
     * Give a particular user a particular quantity of some item.
     * @param int $user The user ID who is to be given the item
     * @param int $itemid The item ID which is to be given
     * @param int $qty The item quantity to be given
     * @param int $notid [optional] If specified and greater than zero, prevents the item given's<br />
     * database entry combining with inventory id $notid.
     * @return int
     */
    public function item_add(int $user, int $itemid, int $qty, int $notid = 0): int
    {
        if ($notid > 0) {
            $r = $this->pdo->row(
                'SELECT inv_id FROM inventory WHERE inv_userid = ? AND inv_itemid = ? AND inv_id != ? LIMIT 1',
                $user,
                $itemid,
                $notid,
            );
        } else {
            $r = $this->pdo->row(
                'SELECT inv_id FROM inventory WHERE inv_userid = ? AND inv_itemid = ? LIMIT 1',
                $user,
                $itemid,
            );
        }
        if (!empty($r)) {
            $id = $this->pdo->update(
                'inventory',
                ['inv_qty' => new EasyPlaceholder('inv_qty + ?', $qty)],
                ['inv_id' => $r['inv_id']],
            );
        } else {
            $id = $this->pdo->insert(
                'inventory',
                [
                    'inv_userid' => $user,
                    'inv_itemid' => $itemid,
                    'inv_qty' => $qty,
                ],
            );
        }
        return $id;
    }

    /**
     * Take away from a particular user a particular quantity of some item.<br />
     * If they don't have enough of that item to be taken, takes away any that they do have.
     * @param int $user The user ID who is to lose the item
     * @param int $itemid The item ID which is to be taken
     * @param int $qty The item quantity to be taken
     */
    public function item_remove(int $user, int $itemid, int $qty): void
    {
        $r = $this->pdo->row(
            'SELECT inv_id, inv_qty FROM inventory WHERE inv_userid = ? AND inv_itemid = ? LIMIT 1',
            $user,
            $itemid,
        );
        if (!empty($r)) {
            if ($r['inv_qty'] > $qty) {
                $this->pdo->update(
                    'inventory',
                    ['inv_qty' => new EasyPlaceholder('inv_qty - ?', $qty)],
                    ['inv_id' => $r['inv_id']],
                );
            } else {
                $this->pdo->delete(
                    'inventory',
                    ['inv_id' => $r['inv_id']],
                );
            }
        }
    }

    /**
     * Constructs a drop-down listbox of all the forums in the game to let the user select one.
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the forum which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first forum alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function forum_dropdown(string $ddname = 'forum', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT ff_id, ff_name FROM forum_forums ORDER BY ff_name',
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'ff_id',
            'name' => 'ff_name',
        ]);
    }

    /**
     * Constructs a drop-down listbox of all the forums in the game, except gang forums, to let the user select one.<br />
     * @param string $ddname The "name" attribute the &lt;select&gt; attribute should have
     * @param int $selected [optional] The <i>ID number</i> of the forum which should be selected by default.<br />
     * Not specifying this or setting it to -1 makes the first forum alphabetically be selected.
     * @return string The HTML code for the listbox, to be inserted in a form.
     */
    public function forum2_dropdown(string $ddname = 'forum', int $selected = -1): string
    {
        $data = $this->pdo->run(
            'SELECT ff_id, ff_name FROM forum_forums WHERE ff_auth != \'gang\' ORDER BY ff_name'
        );
        return $this->dropdown($data, [
            'menu' => $ddname,
            'selected' => $selected,
            'id' => 'ff_id',
            'name' => 'ff_name',
        ]);
    }

    /**
     * Records an action by a member of staff in the central staff log.
     * @param string $text The log's text. This should be fully sanitized for HTML, but not pre-escaped for database insertion.
     */
    public function stafflog_add(string $text): void
    {
        $ip_addr = $_SERVER['REMOTE_ADDR'];
        $this->pdo->insert(
            'stafflog',
            [
                'user' => $this->player['userid'],
                'time' => time(),
                'action' => $text,
                'ip' => $ip_addr,
            ]
        );
    }

    /**
     * Request that an anti-CSRF verification code be issued for a particular form in the game, and return the HTML to be placed in the form.
     * @param string $formid A unique string used to identify this form to match up its submission with the right token.
     * @return string The HTML for the code issued to be added to the form.
     */
    public function request_csrf_html(string $formid): string
    {
        return "<input type='hidden' name='verf' value='"
            . $this->request_csrf_code($formid) . "' />";
    }

    /**
     * Request that an anti-CSRF verification code be issued for a particular form in the game.
     * @param string $formid A unique string used to identify this form to match up its submission with the right token.
     * @return string The code issued to be added to the form.
     */
    public function request_csrf_code(string $formid): string
    {
        // Generate the token
        $token = md5((string)mt_rand());
        // Insert/Update it
        $issue_time                  = time();
        $_SESSION['csrf_' . $formid] = [
            'token' => $token,
            'issued' => $issue_time,
        ];
        return $token;
    }

    /**
     * Check the CSRF code we received against the one that was registered for the form - return false if the request shouldn't be processed...
     * @param string $formid A unique string used to identify this form to match up its submission with the right token.
     * @param string $code The code the user's form input returned.
     * @return bool Whether the user provided a valid code or not
     */
    public function verify_csrf_code(string $formid, string $code): bool
    {
        $key = 'csrf_' . $formid;
        // Lookup the token entry
        // Is there a token in existence?
        if (!isset($_SESSION[$key])
            || !is_array($_SESSION[$key])) {
            // Obviously verification fails
            return false;
        } else {
            // From here on out we always want to remove the token when we're done - so don't return immediately
            $verified = false;
            $token    = $_SESSION[$key];
            // Expiry time on a form?
            $expiry = 900; // hacky lol
            if ($token['issued'] + $expiry > time()) {
                // It's ok, check the contents
                $verified = ($token['token'] === $code);
            } // don't need an else case - verified = false
            // Remove the token before finishing
            unset($_SESSION[$key]);
            return $verified;
        }
    }

    /**
     * Given a password input given by the user and their actual details,
     * determine whether the password entered was correct.
     *
     * Note that password-salt systems don't require the extra md5() on the $input.
     * This is only here to ensure backwards compatibility - that is,
     * a v2 game can be upgraded to use the password salt system without having
     * previously used it, without resetting every user's password.
     *
     * @param string $input The input password given by the user.
     *                        Should be without slashes.
     * @param string $salt The user's unique pass salt
     * @param string $pass The user's encrypted password
     *
     * @return bool    true for equal, false for not (login failed etc)
     *
     */
    public function verify_user_password(string $input, string $salt, string $pass): bool
    {
        return ($pass === $this->encode_password($input, $salt));
    }

    /**
     * Given a password and a salt, encode them to the form which is stored in
     * the game's database.
     *
     * @param string $password The password to be encoded
     * @param string $salt The user's unique pass salt
     * @param bool $already_md5 Whether the specified password is already
     *                                a md5 hash. This would be true for legacy
     *                                v2 passwords.
     *
     * @return string    The resulting encoded password.
     */
    public function encode_password(string $password, string $salt, bool $already_md5 = false): string
    {
        if (!$already_md5) {
            $password = md5($password);
        }
        return md5($salt . $password);
    }

    /**
     * Generate a salt to use to secure a user's password
     * from rainbow table attacks.
     *
     * @return string    The generated salt, 8 alphanumeric characters
     */
    public function generate_pass_salt(): string
    {
        return substr(md5((string)microtime(true)), 0, 8);
    }

    /**
     *
     * @return string The URL of the game.
     */
    public function determine_game_urlbase(): string
    {
        $domain = $_SERVER['HTTP_HOST'];
        $turi   = $_SERVER['REQUEST_URI'];
        $turiq  = '';
        for ($t = strlen($turi) - 1; $t >= 0; $t--) {
            if ($turi[$t] != '/') {
                $turiq = $turi[$t] . $turiq;
            } else {
                break;
            }
        }
        $turiq = '/' . $turiq;
        if ($turiq == '/') {
            $domain .= substr($turi, 0, -1);
        } else {
            $domain .= str_replace($turiq, '', $turi);
        }
        return $domain;
    }

    /**
     * Check to see if this request was made via XMLHttpRequest.
     * Uses variables supported by most JS frameworks.
     *
     * @return bool Whether the request was made via AJAX or not.
     **/

    public function is_ajax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && is_string($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get the file size in bytes of a remote file, if we can.
     *
     * @param string $url The url to the file
     *
     * @return int            The file's size in bytes, or 0 if we could
     *                        not determine its size.
     */

    public function get_filesize_remote(string $url): int
    {
        // Retrieve headers
        if (strlen($url) < 8) {
            return 0; // no file
        }
        $is_ssl = false;
        /** @noinspection HttpUrlsUsage */
        if (str_starts_with($url, 'http://')) {
            $port = 80;
        } elseif (str_starts_with($url, 'https://') && extension_loaded('openssl')) {
            $port   = 443;
            $is_ssl = true;
        } else {
            return 0; // bad protocol
        }
        // Break up url
        $url_parts = explode('/', $url);
        $host      = $url_parts[2];
        unset($url_parts[2]);
        unset($url_parts[1]);
        unset($url_parts[0]);
        $path = '/' . implode('/', $url_parts);
        if (str_contains($host, ':')) {
            $host_parts = explode(':', $host);
            if (count($host_parts) == 2 && ctype_digit($host_parts[1])) {
                $port = (int)$host_parts[1];
                $host = $host_parts[0];
            } else {
                return 0; // malformed host
            }
        }
        $request =
            'HEAD ' . $path . " HTTP/1.1\r\n" . 'Host: ' . $host . "\r\n"
            . "Connection: Close\r\n\r\n";
        $fh      = fsockopen(($is_ssl ? 'ssl://' : '') . $host, $port);
        if ($fh === false) {
            return 0;
        }
        fwrite($fh, $request);
        $headers      = [];
        $total_loaded = 0;
        while (!feof($fh) && $line = fgets($fh, 1024)) {
            if ($line == "\r\n") {
                break;
            }
            if (str_contains($line, ':')) {
                [$key, $val] = explode(':', $line, 2);
                $headers[strtolower($key)] = trim($val);
            } else {
                $headers[] = strtolower($line);
            }
            $total_loaded += strlen($line);
            if ($total_loaded > 50000) {
                // Stop loading garbage!
                break;
            }
        }
        fclose($fh);
        if (!isset($headers['content-length'])) {
            return 0;
        }
        return (int)$headers['content-length'];
    }

    /**
     * @return array
     */
    public function get_site_settings(): array
    {
        $set  = [];
        $rows = $this->pdo->run(
            'SELECT * FROM settings',
        );
        foreach ($rows as $row) {
            $set[$row['conf_name']] = $row['conf_value'];
            settype($set[$row['conf_name']], $row['data_type']);
        }
        return $set;
    }

    /**
     * @param int|string $target_id
     * @return string|int
     */
    public function userBox(int|string $target_id): string|int
    {
        return $target_id;
    }

    /**
     * @param string|array $permissions
     * @param int|null $target_id
     * @return bool
     */
    public function check_access(string|array $permissions, ?int $target_id = null): bool
    {
        // We want an array
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        // We're quite permissive with formats allowed in $permissions, turn them back into "true" permission format
        $permissions = array_map(function ($permission) {
            return strtolower(str_replace([' ', '-'], '_', $permission));
        }, $permissions);
        // If target_id isn't provided, use the current user
        $target_id ??= $this->player['userid'];
        // Get the target's roles
        $roles        = $this->pdo->run(
            'SELECT staff_role FROM users_roles WHERE userid = ' . $target_id,
        );
        $target_roles = [];
        foreach ($roles as $role) {
            $target_roles[] = $role['staff_role'];
        }
        // They don't have any
        if (!$target_roles) {
            return false;
        }
        // Get the corresponding role data
        $statement        = EasyStatement::open()
            ->in('id IN (?*)', $target_roles);
        $staff_roles      = $this->pdo->run(
            'SELECT * FROM staff_roles WHERE ' . $statement,
            ...$statement->values(),
        );
        $role_permissions = [];
        foreach ($staff_roles as $row) {
            foreach ($row as $key => $value) {
                // id and name aren't permissions
                if (in_array($key, ['id', 'name'])) {
                    continue;
                }
                // If the target has the administrator permission, grant all accesses
                if ($row['administrator']) {
                    $value = true;
                }
                // If we've not already added it, and it's true, add it
                if (!array_key_exists($key, $role_permissions) && $value) {
                    $role_permissions[] = $key;
                }
            }
        }
        // Check the given permissions against the roles' combined permissions
        $matches = array_intersect($role_permissions, $permissions);
        // No matches
        if (empty($matches)) {
            return false;
        }
        // Access granted!
        return true;
    }

    /**
     * @return bool
     */
    public function is_staff(): bool
    {
        return $this->pdo->exists(
            'SELECT COUNT(*) FROM users_roles WHERE staff_role > 0 AND userid = ?',
            $this->player['userid'],
        );
    }

    /**
     * @param int|null $online_cutoff
     * @return array
     */
    public function get_online_staff(?int $online_cutoff = null): array
    {
        $online_cutoff ??= time() - 900;
        return $this->pdo->run(
            'SELECT u.userid, u.username, u.laston
            FROM users AS u
            INNER JOIN users_roles AS ur ON ur.userid = u.userid
            WHERE ur.staff_role > 0 AND u.laston > ?
            GROUP BY u.userid
            ORDER BY userid',
            $online_cutoff,
        );
    }

    /**
     * @return void
     */
    public function end_attack(): void
    {
        $this->pdo->update(
            'users',
            ['attacking' => 0],
            ['userid' => $this->player['userid']],
        );
    }

    /**
     * @param int $to
     * @param int $from
     * @param int $amount
     * @return void
     */
    public function attack_update_gang_respect(int $to, int $from, int $amount): void
    {
        $this->pdo->update(
            'gangs',
            ['gangRESPECT' => new EasyPlaceholder('gangRESPECT + ?', $amount)],
            ['gangID' => $to],
        );
        $this->pdo->update(
            'gangs',
            ['gangRESPECT' => new EasyPlaceholder('gangRESPECT - ?', $amount)],
            ['gangID' => $from],
        );
    }

    /**
     * @param int $gangId
     * @return void
     */
    public function destroy_gang_and_end_wars(int $gangId): void
    {
        $this->pdo->update(
            'users',
            ['gang' => 0],
            ['gang' => $gangId],
        );

        $this->pdo->safeQuery(
            'DELETE FROM gangs WHERE gangRESPECT <= 0'
        );
        $this->pdo->delete(
            'gangwars',
            ['warDECLARER' => $gangId],
        );
        $this->pdo->delete(
            'gangwars',
            ['warDECLARED' => $gangId],
        );
    }

    /**
     * @param array $r
     * @return void
     */
    public function check_challenge_beaten(array $r): void
    {
        $cb = $this->pdo->row(
            'SELECT cb_money FROM challengebots WHERE cb_npcid = ?',
            $r['userid'],
        );
        if (!empty($cb)) {
            $has_beaten = $this->pdo->cell(
                'SELECT COUNT(npcid) FROM challengesbeaten WHERE userid = ? AND npcid = ?',
                $this->player['userid'],
                $r['userid'],
            );
            if (!$has_beaten) {
                $m = (int)$cb['cb_money'];
                $this->pdo->update(
                    'users',
                    ['money' => new EasyPlaceholder('money + ?', $m)],
                    ['userid' => $this->player['userid']],
                );
                $this->pdo->insert(
                    'challengesbeaten',
                    [
                        'userid' => $this->player['userid'],
                        'npcid' => $r['userid'],
                    ]
                );
                echo '<br /> You gained ' . $this->money_formatter($m) . ' for beating the challenge bot ' . $r['username'];
            }
        }
    }

    /**
     * Format money in the way humans expect to read it.
     * @param int|float|string $muny The amount of money to display
     * @param string $symb The money unit symbol to use, e.g. $
     * @return string
     */
    public function money_formatter(int|float|string $muny, string $symb = '$'): string
    {
        return $symb . number_format((float)$muny);
    }
}
