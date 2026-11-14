<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $options = getopt('', ['username:', 'name:', 'email:']);
    $username = trim((string)($options['username'] ?? ''));
    $name = trim((string)($options['name'] ?? ''));
    $email = strtolower(trim((string)($options['email'] ?? '')));
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || $name === '' || mb_strlen($name) > 150 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Gunakan: php scripts/create-user.php --username=ID --name="Nama lengkap" --email=alamat@example.com');
    }
    $db = App\Core\Database::connection();
    App\Services\AuthSchema::migrate($db);
    $password = bin2hex(random_bytes(12));
    $db->prepare('INSERT INTO app_users (username,name,email,password_hash) VALUES (?,?,?,?)')->execute([$username, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Akun dibuat.\nID pengguna: $username\nEmail: $email\nKata sandi sementara: $password\nUbah kata sandi melalui Profil Saya setelah masuk.\n";
} catch (PDOException $e) {
    fwrite(STDERR, $e->getCode() === '23000' ? "ID pengguna atau email sudah dipakai. Akun lama tidak diubah.\n" : "Pembuatan akun gagal. Periksa koneksi dan skema database.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
