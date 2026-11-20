<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ConsumptionPolicy
{
    public const KEY = 'consumption_policy';

    /** @return array{rate: string, departments: list<string>}|null */
    public static function load(PDO $db, bool $lock = false): ?array
    {
        $query = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key=?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([self::KEY]);
        $stored = $query->fetchColumn();
        if ($stored === false) return null;

        $policy = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
        $rate = $policy['rate'] ?? null;
        $departments = $policy['departments'] ?? null;
        if (!is_string($rate) || !preg_match('/^[0-9]{1,18}$/D', $rate)
            || !is_array($departments) || !array_is_list($departments)
            || array_filter($departments, 'is_string') !== $departments) {
            throw new RuntimeException('Pengaturan tarif konsumsi tidak valid.');
        }
        return ['rate' => $rate, 'departments' => $departments];
    }

    /** @param array{rate: string, departments: list<string>} $policy */
    public static function save(PDO $db, array $policy): void
    {
        $value = json_encode($policy, JSON_THROW_ON_ERROR);
        $db->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?')
            ->execute([self::KEY, $value, $value]);
    }
}
