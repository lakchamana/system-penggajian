<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class AuthSchema {
    public static function migrate(PDO $db): void {
        $columns = $db->query('SHOW COLUMNS FROM app_users')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('email', $columns, true)) {
            $db->exec('ALTER TABLE app_users ADD COLUMN email VARCHAR(254) NULL AFTER name, ADD UNIQUE KEY uq_app_users_email (email)');
        }
        foreach ([
            'role' => "ENUM('superadmin','karyawan') NOT NULL DEFAULT 'superadmin'",
            'employee_id' => 'BIGINT UNSIGNED NULL UNIQUE',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'must_change_password' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'password_encrypted' => 'TEXT NULL',
        ] as $column => $definition) {
            if (!in_array($column, $columns, true)) $db->exec("ALTER TABLE app_users ADD COLUMN `$column` $definition");
        }
        $foreignKey = $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='app_users' AND CONSTRAINT_NAME='fk_app_user_employee'")->fetchColumn();
        if (!$foreignKey) $db->exec('ALTER TABLE app_users ADD CONSTRAINT fk_app_user_employee FOREIGN KEY (employee_id) REFERENCES karyawan(id) ON DELETE RESTRICT');
        $db->exec('CREATE TABLE IF NOT EXISTS password_reset_tokens (
            user_id BIGINT UNSIGNED PRIMARY KEY,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $db->exec('CREATE TABLE IF NOT EXISTS password_reset_attempts (
            fingerprint CHAR(64) PRIMARY KEY,
            failures INT UNSIGNED NOT NULL DEFAULT 0,
            last_attempt DATETIME NOT NULL
        ) ENGINE=InnoDB');
    }
}
