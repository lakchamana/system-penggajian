<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

final class EmployeePasswordVault {
    private function key(): string {
        $key = base64_decode((string)($_ENV['EMPLOYEE_PASSWORD_KEY'] ?? ''), true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('Kunci password karyawan belum tersedia. Hubungi pengelola aplikasi.');
        return $key;
    }

    public function encrypt(int $accountId, string $password): string {
        $nonce = random_bytes(12);
        $encrypted = openssl_encrypt($password, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'app_users:'.$accountId, 16);
        if ($encrypted === false) throw new RuntimeException('Password belum dapat disimpan dengan aman.');
        return json_encode(['v'=>1, 'nonce'=>base64_encode($nonce), 'tag'=>base64_encode($tag), 'data'=>base64_encode($encrypted)], JSON_THROW_ON_ERROR);
    }

    public function decrypt(int $accountId, ?string $envelope): string {
        $key = $this->key();
        $payload = json_decode($envelope ?? '', true);
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1) throw new RuntimeException('Password tersimpan tidak dapat dibuka. Periksa cadangan kunci dan data akun.');
        $nonce = is_string($payload['nonce'] ?? null) ? base64_decode($payload['nonce'], true) : false;
        $tag = is_string($payload['tag'] ?? null) ? base64_decode($payload['tag'], true) : false;
        $data = is_string($payload['data'] ?? null) ? base64_decode($payload['data'], true) : false;
        if ($nonce === false || strlen($nonce) !== 12 || $tag === false || strlen($tag) !== 16 || $data === false) throw new RuntimeException('Password tersimpan tidak dapat dibuka. Periksa cadangan kunci dan data akun.');
        $password = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'app_users:'.$accountId);
        if ($password === false) throw new RuntimeException('Password tersimpan tidak dapat dibuka. Periksa cadangan kunci dan data akun.');
        return $password;
    }
}
