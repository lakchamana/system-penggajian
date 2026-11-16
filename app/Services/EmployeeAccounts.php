<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class EmployeeAccounts {
    public function __construct(private PDO $db) {}

    public static function initialPassword(): string {
        $password = (string)($_ENV['EMPLOYEE_INITIAL_PASSWORD'] ?? '');
        if (strlen($password)<8 || strlen($password)>72) throw new RuntimeException('Password awal karyawan belum dikonfigurasi (8–72 karakter).');
        return $password;
    }

    public function create(int $employeeId): int {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM karyawan WHERE id=? FOR UPDATE'); $q->execute([$employeeId]); $employee = $q->fetch();
            if (!$employee) throw new InvalidArgumentException('Karyawan tidak ditemukan.');
            $q = $this->db->prepare('SELECT id FROM app_users WHERE employee_id=? OR username=? FOR UPDATE'); $q->execute([$employeeId,$employee['nip']]);
            if ($q->fetchColumn()) throw new InvalidArgumentException('Karyawan atau nomor induk ini sudah memiliki akun.');
            $password = self::initialPassword();
            $q = $this->db->prepare("INSERT INTO app_users(username,name,password_hash,role,employee_id,is_active,must_change_password) VALUES(?,?,?,'karyawan',?,1,1)");
            $q->execute([$employee['nip'],$employee['nama'],password_hash($password,PASSWORD_DEFAULT),$employeeId]);
            $id = (int)$this->db->lastInsertId();
            $encrypted = (new EmployeePasswordVault())->encrypt($id,$password);
            $this->db->prepare('UPDATE app_users SET password_encrypted=? WHERE id=?')->execute([$encrypted,$id]);
            if ($ownsTransaction) $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function changePassword(int $id, int $sessionVersion, string $current, string $password, string $confirmation): int {
        if (strlen($password)<8 || strlen($password)>72 || str_contains($password,"\0")) throw new InvalidArgumentException('Gunakan kata sandi baru sepanjang 8–72 karakter.');
        if ($password !== $confirmation) throw new InvalidArgumentException('Konfirmasi kata sandi baru belum sama.');
        if (hash_equals(self::initialPassword(),$password)) throw new InvalidArgumentException('Kata sandi baru harus berbeda dari password awal bersama.');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare("SELECT * FROM app_users WHERE id=? AND role='karyawan' FOR UPDATE"); $q->execute([$id]); $user = $q->fetch();
            if (!$user || !$user['is_active'] || (int)$user['session_version']!==$sessionVersion) throw new InvalidArgumentException('Akun berubah. Silakan masuk kembali.');
            if (!password_verify($current,$user['password_hash'])) throw new InvalidArgumentException('Kata sandi saat ini tidak sesuai.');
            if (password_verify($password,$user['password_hash'])) throw new InvalidArgumentException('Kata sandi baru harus berbeda dari kata sandi saat ini.');
            $encrypted = (new EmployeePasswordVault())->encrypt($id,$password);
            $version = $sessionVersion+1;
            $this->db->prepare('UPDATE app_users SET password_hash=?,password_encrypted=?,must_change_password=0,session_version=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$encrypted,$version,$id]);
            $this->db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);
            $this->db->prepare("INSERT INTO audit_log(user_id,action,entity,record_key) VALUES(?,'change_password','app_users',?)")->execute([$id,(string)$id]);
            $this->db->commit();
            return $version;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
