<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class BankHandler extends BankController
{
    /**
     * @return void
     */
    public function get(): void
    {
        if ($this->player['hospital'] || $this->player['jail']) {
            ToroHook::fire('jail_hosp');
            return;
        }
        if ($this->player['bankmoney'] > -1) {
            $this->displayBank();
        } else {
            $this->displayPurchaseAccount();
        }
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
    private function handlePost(?string $subRoute = null): array
    {
        $response = match ($subRoute) {
            'open-account' => $this->doOpenAccount(),
            'deposit' => $this->doDeposit(),
            'withdraw' => $this->doWithdraw(),
        };
        if (empty($response)) {
            ToroHook::fire('404');
        }
        return $response;
    }

    /**
     * @return void
     */
    private function displayBank(): void
    {
        $template = file_get_contents($this->view . '/auth/bank/index.html');
        echo strtr($template, [
            '{{BANKMONEY_FORMATTED}}' => $this->func->money_formatter($this->player['bankmoney']),
            '{{BANKMONEY}}' => $this->player['bankmoney'],
            '{{MONEY}}' => $this->player['money'],
            '{{FEE_PERCENTAGE}}' => $this->bankSettings['fee_percentage'],
            '{{FEE_MAX}}' => $this->func->money_formatter($this->bankSettings['fee_max']),
        ]);
    }

    /**
     * @return void
     */
    private function displayPurchaseAccount(): void
    {
        $template = file_get_contents($this->view . '/auth/bank/open-account.html');
        echo strtr($template, [
            '{{ACCOUNT_COST}}' => $this->func->money_formatter($this->bankSettings['account_cost']),
            '{{CSRF_TOKEN}}' => $this->func->request_csrf_code('bank_open_account'),
        ]);
    }
}
