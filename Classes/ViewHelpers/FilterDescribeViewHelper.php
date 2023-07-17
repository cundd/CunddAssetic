<?php

declare(strict_types=1);

namespace Cundd\Assetic\ViewHelpers;

use Assetic\Contracts\Filter\FilterInterface;
use Assetic\Filter\BaseProcessFilter;
use Closure;
use ReflectionException;
use ReflectionProperty;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function get_class;

class FilterDescribeViewHelper extends AbstractViewHelper
{
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('filter', FilterInterface::class, 'Filter to describe', false);
    }

    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        /** @var FilterInterface $filter */
        $filter = $arguments['filter'] ?? $renderChildrenClosure();

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

        return $value ? (string)$value : null;
    }

}
