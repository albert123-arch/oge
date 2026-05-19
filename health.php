<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

function line_out(string $label, bool $ok, string $details = ''): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if ($details !== '') {
        echo ' - ' . $details;
    }
    echo PHP_EOL;
}

line_out('PHP runtime', true, PHP_VERSION);

$configPath = __DIR__ . '/includes/config.php';
line_out('Config file', is_file($configPath), 'includes/config.php');

if (!is_file($configPath)) {
    exit(1);
}

require_once $configPath;

$requiredConstants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SITE_NAME', 'SITE_URL'];
$missingConstants = [];
foreach ($requiredConstants as $constant) {
    if (!defined($constant) || constant($constant) === '') {
        $missingConstants[] = $constant;
    }
}

line_out('Config constants', empty($missingConstants), empty($missingConstants) ? 'present' : implode(', ', $missingConstants));

if (!empty($missingConstants)) {
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli->set_charset('utf8mb4');
    line_out('Database connection', true, DB_NAME);

    $requiredTables = ['oge_users', 'oge_questions', 'oge_task_types', 'oge_topics'];
    foreach ($requiredTables as $table) {
        $stmt = $mysqli->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
        );
        $dbName = DB_NAME;
        $stmt->bind_param('ss', $dbName, $table);
        $stmt->execute();
        $result = $stmt->get_result();
        line_out('Table ' . $table, $result->num_rows > 0);
        $stmt->close();
    }
} catch (Throwable $exception) {
    line_out('Database connection', false, $exception->getMessage());
    exit(1);
}
