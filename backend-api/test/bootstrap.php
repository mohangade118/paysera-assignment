<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$runsPhpunitCli = PHP_SAPI === 'cli' && array_filter(
    $_SERVER['argv'] ?? [],
    static fn ($arg): bool => is_string($arg) && str_contains($arg, 'phpunit'),
);

if ($runsPhpunitCli) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
    putenv('APP_ENV=test');
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($runsPhpunitCli) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
    putenv('APP_ENV=test');
}

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}

if ($runsPhpunitCli) {
    $jwtDir = dirname(__DIR__).'/test/fixtures/jwt';
    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0775, true);
    }
    $privatePath = $jwtDir.'/private.pem';
    $publicPath = $jwtDir.'/public.pem';
    if (!is_file($privatePath) || !is_file($publicPath)) {
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($config);
        if (false === $key) {
            throw new RuntimeException('Could not generate JWT test keys: openssl_pkey_new() failed.');
        }
        if (!openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Could not export JWT test private key.');
        }
        $details = openssl_pkey_get_details($key);
        if (false === $details || !isset($details['key'])) {
            throw new RuntimeException('Could not read JWT test public key material.');
        }
        file_put_contents($privatePath, $privateKey);
        file_put_contents($publicPath, $details['key']);
    }
}

/**
 * Ensure test database exists inside docker-compose MySQL.
 *
 * The assignment's docker-compose creates only the main DB, but integration tests
 * use `DATABASE_URL` from `.env.test` (typically `*_test`).
 */
if (($_SERVER['APP_ENV'] ?? null) === 'test') {
    $databaseUrl = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '');
    $parts = parse_url($databaseUrl);

    $dbName = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
    $dbUser = $parts['user'] ?? null;
    $dbPass = $parts['pass'] ?? null;
    $dbHost = $parts['host'] ?? null;
    $dbPort = (int) ($parts['port'] ?? 3306);

    if ($dbName !== '' && is_string($dbUser) && is_string($dbPass) && is_string($dbHost)) {
        $rootDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
        $rootPassword = (string) (getenv('MYSQL_ROOT_PASSWORD') ?: 'root');

        try {
            $pdo = null;
            $lastException = null;
            for ($i = 0; $i < 15; $i++) {
                try {
                    $pdo = new PDO($rootDsn, 'root', $rootPassword, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    break;
                } catch (Throwable $e) {
                    $lastException = $e;
                    usleep(250_000);
                }
            }

            if (!$pdo instanceof PDO) {
                throw $lastException ?? new RuntimeException('Could not connect to MySQL as root.');
            }

            // Doctrine tests expect the DB to exist and the app user to have rights.
            // Some configs also append dbname_suffix `_test`, so create both possibilities.
            $dbNamesToEnsure = array_values(array_unique(array_filter([
                $dbName,
                str_ends_with($dbName, '_test') ? null : $dbName.'_test',
            ])));

            foreach ($dbNamesToEnsure as $name) {
                $quotedDb = '`'.str_replace('`', '``', $name).'`';
                $pdo->exec('CREATE DATABASE IF NOT EXISTS '.$quotedDb.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }

            $quotedUser = $pdo->quote($dbUser);
            $quotedPass = $pdo->quote($dbPass);

            // Create user if missing, and ensure password is correct.
            $pdo->exec('CREATE USER IF NOT EXISTS '.$quotedUser.'@\'%\' IDENTIFIED BY '.$quotedPass);
            $pdo->exec('ALTER USER '.$quotedUser.'@\'%\' IDENTIFIED BY '.$quotedPass);

            foreach ($dbNamesToEnsure as $name) {
                $quotedDb = '`'.str_replace('`', '``', $name).'`';
                $pdo->exec('GRANT ALL PRIVILEGES ON '.$quotedDb.'.* TO '.$quotedUser.'@\'%\'');
            }

            $pdo->exec('FLUSH PRIVILEGES');
        } catch (Throwable) {
            // If MySQL isn't reachable yet, let PHPUnit fail with the original error;
            // this bootstrap is best-effort and shouldn't hide failures.
        }
    }
}
