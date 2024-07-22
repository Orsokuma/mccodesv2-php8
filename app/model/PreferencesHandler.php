<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class PreferencesHandler extends PreferencesController
{
    /**
     * @param string|null $subRoute
     * @return void
     */
    public function get(?string $subRoute = null): void
    {
        $this->displayMenu($subRoute);
        match ($subRoute) {
            'sex' => $this->displaySexSetting(),
            'pass' => $this->displayPassSetting(),
            'name' => $this->displayNameSetting(),
            'pic' => $this->displayPicSetting(),
            'forum' => $this->displayForumSetting(),
            default => '',
        };
    }

    /**
     * @param string|null $subRoute
     * @return void
     */
    public function post(?string $subRoute = null): void
    {
        $this->sendResponse($this->handlePost($subRoute), 'preferences');
    }

    /**
     * @param string|null $subRoute
     * @return array
     */
    private function handlePost(?string $subRoute = null): array
    {
        $response = match ($subRoute) {
            'sex' => $this->doUpdateSex(),
            'pass' => $this->doUpdatePass(),
            'name' => $this->doUpdateName(),
            'pic' => $this->doUpdatePic(),
            'forum' => $this->doUpdateForum(),
            default => '',
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }

    /**
     * @param string|null $subRoute
     * @return void
     */
    private function displayMenu(?string $subRoute): void
    {
        $template     = file_get_contents($this->view . '/auth/preferences/menu.html');
        $replacements = [];
        $keys         = ['sex', 'pass', 'name', 'pic', 'forum'];
        foreach ($keys as $key) {
            $upper_key             = strtoupper($key);
            $active                = '{{ACTIVE:' . $upper_key . '}}';
            $replacements[$active] = $subRoute === $key ? 'text-bold' : '';
        }
        echo strtr($template, $replacements);
    }

    /**
     * @return void
     */
    private function displaySexSetting(): void
    {
        $template = file_get_contents($this->view . '/auth/preferences/sex.html');
        echo strtr($template, [
            '{{OPPOSITE}}' => $this->player['gender'] === 'Male' ? 'Female' : 'Male',
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('prefs_sex'),
        ]);
    }

    /**
     * @return void
     */
    private function displayPassSetting(): void
    {
        $template = file_get_contents($this->view . '/auth/preferences/password.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('prefs_pass'),
        ]);
    }

    /**
     * @return void
     */
    private function displayNameSetting(): void
    {
        $template = file_get_contents($this->view . '/auth/preferences/name.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('prefs_name'),
            '{{CURRENT}}' => $this->player['username'],
        ]);
    }

    /**
     * @return void
     */
    private function displayPicSetting(): void
    {
        $template = file_get_contents($this->view . '/auth/preferences/pic.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('prefs_pic'),
            '{{CURRENT}}' => $this->player['display_pic'],
        ]);
    }

    /**
     * @return void
     */
    private function displayForumSetting(): void
    {
        $template = file_get_contents($this->view . '/auth/preferences/forum.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('prefs_forum'),
            '{{CURRENT_AVATAR}}' => $this->player['forums_avatar'],
            '{{CURRENT_SIGNATURE}}' => $this->player['forums_signature'],
            '{{CURRENT_SIGNATURE_FORMATTED}}' => nl2br($this->player['forums_signature']),
        ]);
    }
}
