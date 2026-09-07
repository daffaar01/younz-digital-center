<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class QueueSupervisorTest extends TestCase
{
    public function test_supervisor_restarts_clean_and_error_exits_and_stops_without_killing_child(): void
    {
        $dir = sys_get_temp_dir().'/younz-supervisor-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $fixture = $dir.'/child.php';
        file_put_contents($fixture, '<?php $p=$argv[1]; $n=is_file($p)?(int)file_get_contents($p):0; file_put_contents($p,(string)($n+1)); if($n>=2) file_put_contents(dirname($p)."/queue-supervisor.stop","stop"); exit($n===1?7:0);');
        $process = new Process([PHP_BINARY, base_path('scripts/queue-supervisor.php'), 'run', $dir, '--fixture', PHP_BINARY, $fixture, $dir.'/count']);
        $process->setTimeout(20);
        try {
            $process->mustRun();
            $this->assertSame('3', file_get_contents($dir.'/count'));
            $this->assertStringContainsString('Queue child exited: 0', $process->getErrorOutput());
            $this->assertStringContainsString('Queue child exited: 7', $process->getErrorOutput());
        } finally {
            foreach (glob($dir.'/*') as $file) unlink($file);
            rmdir($dir);
        }
    }
}
