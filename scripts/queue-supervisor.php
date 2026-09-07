<?php

// Standalone supervisor: never boot Laravel or load deployment credentials here.
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Process\Process;

$state = $argv[2] ?? '';
if ($state === '' || ! is_dir($state)) {
    fwrite(STDERR, "An existing private state directory is required.\n");
    exit(2);
}
$stop = $state.'/queue-supervisor.stop';
$lockPath = $state.'/queue-supervisor.lock';
if (($argv[1] ?? '') === 'stop') {
    file_put_contents($stop, 'stop');
    exit(0);
}
if (($argv[1] ?? '') !== 'run') exit(2);
$lock = fopen($lockPath, 'c');
if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) exit(3);
if (is_file($stop)) unlink($stop);
$command = [PHP_BINARY, dirname(__DIR__).'/artisan', 'queue:work', 'database', '--queue=default', '--sleep=2', '--tries=3', '--timeout=180', '--max-time=200'];
// Test fixtures may replace the command only through an explicit CLI mode.
if (($argv[3] ?? '') === '--fixture') {
    $command = array_slice($argv, 4);
    if ($command === []) exit(2);
}
try {
    while (! is_file($stop)) {
        $process = new Process($command, dirname(__DIR__));
        $process->setTimeout(null);
        $process->start(function ($type, $buffer): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
        });
        // Do not kill an active financial request. max-time lets Laravel exit between jobs.
        while ($process->isRunning()) {
            usleep(100000);
        }
        $code = $process->getExitCode();
        fwrite(STDERR, "Queue child exited: {$code}\n");
        for ($tick = 0; $tick < 20 && ! is_file($stop); $tick++) usleep(100000);
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
