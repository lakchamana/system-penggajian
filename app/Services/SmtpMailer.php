<?php
declare(strict_types=1);
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpMailer {
    public function assertConfigured(): void {
        $port = filter_var($_ENV['SMTP_PORT'] ?? '587', FILTER_VALIDATE_INT);
        $encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
        $auth = filter_var($_ENV['SMTP_AUTH'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        if (!class_exists(PHPMailer::class) || empty($_ENV['SMTP_HOST']) || !$port || $port < 1 || $port > 65535 ||
            !in_array($encryption, ['tls', 'ssl', 'none'], true) ||
            !filter_var($_ENV['MAIL_FROM_ADDRESS'] ?? '', FILTER_VALIDATE_EMAIL) ||
            ($auth && (empty($_ENV['SMTP_USERNAME']) || empty($_ENV['SMTP_PASSWORD'])))) {
            throw new RuntimeException('Pengiriman email belum dikonfigurasi. Hubungi pengelola aplikasi.');
        }
    }

    public function sendReset(string $email, string $name, string $link): void {
        $this->assertConfigured();
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $mail->SMTPAuth = filter_var($_ENV['SMTP_AUTH'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = match ($_ENV['SMTP_ENCRYPTION'] ?? 'tls') {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'none' => '',
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME'] ?? 'Mutiara Hikmah');
        $mail->addAddress($email, $name);
        $mail->Subject = 'Atur ulang kata sandi Penggajian Mutiara Hikmah';
        $mail->Body = "Halo $name,\n\nUntuk mengatur ulang kata sandi, buka tautan berikut:\n$link\n\nTautan hanya berlaku 30 menit dan dapat digunakan satu kali.\nJika Anda tidak meminta perubahan ini, abaikan email ini.\n\nYayasan Mutiara Hikmah";
        $mail->send();
    }
}
