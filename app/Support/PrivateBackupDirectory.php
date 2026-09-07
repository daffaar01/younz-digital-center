<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class PrivateBackupDirectory
{
    public static function secure(string $path): void
    {
        if (is_link($path) || (! is_dir($path) && ! mkdir($path, 0700, true))) {
            throw new RuntimeException('Cannot create private backup directory.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            if (! chmod($path, 0700)) {
                throw new RuntimeException('Cannot protect backup directory.');
            }

            return;
        }

        $identity = new Process(['whoami', '/user', '/fo', 'csv', '/nh']);
        $identity->setTimeout(15)->run();
        if (! $identity->isSuccessful() || ! preg_match('/S-1-5-[0-9-]+/', $identity->getOutput(), $match)) {
            throw new RuntimeException('Cannot identify backup directory owner.');
        }
        $command = ['icacls', str_replace('/', '\\', (string) realpath($path)), '/inheritance:r', '/grant:r'];
        foreach (array_unique([$match[0], 'S-1-5-18', 'S-1-5-32-544']) as $sid) {
            $command[] = '*'.$sid.':(OI)(CI)F';
        }
        $acl = new Process($command);
        $acl->setTimeout(15)->run();
        if (! $acl->isSuccessful()) {
            throw new RuntimeException('Cannot protect backup directory ACL.');
        }
    }
}
