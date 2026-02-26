<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\BuildStateResult\BuildErr;
use Cundd\Assetic\ValueObject\BuildStateResult\BuildOk;
use Throwable;

/**
 * @template E of Throwable
 */
abstract class BuildStateResult
{
    /**
     * @use ResultTrait<BuildState,E>
     */
    use ResultTrait;

    /**
     * @param BuildState|E $inner
     */
    protected function __construct(
        protected readonly BuildState|Throwable $inner,
    ) {
    }

    /**
     * @return self<never>
     */
    public static function ok(BuildState $inner): self
    {
        return new BuildOk($inner);
    }

    /**
     * @template R of Throwable
     *
     * @param R $inner
     *
     * @return BuildErr<R>
     */
    public static function err(Throwable $inner): self
    {
        return new BuildErr($inner);
    }

    /**
     * @template R
     *
     * @param callable(BuildState): R $callback
     *
     * @return self<E>
     */
    public function map(callable $callback): self
    {
        if ($this->isOk()) {
            return static::ok($callback($this->inner));
        } else {
            return static::err($this->inner);
        }
    }
}
