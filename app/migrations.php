<?php

declare(strict_types=1);

/**
 * Runs pending schema migrations and seeds demo data.
 *
 * The CMS calls this on startup via sz_db(), so no manual SQL scripts
 * are required during development.
 */
function sz_run_migrations(\PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(191) NOT NULL,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $executed = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
    $executedMigrations = [];
    foreach ($executed as $migrationName) {
        if (is_string($migrationName)) {
            $executedMigrations[$migrationName] = true;
        }
    }

    $migrations = [
        '20260211_001_create_core_tables' => static function (\PDO $pdo): void {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS customers (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(120) NOT NULL,
                    slug VARCHAR(140) NOT NULL,
                    contact_person VARCHAR(120) DEFAULT NULL,
                    email VARCHAR(190) DEFAULT NULL,
                    status ENUM(\'active\', \'onboarding\', \'inactive\') NOT NULL DEFAULT \'active\',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_customers_slug (slug),
                    KEY idx_customers_name (name),
                    KEY idx_customers_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS roles (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    slug VARCHAR(60) NOT NULL,
                    name VARCHAR(120) NOT NULL,
                    permissions_json LONGTEXT NOT NULL,
                    is_system TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_roles_slug (slug)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    customer_id BIGINT UNSIGNED DEFAULT NULL,
                    role_id BIGINT UNSIGNED NOT NULL,
                    full_name VARCHAR(120) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    status ENUM(\'active\', \'disabled\') NOT NULL DEFAULT \'active\',
                    last_login_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_users_email (email),
                    KEY idx_users_customer_id (customer_id),
                    KEY idx_users_role_id (role_id),
                    CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS content (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    customer_id BIGINT UNSIGNED NOT NULL,
                    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                    title VARCHAR(160) NOT NULL,
                    type ENUM(\'image\', \'video\', \'text\') NOT NULL DEFAULT \'image\',
                    status ENUM(\'active\', \'planned\', \'archived\') NOT NULL DEFAULT \'planned\',
                    body_text TEXT DEFAULT NULL,
                    media_url VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_content_customer_id (customer_id),
                    KEY idx_content_status (status),
                    CONSTRAINT fk_content_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                    CONSTRAINT fk_content_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS playlists (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    customer_id BIGINT UNSIGNED NOT NULL,
                    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                    title VARCHAR(160) NOT NULL,
                    description TEXT DEFAULT NULL,
                    status ENUM(\'active\', \'planned\', \'archived\') NOT NULL DEFAULT \'planned\',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_playlists_customer_id (customer_id),
                    KEY idx_playlists_status (status),
                    CONSTRAINT fk_playlists_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                    CONSTRAINT fk_playlists_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS contact_requests (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(120) NOT NULL,
                    organization VARCHAR(120) DEFAULT NULL,
                    email VARCHAR(190) NOT NULL,
                    phone VARCHAR(30) DEFAULT NULL,
                    message TEXT NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_contact_requests_created_at (created_at),
                    KEY idx_contact_requests_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        },
        '20260211_002_seed_roles_customers_users_and_content' => static function (\PDO $pdo): void {
            $adminPermissions = [
                'customers.read',
                'customers.create',
                'customers.update',
                'customers.delete',
                'users.read',
                'users.create',
                'users.update',
                'users.delete',
                'roles.read',
                'roles.update',
                'content.read.all',
                'content.manage.all',
                'playlists.read.all',
                'playlists.manage.all',
                'settings.manage',
                'settings.update.own',
            ];
            $customerPermissions = [
                'content.read.own',
                'content.manage.own',
                'playlists.read.own',
                'playlists.manage.own',
                'settings.read.own',
                'settings.update.own',
            ];

            $roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
            if ($roleCount === 0) {
                $insertRole = $pdo->prepare(
                    'INSERT INTO roles (slug, name, permissions_json, is_system)
                    VALUES (:slug, :name, :permissions_json, :is_system)'
                );
                $insertRole->execute([
                    ':slug' => 'admin',
                    ':name' => 'Admin',
                    ':permissions_json' => json_encode($adminPermissions, JSON_UNESCAPED_SLASHES),
                    ':is_system' => 1,
                ]);
                $insertRole->execute([
                    ':slug' => 'customer',
                    ':name' => 'Klant',
                    ':permissions_json' => json_encode($customerPermissions, JSON_UNESCAPED_SLASHES),
                    ':is_system' => 1,
                ]);
            }

            $customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
            if ($customerCount === 0) {
                $insertCustomer = $pdo->prepare(
                    'INSERT INTO customers (name, slug, contact_person, email, status)
                    VALUES (:name, :slug, :contact_person, :email, :status)'
                );
                $insertCustomer->execute([
                    ':name' => 'VianenFysio',
                    ':slug' => 'vianenfysio',
                    ':contact_person' => 'S. van Dijk',
                    ':email' => 'info@vianenfysio.nl',
                    ':status' => 'active',
                ]);
                $insertCustomer->execute([
                    ':name' => 'Demo Klant A',
                    ':slug' => 'demo-klant-a',
                    ':contact_person' => 'M. Jansen',
                    ':email' => 'team@demoklanta.nl',
                    ':status' => 'active',
                ]);
                $insertCustomer->execute([
                    ':name' => 'Demo Klant B',
                    ':slug' => 'demo-klant-b',
                    ':contact_person' => 'L. Peters',
                    ':email' => 'team@demoklantb.nl',
                    ':status' => 'onboarding',
                ]);
            }

            $rolesBySlug = [];
            $roleRows = $pdo->query('SELECT id, slug FROM roles')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($roleRows as $roleRow) {
                if (!isset($roleRow['slug'], $roleRow['id'])) {
                    continue;
                }

                $rolesBySlug[(string) $roleRow['slug']] = (int) $roleRow['id'];
            }

            $customersBySlug = [];
            $customerRows = $pdo->query('SELECT id, slug FROM customers')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($customerRows as $customerRow) {
                if (!isset($customerRow['slug'], $customerRow['id'])) {
                    continue;
                }

                $customersBySlug[(string) $customerRow['slug']] = (int) $customerRow['id'];
            }

            $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($userCount === 0 && isset($rolesBySlug['admin'], $rolesBySlug['customer'])) {
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (customer_id, role_id, full_name, email, password_hash, status)
                    VALUES (:customer_id, :role_id, :full_name, :email, :password_hash, :status)'
                );

                $insertUser->execute([
                    ':customer_id' => null,
                    ':role_id' => $rolesBySlug['admin'],
                    ':full_name' => 'Platform Admin',
                    ':email' => 'admin@schermzicht.nl',
                    ':password_hash' => password_hash('Admin123!', PASSWORD_DEFAULT),
                    ':status' => 'active',
                ]);

                if (isset($customersBySlug['vianenfysio'])) {
                    $insertUser->execute([
                        ':customer_id' => $customersBySlug['vianenfysio'],
                        ':role_id' => $rolesBySlug['customer'],
                        ':full_name' => 'VianenFysio Beheer',
                        ':email' => 'manager@vianenfysio.nl',
                        ':password_hash' => password_hash('Klant123!', PASSWORD_DEFAULT),
                        ':status' => 'active',
                    ]);
                }

                if (isset($customersBySlug['demo-klant-a'])) {
                    $insertUser->execute([
                        ':customer_id' => $customersBySlug['demo-klant-a'],
                        ':role_id' => $rolesBySlug['customer'],
                        ':full_name' => 'Demo Klant A Beheer',
                        ':email' => 'beheer@demoklanta.nl',
                        ':password_hash' => password_hash('Klant123!', PASSWORD_DEFAULT),
                        ':status' => 'active',
                    ]);
                }
            }

            $contentCount = (int) $pdo->query('SELECT COUNT(*) FROM content')->fetchColumn();
            if ($contentCount === 0 && !empty($customersBySlug)) {
                $insertContent = $pdo->prepare(
                    'INSERT INTO content (customer_id, title, type, status, body_text, media_url)
                    VALUES (:customer_id, :title, :type, :status, :body_text, :media_url)'
                );

                foreach ($customersBySlug as $slug => $customerId) {
                    $insertContent->execute([
                        ':customer_id' => $customerId,
                        ':title' => 'Welkomstscherm ' . strtoupper((string) $slug),
                        ':type' => 'image',
                        ':status' => 'active',
                        ':body_text' => 'Placeholdertekst voor het welkomstscherm van deze klant.',
                        ':media_url' => 'https://placehold.co/1200x675?text=' . rawurlencode((string) $slug),
                    ]);
                    $insertContent->execute([
                        ':customer_id' => $customerId,
                        ':title' => 'Actiecampagne Februari',
                        ':type' => 'video',
                        ':status' => 'planned',
                        ':body_text' => 'Dummy video-item voor campagneplanning.',
                        ':media_url' => 'https://example.com/media/actie-februari.mp4',
                    ]);
                }
            }

            $playlistCount = (int) $pdo->query('SELECT COUNT(*) FROM playlists')->fetchColumn();
            if ($playlistCount === 0 && !empty($customersBySlug)) {
                $insertPlaylist = $pdo->prepare(
                    'INSERT INTO playlists (customer_id, title, description, status)
                    VALUES (:customer_id, :title, :description, :status)'
                );

                foreach ($customersBySlug as $slug => $customerId) {
                    $insertPlaylist->execute([
                        ':customer_id' => $customerId,
                        ':title' => 'Dagstart ' . strtoupper((string) $slug),
                        ':description' => 'Playlist voor de ochtend met dummy inhoud.',
                        ':status' => 'active',
                    ]);
                    $insertPlaylist->execute([
                        ':customer_id' => $customerId,
                        ':title' => 'Middag rotatie',
                        ':description' => 'Placeholder playlist voor middagcommunicatie.',
                        ':status' => 'planned',
                    ]);
                }
            }
        },
    ];

    $insertMigration = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
    foreach ($migrations as $migrationName => $callback) {
        if (isset($executedMigrations[$migrationName])) {
            continue;
        }

        $pdo->beginTransaction();
        try {
            $callback($pdo);
            $insertMigration->execute([':migration' => $migrationName]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw new \RuntimeException(
                sprintf('Database migratie mislukt bij "%s".', $migrationName),
                0,
                $exception
            );
        }
    }
}
