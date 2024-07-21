<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class HomeController extends CommonObjects
{
    private const int CHARACTER_LIMIT = 500;
    private const string CHARACTER_LIMIT_REACHED = 'Your notepad is limited to %s characters.';
    private const string NOTEPAD_UPDATED = 'Your notepad has been updated.';

    /**
     * @return array|string[]
     */
    protected function doNotepadUpdate(): array
    {
        $_POST['pn_update'] = array_key_exists('pn_update', $_POST) ? trim($_POST['pn_update']) : '';
        if (strlen($_POST['pn_update']) > 500) {
            return [
                'type' => 'error',
                'message' => sprintf(self::CHARACTER_LIMIT_REACHED, number_format(self::CHARACTER_LIMIT)),
            ];
        }
        $this->pdo->update(
            'users',
            ['user_notepad' => $_POST['pn_update']],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => self::NOTEPAD_UPDATED,
        ];
    }
}
