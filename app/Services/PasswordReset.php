<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class PasswordReset {
    public function __construct(private PDO $db, private SmtpMailer $mailer) {}

    private function allowRequest(string $key, int $limit): bool {
        $fingerprint = hash('sha256', $key);
        $this->db->prepare('INSERT INTO password_reset_attempts (fingerprint,failures,last_attempt) VALUES (?,1,NOW())
            ON DUPLICATE KEY UPDATE failures=IF(last_attempt<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,failures+1),last_attempt=NOW()')->execute([$fingerprint]);
        $q = $this->db->prepare('SELECT failures FROM password_reset_attempts WHERE fingerprint=?');
        $q->execute([$fingerprint]);
        return (int)$q->fetchColumn() <= $limit;
    }

    public function request(string $email, string $ip): void {
        $this->mailer->assertConfigured();
        $base = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        $parts = parse_url($base);
        if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Alamat aplikasi untuk tautan email belum dikonfigurasi. Hubungi pengelola aplikasi.');
        }
        $email = strtolower(trim($email));
        if (!$this->allowRequest('ip:' . $ip, 5) || !$this->allowRequest('email:' . $email, 3)) return;
        $q = $this->db->prepare('SELECT id FROM app_users WHERE email=?');
        $q->execute([$email]);
        $id = $q->fetchColumn();
        if (!$id) return;
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT id,name,email FROM app_users WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $user = $q->fetch();
            if (!$user || strcasecmp((string)$user['email'], $email) !== 0) { $this->db->rollBack(); return; }
            $token = bin2hex(random_bytes(32));
            $this->db->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))
                ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=NOW()')->execute([$id, hash('sha256', $token)]);
            $this->mailer->sendReset($user['email'], $user['name'], $base . '/reset-password?token=' . $token);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Do not log reset links, credentials, or SMTP conversation contents.
            error_log('Password reset email failed (' . get_class($e) . '). Check SMTP configuration and connectivity.');
        }
    }

    public function valid(string $token): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
        $q = $this->db->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=? AND expires_at>NOW()');
        $q->execute([hash('sha256', $token)]);
        return (bool)$q->fetchColumn();
    }

    public function reset(string $token, string $password): bool {
        if (strlen($password) < 12 || strlen($password) > 72 || !$this->valid($token)) return false;
        $hash = hash('sha256', $token);
        $q = $this->db->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=?');
        $q->execute([$hash]);
        $id = $q->fetchColumn();
        if (!$id) return false;
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT id FROM app_users WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if (!$q->fetchColumn()) { $this->db->rollBack(); return false; }
            $q = $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id=? AND token_hash=? AND expires_at>NOW()');
            $q->execute([$id, $hash]);
            if ($q->rowCount() !== 1) { $this->db->rollBack(); return false; }
            $this->db->prepare('UPDATE app_users SET password_hash=?,session_version=session_version+1 WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $this->db->prepare("INSERT INTO audit_log (user_id,action,entity,record_key) VALUES (?,'password_reset','app_users',?)")->execute([$id, (string)$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
