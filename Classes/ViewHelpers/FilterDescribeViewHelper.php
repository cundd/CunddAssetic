<?php

declare(strict_types=1);

namespace Cundd\Assetic\ViewHelpers;

use Assetic\Contracts\Filter\FilterInterface;
use Assetic\Filter\BaseProcessFilter;
use ReflectionException;
use ReflectionProperty;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function get_class;

class FilterDescribeViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('filter', FilterInterface::class, 'Filter to describe', false);
    }

    public function render(): string
    {
        /** @var FilterInterface $filter */
        $filter = $this->arguments['filter'] ?? $this->renderChildren();

        if ($filter instanceof BaseProcessFilter) {
            $binaryPath = static::extractBinaryPath($filter);

            return get_class($filter) . '(' . $binaryPath . ')';
        }

        return get_class($filter);
    }

    private static function extractBinaryPath(BaseProcessFilter $filter): ?string
    {
        try {
            $reflectionProperty = new ReflectionProperty($filter, 'binaryPath');
        } catch (ReflectionException $_) {
            return null;
        }
        $reflectionProperty->setAccessible(true);
        $value = $reflectionProperty->getValue($filter);

        return $value ? (string) $value : null;
    }
}
