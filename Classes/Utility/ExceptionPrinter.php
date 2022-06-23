<?php
declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use Assetic\Exception\FilterException;
use Throwable;

/**
 * Class to display exceptions
 */
class ExceptionPrinter
{
    /**
     * Print the given exception to the screen
     *
     * @param FilterException|\Exception $exception
     */
    public function printException(Throwable $exception):string
    {
        $i = 0;
        $trace = '';
        $backtrace = $exception->getTrace();

        $heading = 'Caught Assetic error #' . $exception->getCode() . ': ' . $exception->getMessage();
        while ($step = current($backtrace)) {
            $trace .= '#' . $i . ': ' . $step['file'] . '(' . $step['line'] . '): ';
            if (isset($step['class'])) {
                $trace .= $step['class'] . $step['type'];
            }
            $trace .= $step['function'] . '(arguments: ' . count($step['args']) . ')' . PHP_EOL;
            next($backtrace);
            $i++;
        }
        $boxStyles = $this->getBoxStyle();
        $fontStyle = $this->getFontStyle();
        $traceFontStyle = $this->getTraceFontStyle();

        return sprintf(
            "<div style='%s'><div style='%s'>%s</div><code style='%s'>%s</code></div>",
            $boxStyles,
            $fontStyle,
            $heading,
            $traceFontStyle,
            $trace
        );
    }

    /**
     * @return string
     */
    private function getFontStyle(): string
    {
        $fontStyle = [
            'font-family' => 'Hack, Meslo, Menlo, monospace',
            'font-size'   => '14px',
            'white-space' => 'pre',
            'color'       => 'white',
        ];

        array_walk(
            $fontStyle,
            function (&$value, $key) {
                $value = $key . ':' . $value;
            }
        );

        return implode(';', $fontStyle);
    }

    /**
     * @return string
     */
    private function getTraceFontStyle(): string
    {
        $fontStyle = [
            'font-family' => 'Hack, Meslo, Menlo, monospace',
            'font-size'   => '11px',
            'white-space' => 'pre',
            'color'       => 'white',
        ];

        array_walk(
            $fontStyle,
            function (&$value, $key) {
                $value = $key . ':' . $value;
            }
        );

        return implode(';', $fontStyle);
    }

    /**
     * @return string
     */
    private function getBoxStyle(): string
    {
        $boxStyles = [
            'width'      => '100%',
            'overflow'   => 'scroll',
            'border'     => '1px solid #A90000',
            'background' => '#A90000',
            'padding'    => '5px',
            'box-sizing' => 'border-box',
            'box-shadow' => 'inset 0 0 4px rgba(0, 0, 0, 0.3)',
            'z-index'    => '1000',
            'position'   => 'relative',
        ];
        array_walk(
            $boxStyles,
            function (&$value, $key) {
                $value = $key . ':' . $value;
            }
        );

        return implode(';', $boxStyles);
    }
}
