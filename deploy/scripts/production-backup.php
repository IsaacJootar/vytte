#!/opt/cpanel/ea-php83/root/usr/bin/php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

const APP_ROOT = '/home/klickit/vytte';
const BACKUP_ROOT = '/home/klickit/backups';
const EXPECTED_DATABASE = 'vytte';
const EXPECTED_HOST = '127.0.0.1';
const EXPECTED_PORT = 5433;
const PG_DUMP = '/usr/pgsql-17/bin/pg_dump';
const PG_RESTORE = '/usr/pgsql-17/bin/pg_restore';

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('This backup can only run from the command line.');
}

require APP_ROOT.'/vendor/autoload.php';

$app = require APP_ROOT.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (array) config('database.connections.pgsql');
$database = (string) ($connection['database'] ?? '');
$host = (string) ($connection['host'] ?? '');
$port = (int) ($connection['port'] ?? 0);
$username = (string) ($connection['username'] ?? '');
$password = (string) ($connection['password'] ?? '');

if ($database !== EXPECTED_DATABASE || $host !== EXPECTED_HOST || $port !== EXPECTED_PORT) {
    throw new RuntimeException(sprintf(
        'Refusing backup for unexpected PostgreSQL target %s@%s:%d/%s.',
        $username,
        $host,
        $port,
        $database,
    ));
}

foreach ([APP_ROOT, BACKUP_ROOT, PG_DUMP, PG_RESTORE] as $requiredPath) {
    if (! file_exists($requiredPath)) {
        throw new RuntimeException("Required production path is missing: {$requiredPath}");
    }
}

$timestamp = gmdate('Ymd-His');
$temporaryDirectory = BACKUP_ROOT."/.vytte-incomplete-{$timestamp}";
$finalDirectory = BACKUP_ROOT."/vytte-{$timestamp}";

if (! mkdir($temporaryDirectory, 0700) && ! is_dir($temporaryDirectory)) {
    throw new RuntimeException("Unable to create backup directory: {$temporaryDirectory}");
}

try {
    $dumpPath = $temporaryDirectory.'/database.dump';
    runProcess([
        PG_DUMP,
        '--format=custom',
        '--no-owner',
        '--no-acl',
        '--host='.$host,
        '--port='.(string) $port,
        '--username='.$username,
        '--file='.$dumpPath,
        $database,
    ], ['PGPASSWORD' => $password]);

    runProcess([PG_RESTORE, '--list', $dumpPath]);

    $archivePath = $temporaryDirectory.'/application-state.tar.gz';
    runProcess([
        '/usr/bin/tar',
        '--create',
        '--gzip',
        '--file='.$archivePath,
        '--exclude=.git',
        '--exclude=vendor',
        '--exclude=node_modules',
        '--exclude=storage/logs',
        '--exclude=storage/framework',
        '--exclude=public/build',
        '--directory='.APP_ROOT,
        '.',
    ]);

    $environmentPath = $temporaryDirectory.'/.env';
    if (! copy(APP_ROOT.'/.env', $environmentPath) || ! chmod($environmentPath, 0600)) {
        throw new RuntimeException('Unable to preserve the production environment file.');
    }

    $commit = trim(runProcess([
        '/usr/sbin/runuser',
        '--user',
        'klickit',
        '--',
        '/usr/bin/git',
        '-C',
        APP_ROOT,
        'rev-parse',
        'HEAD',
    ]));
    $manifest = [
        'created_at_utc' => gmdate(DATE_ATOM),
        'application' => APP_ROOT,
        'git_commit' => $commit,
        'database' => [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
        ],
        'files' => [
            'database.dump' => hash_file('sha256', $dumpPath),
            'application-state.tar.gz' => hash_file('sha256', $archivePath),
            '.env' => hash_file('sha256', $environmentPath),
        ],
    ];

    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    if (file_put_contents($temporaryDirectory.'/manifest.json', $manifestJson, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the backup manifest.');
    }

    if (! rename($temporaryDirectory, $finalDirectory)) {
        throw new RuntimeException('Unable to finalize the backup directory.');
    }

    fwrite(STDOUT, "Verified Vytte backup created at {$finalDirectory}".PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    fwrite(STDERR, "Incomplete files remain at {$temporaryDirectory} for inspection.".PHP_EOL);
    exit(1);
}

/**
 * @param  list<string>  $command
 * @param  array<string, string>  $environment
 */
function runProcess(array $command, array $environment = []): string
{
    $process = new Process($command, APP_ROOT, $environment, null, 900);
    $process->mustRun();

    return $process->getOutput();
}
