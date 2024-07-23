<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class CrystalMarketHandler extends CrystalMarketController
{
    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function get(?string $subRoute = null, ?string $id = null): void
    {
        match ($subRoute) {
            'add' => $this->displayAddListing(),
            'purchase' => $this->displayBuyListing((int)$id),
            'remove' => $this->displayRemoveListing((int)$id),
            'gift' => $this->displayGiftListing((int)$id),
            default => $this->displayCrystalMarket(),
        };
    }

    /**
     * @return void
     */
    protected function displayAddListing(): void
    {
        $template = file_get_contents($this->view . '/auth/crystal-market/add.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('cmarket_add'),
            '{{CRYSTALS}}' => $this->player['crystals'],
        ]);
    }

    /**
     * @param int|null $id
     * @return void
     */
    protected function displayBuyListing(?int $id): void
    {
        if (empty($id)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ]);
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'listing'),
            ]);
        }
        if ($row['cmADDER'] === $this->player['userid']) {
            $this->sendResponse([
                'type' => 'error',
                'message' => 'You can\'t purchase your own listing.',
            ]);
        }
        $template = file_get_contents($this->view . '/auth/crystal-market/purchase.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('cmarket_purchase'),
            '{{ID}}' => $row['cmID'],
            '{{AMOUNT}}' => $row['cmQTY'],
            '{{PRICE_EACH}}' => $this->func->money_formatter($row['cmPRICE']),
            '{{PRICE_TOTAL}}' => $this->func->money_formatter($row['cmPRICE'] * $row['cmQTY']),
        ]);
    }

    /**
     * @param int|null $id
     * @return void
     */
    protected function displayRemoveListing(?int $id): void
    {
        if (empty($id)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ]);
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'listing'),
            ]);
        }
        if ($row['cmADDER'] !== $this->player['userid']) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'listing'),
            ]);
        }
        $this->displayConfirmationInterstitial([
            '{{ACTION}}' => '/crystal-market/remove/' . $id,
            '{{CONTENT}}' => 'remove this listing?',
        ]);
    }

    /**
     * @param int|null $id
     * @return void
     */
    protected function displayGiftListing(?int $id): void
    {
        if (empty($id)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ]);
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row)) {
            $this->sendResponse([
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'listing'),
            ]);
        }
        if ($row['cmADDER'] === $this->player['userid']) {
            $this->sendResponse([
                'type' => 'error',
                'message' => 'You can\'t purchase your own listing.',
            ]);
        }
        $template = file_get_contents($this->view . '/auth/crystal-market/purchase-as-gift.html');
        echo strtr($template, [
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('cmarket_purchase_gift'),
            '{{ID}}' => $row['cmID'],
            '{{AMOUNT}}' => $row['cmQTY'],
            '{{PRICE_EACH}}' => $row['cmPRICE'],
            '{{PRICE_TOTAL}}' => $row['cmPRICE'] * $row['cmQTY'],
            '{{MENU:USERS}}' => $this->renderUserMenuOpts($row['cmADDER']),
        ]);
    }

    /**
     * @param int|null $adder
     * @return string
     */
    private function renderUserMenuOpts(?int $adder): string
    {
        $rows    = $this->pdo->run(
            'SELECT userid, username FROM users WHERE userid NOT IN (?, ?)',
            $this->player['userid'],
            $adder,
        );
        $content = '';
        foreach ($rows as $row) {
            $content .= sprintf('<option value="%u">%s</option>%s', $row['userid'], $row['username'], PHP_EOL);
        }
        return $content;
    }

    /**
     * @return void
     */
    private function displayCrystalMarket(): void
    {
        $rows     = $this->pdo->run(
            'SELECT c.*, u.username
            FROM crystalmarket AS c
            INNER JOIN users AS u ON c.cmADDER = u.userid
            ORDER BY c.cmQTY/c.cmPRICE',
        );
        $template = file_get_contents($this->view . '/auth/crystal-market/index.html');
        $entry    = file_get_contents($this->view . '/auth/crystal-market/entry.html');
        $content  = '';
        preg_match_all('/\{\{IF:SELLER=USER}}(.+?)\{\{ELSE}}(.+?)\{\{ENDIF}}/s', $entry, $matches);
        foreach ($rows as $row) {
            $key = $this->player['userid'] === $row['cmADDER'] ? 1 : 2;
            $entry = str_replace($matches[0][0], $matches[$key][0], $entry);
            $content  .= strtr($entry, [
                '{{ID}}' => $row['cmID'],
                '{{AMOUNT}}' => $row['cmQTY'],
                '{{PRICE_EACH}}' => $this->func->money_formatter($row['cmPRICE']),
                '{{PRICE_TOTAL}}' => $this->func->money_formatter($row['cmPRICE'] * $row['cmQTY']),
                '{{SELLER_ID}}' => $row['cmADDER'],
                '{{SELLER_NAME}}' => $row['username'],
            ]);
        }
        echo strtr($template, [
            '{{LISTINGS}}' => $content,
        ]);
    }

    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return void
     * @throws Throwable
     */
    public function post(?string $subRoute = null, ?string $id = null): void
    {
        $this->sendResponse($this->handlePost($subRoute, $id), 'crystal-market');
    }

    /**
     * @param string|null $subRoute
     * @param string|null $id
     * @return array
     * @throws Throwable
     */
    private function handlePost(?string $subRoute = null, ?string $id = null): array
    {
        $response = match ($subRoute) {
            'add' => $this->doUpsertListing(),
            'purchase' => $this->doPurchaseListing((int)$id),
            'remove' => $this->doRemoveListing((int)$id),
            'gift' => $this->doPurchaseAsGift((int)$id),
            default => null,
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }
}
