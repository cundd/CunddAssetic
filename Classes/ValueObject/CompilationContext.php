<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use TYPO3\CMS\Core\Site\Entity\Site;

final class CompilationContext
{
    public function __construct(
        public readonly Site $site,
        public readonly bool $isBackendUserLoggedIn,
        public readonly bool $isCliEnvironment,
    ) {
    }
}
