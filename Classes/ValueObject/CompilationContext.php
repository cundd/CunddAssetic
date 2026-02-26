<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\Configuration;
use TYPO3\CMS\Core\Site\Entity\Site;

final class CompilationContext
{
    public function __construct(
        public readonly Site $site,
        public readonly bool $isBackendUserLoggedIn,
        public readonly bool $isCliEnvironment,
        public readonly bool $forceCompilation,
    ) {
    }

    public function hasAccessToDevelopmentFeatures(
        Configuration $configuration,
    ): bool {
        return $this->isCliEnvironment
            || $this->isBackendUserLoggedIn
            || $configuration->allowDeveloperFeaturesWithoutLogin;
    }

    /**
     * Return if LiveReload is enabled and if the current client is allowed to
     * use it
     */
    public function shouldLoadLiveReload(Configuration $configuration): bool
    {
        if (!$configuration->liveReloadConfiguration->isEnabled) {
            return false;
        }

        // If Live Reload is enabled check the current callers permissions
        return $this->isBackendUserLoggedIn
            || $configuration->allowDeveloperFeaturesWithoutLogin;
    }
}
