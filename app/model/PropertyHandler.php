<?php
declare(strict_types=1);
if (!defined('MONO_ON') || !MONO_ON) {
    exit;
}

/**
 *
 */
class PropertyHandler extends PropertyController
{
    /**
     * @param string|null $subRoute
     * @param mixed|null $id
     * @return void
     */
    public function get(?string $subRoute = null, mixed $id = null): void
    {
        match ($subRoute) {
            'buy' => $this->buyProperty($id),
            'sell' => $this->sellProperty(),
            default => $this->displayProperties(),
        };
    }

    /**
     * @param int $will
     * @return array|null
     */
    public function getHouseByWill(int $will): ?array
    {
        return $this->pdo->row(
            'SELECT * FROM houses WHERE hWILL = ? LIMIT 1',
            $will,
        );
    }
}
