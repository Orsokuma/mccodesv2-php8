<?php
declare(strict_types=1);

use ParagonIE\EasyDB\EasyPlaceholder;

if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class CrystalMarketController extends CommonObjects
{
    private const string LISTING_UPSERTED = 'You\'ve added %s crystal%s to the Crystal Market at %s each (total: %s).';

    /**
     * @return array
     * @throws Throwable
     */
    protected function doUpsertListing(): array
    {
        $nums = ['amount', 'price'];
        foreach ($nums as $num) {
            $_POST[$num] = array_key_exists($num, $_POST) && (int)$_POST[$num] > 0 ? (int)$_POST[$num] : null;
            if (empty($_POST[$num])) {
                return [
                    'type' => 'error',
                    'message' => sprintf(self::MISSED_REQUIRED_FIELD, $num),
                ];
            }
        }
        if ($_POST['amount'] > $this->player['crystals']) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_ENOUGH, 'crystals'),
            ];
        }
        $existing = $this->pdo->row(
            'SELECT cmID FROM crystalmarket WHERE cmADDER = ? AND cmPRICE = ?',
            $this->player['userid'],
            $_POST['price'],
        );
        $save     = function () use ($existing) {
            if ($existing) {
                $this->pdo->update(
                    'crystalmarket',
                    ['cmQTY' => new EasyPlaceholder('cmQTY + ?', $_POST['amount'])],
                    ['cmID' => $existing['cmID']],
                );
            } else {
                $this->pdo->insert(
                    'crystalmarket',
                    [
                        'cmQTY' => $_POST['amount'],
                        'cmPRICE' => $_POST['price'],
                        'cmADDER' => $this->player['userid'],
                    ],
                );
            }
            $this->pdo->update(
                'users',
                ['crystals' => new EasyPlaceholder('crystals - ?', $_POST['amount'])],
                ['userid' => $this->player['userid']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(
                self::LISTING_UPSERTED,
                number_format($_POST['amount']),
                $_POST['amount'] === 1 ? '' : 's',
                $this->func->money_formatter($_POST['price']),
                $this->func->money_formatter($_POST['price'] * $_POST['amount']),
            ),
        ];
    }

    /**
     * @param int|null $id
     * @return array
     * @throws Throwable
     */
    protected function doRemoveListing(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row) || $row['cmADDER'] !== $this->player['userid']) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'listing'),
            ];
        }
        $save = function () use ($row) {
            $this->pdo->delete(
                'crystalmarket',
                ['cmID' => $row['cmID']],
            );
            $this->pdo->update(
                'users',
                ['crystals' => new EasyPlaceholder('crystals + ?', $row['cmQTY'])],
                ['userid' => $this->player['userid']],
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(self::OBJECT_DELETED, 'listing'),
        ];
    }

    /**
     * @throws Throwable
     */
    protected function doPurchaseListing(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'listing'),
            ];
        }
        if ($row['cmADDER'] === $this->player['userid']) {
            return [
                'type' => 'error',
                'message' => 'You can\'t purchase your own listing.',
            ];
        }
        $_POST['amount'] = array_key_exists('amount', $_POST) && (int)$_POST['amount'] > 0 ? (int)$_POST['amount'] : null;
        if (empty($_POST['amount'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'amount'),
            ];
        }
        if ($_POST['amount'] > $row['cmQTY']) {
            return [
                'type' => 'error',
                'message' => 'There aren\'t that many crystals on this listing.',
            ];
        }
        $total = $_POST['amount'] * $row['cmPRICE'];
        if ($total > $this->player['money']) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_ENOUGH, 'money'),
            ];
        }
        $save = function () use ($row, $total) {
            if ($_POST['amount'] === $row['cmQTY']) {
                $this->pdo->delete(
                    'crystalmarket',
                    ['cmID' => $row['cmID']],
                );
            } else {
                $this->pdo->update(
                    'crystalmarket',
                    ['cmQTY' => new EasyPlaceholder('cmQTY - ?', $_POST['amount'])],
                    ['cmID' => $row['cmID']],
                );
            }
            $this->pdo->update(
                'users',
                [
                    'money' => new EasyPlaceholder('money - ?', $total),
                    'crystals' => new EasyPlaceholder('crystals + ?', $_POST['amount']),
                ],
                ['userid' => $this->player['userid']],
            );
            $this->pdo->update(
                'users',
                ['money' => new EasyPlaceholder('money + ?', $total)],
                ['userid' => $row['cmADDER']],
            );
            $this->func->event_add(
                $row['cmADDER'],
                '<a href="/profile/%u">%s</a> [%u] purchased ' . ($_POST['amount'] === $row['cmQTY'] ? 'all of' : number_format($_POST['amount']) . ' crystal' . ($_POST['amount'] === 1 ? '' : 's') . ' from') . ' your Crystal Market listing for ' . $this->func->money_formatter($total),
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(
                'You\'ve purchased %s crystal%s from the Crystal Market at %s each (%s total)',
                number_format($_POST['amount']),
                $_POST['amount'] === 1 ? '' : 's',
                $this->func->money_formatter($row['cmPRICE']),
                $this->func->money_formatter($total),
            ),
        ];
    }

    /**
     * @throws Throwable
     */
    protected function doPurchaseAsGift(?int $id): array
    {
        if (empty($id)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_OBJECT, 'listing'),
            ];
        }
        $row = $this->pdo->row(
            'SELECT * FROM crystalmarket WHERE cmID = ?',
            $id,
        );
        if (empty($row)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXIST_OR_NOT_YOURS, 'listing'),
            ];
        }
        if ($row['cmADDER'] === $this->player['userid']) {
            return [
                'type' => 'error',
                'message' => 'You can\'t purchase your own listing.',
            ];
        }
        $_POST['amount'] = array_key_exists('amount', $_POST) && (int)$_POST['amount'] > 0 ? (int)$_POST['amount'] : null;
        if (empty($_POST['amount'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::MISSED_REQUIRED_FIELD, 'amount'),
            ];
        }
        if ($_POST['amount'] > $row['cmQTY']) {
            return [
                'type' => 'error',
                'message' => 'There aren\'t that many crystals on this listing.',
            ];
        }
        $total = $_POST['amount'] * $row['cmPRICE'];
        if ($total > $this->player['money']) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_ENOUGH, 'crystals'),
            ];
        }
        $_POST['user'] = array_key_exists('user', $_POST) && (int)$_POST['user'] > 0 ? (int)$_POST['user'] : null;
        if (empty($_POST['user'])) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'user'),
            ];
        }
        $recipient = $this->pdo->cell(
            'SELECT username FROM users WHERE userid = ?',
            $_POST['user'],
        );
        if (empty($recipient)) {
            return [
                'type' => 'error',
                'message' => sprintf(self::NOT_EXISTS, 'user'),
            ];
        }
        $save = function () use ($row, $total) {
            if ($_POST['amount'] === $row['cmQTY']) {
                $this->pdo->delete(
                    'crystalmarket',
                    ['cmID' => $row['cmID']],
                );
            } else {
                $this->pdo->update(
                    'crystalmarket',
                    ['cmQTY' => new EasyPlaceholder('cmQTY - ?', $total)],
                    ['cmID' => $row['cmID']],
                );
            }
            $this->pdo->update(
                'users',
                ['money' => new EasyPlaceholder('money - ?', $total)],
                ['userid' => $this->player['userid']],
            );
            $this->pdo->update(
                'users',
                ['money' => new EasyPlaceholder('money + ?', $total)],
                ['userid' => $row['cmADDER']],
            );
            $this->pdo->update(
                'users',
                ['crystals' => new EasyPlaceholder('crystals + ?', $_POST['amount'])],
                ['userid' => $_POST['user']],
            );
            $this->func->event_add(
                $row['cmADDER'],
                sprintf(
                    '<a href="/profile/%u">%s</a> [%u] purchased %s crystal%s for %s',
                    $this->player['userid'],
                    $this->player['username'],
                    $this->player['userid'],
                    number_format($_POST['amount']),
                    $_POST['amount'] === 1 ? '' : 's',
                    $this->func->money_formatter($total),
                ),
            );
            $this->func->event_add(
                $_POST['user'],
                sprintf(
                    '<a href="/profile/%u">%s</a> [%u] purchased %s crystal%s from the Crystal Market and gifted them to you!%s',
                    $this->player['userid'],
                    $this->player['username'],
                    $this->player['userid'],
                    number_format($_POST['amount']),
                    $_POST['amount'] === 1 ? '' : 's',
                    !empty($_POST['message']) ? ' They left this message for you: ' . $_POST['message'] : '',
                ),
            );
        };
        $this->pdo->tryFlatTransaction($save);
        return [
            'type' => 'success',
            'message' => sprintf(
                'You\'ve purchased %s crystal%s for %s and sent them to <a href="/profile/%u">%s</a> [%u]',
                number_format($_POST['amount']),
                $_POST['amount'] === 1 ? '' : 's',
                $this->func->money_formatter($total),
                $_POST['user'],
                $recipient,
                $_POST['user'],
            ),
        ];
    }
}
