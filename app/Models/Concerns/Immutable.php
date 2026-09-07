<?php

namespace App\Models\Concerns;

use LogicException;

trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(fn () => throw new LogicException(class_basename(static::class).' bersifat immutable dan tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException(class_basename(static::class).' bersifat immutable dan tidak dapat dihapus.'));
    }
}
