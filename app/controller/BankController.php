<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class BankController extends CommonObjects
{
    private const string DEPOSIT_IS_NIL = 'That would result in a nil deposit. You need to add more cash in a single deposit.';
    private const string NO_ACCOUNT_403 = 'You do not have a bank account and cannot perform that action.';
    private const string DEPOSIT_COMPLETE = 'You hand over %s to be deposited. After the fee is taken (%s), %s is added to your account.<br>You now have %s in the bank.';
    private const string NOT_ENOUGH_MONEY = 'You don\'t have enough money.';
    private const string NOT_ENOUGH_BANKED = 'You don\'t have enough money in your bank.';
    private const string WITHDRAW_COMPLETE = 'You ask to withdraw %s. The bank teller grudgingly hands it over.<br>You now have %s in the bank.';
    private const string ACCOUNT_ALREADY_OWNED = 'You already have a bank account.';
    private const string ACCOUNT_OPENED = 'You\'ve opened an account for %s!';
    protected array $bankSettings = [
        'account_cost' => 50000,
        'fee_max' => 3000,
        'fee_percentage' => 15,
    ];

    /**
     * @return array|string[]
     */
    protected function doDeposit(): array
    {
        if ($this->player['bankmoney'] < 0) {
            return [
                'type' => 'error',
                'message' => self::NO_ACCOUNT_403,
            ];
        }
        $_POST['deposit'] = array_key_exists('deposit', $_POST) && (int)$_POST['deposit'] > 0 ? (int)$_POST['deposit'] : 0;
        if (empty($_POST['deposit'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'deposit amount'),
            ];
        }
        if ($_POST['deposit'] > $this->player['money']) {
            return [
                'type' => 'error',
                'message' => self::NOT_ENOUGH_MONEY,
            ];
        }
        $fee = (int)($_POST['deposit'] / 100 * $this->bankSettings['fee_percentage']);
        if ($fee > $this->bankSettings['fee_max']) {
            $fee = $this->bankSettings['fee_max'];
        }
        $deposit = $_POST['deposit'] - $fee;
        if ($deposit <= 0) {
            return [
                'type' => 'error',
                'message' => self::DEPOSIT_IS_NIL,
            ];
        }
        $this->pdo->update(
            'users',
            [
                'bankmoney' => new EasyPlaceholder('bankmoney + ?', $deposit),
                'money' => new EasyPlaceholder('money - ?', $_POST['deposit']),
            ],
            ['userid' => $this->player['userid']],
        );
        $this->player['bankmoney'] += $deposit;
        return [
            'type' => 'success',
            'message' => sprintf(
                self::DEPOSIT_COMPLETE,
                $this->func->money_formatter($_POST['deposit']),
                $this->func->money_formatter($fee),
                $this->func->money_formatter($deposit),
                $this->func->money_formatter($this->player['bankmoney']),
            ),
        ];
    }

    /**
     * @return array|string[]
     */
    protected function doWithdraw(): array
    {
        if ($this->player['bankmoney'] < 0) {
            return [
                'type' => 'error',
                'message' => self::NO_ACCOUNT_403,
            ];
        }
        $_POST['withdraw'] = array_key_exists('withdraw', $_POST) && (int)$_POST['withdraw'] > 0 ? (int)$_POST['withdraw'] : 0;
        if (empty($_POST['withdraw'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'withdraw amount'),
            ];
        }
        if ($_POST['withdraw'] > $this->player['bankmoney']) {
            return [
                'type' => 'error',
                'message' => self::NOT_ENOUGH_BANKED,
            ];
        }
        $this->pdo->update(
            'users',
            [
                'bankmoney' => new EasyPlaceholder('bankmoney - ?', $_POST['withdraw']),
                'money' => new EasyPlaceholder('money - ?', $_POST['withdraw']),
            ],
            ['userid' => $this->player['userid']],
        );
        $this->player['bankmoney'] -= $_POST['withdraw'];
        return [
            'type' => 'success',
            'message' => sprintf(
                self::WITHDRAW_COMPLETE,
                $this->func->money_formatter($_POST['withdraw']),
                $this->func->money_formatter($this->player['bankmoney']),
            ),
        ];
    }

    /**
     * @return string[]
     */
    protected function doOpenAccount(): array
    {
        if ($this->player['bankmoney'] > -1) {
            return [
                'type' => 'error',
                'message' => self::ACCOUNT_ALREADY_OWNED,
            ];
        }
        if ($this->player['money'] < $this->bankSettings['account_cost']) {
            return [
                'type' => 'error',
                'message' => self::NOT_ENOUGH_MONEY,
            ];
        }
        $this->pdo->update(
            'users',
            [
                'bankmoney' => 0,
                'money' => new EasyPlaceholder('money - ?', $this->bankSettings['account_cost']),
            ],
            ['userid' => $this->player['userid']],
        );
        return [
            'type' => 'success',
            'message' => sprintf(self::ACCOUNT_OPENED, $this->func->money_formatter($this->bankSettings['account_cost'])),
            'redirect' => 'bank',
        ];
    }
}
