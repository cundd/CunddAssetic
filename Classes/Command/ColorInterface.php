<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

interface ColorInterface
{
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // ESCAPE CHARACTER
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * The escape character
     */
    const ESCAPE = "\033";

    /**
     * The escape character
     */
    const SIGNAL = self::ESCAPE;

    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // COLORS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Bold color red
     */
    const BOLD_RED = "[1;31m";

    /**
     * Bold color green
     */
    const BOLD_GREEN = "[1;32m";

    /**
     * Bold with color blue
     */
    const BOLD_BLUE = "[1;34m";

    /**
     * Bold color cyan
     */
    const BOLD_CYAN = "[1;36m";

    /**
     * Bold color yellow
     */
    const BOLD_YELLOW = "[1;33m";

    /**
     * Bold color magenta
     */
    const BOLD_MAGENTA = "[1;35m";

    /**
     * Bold color white
     */
    const BOLD_WHITE = "[1;37m";

    /**
     * Normal
     */
    const NORMAL = "[0m";

    /**
     * Color black
     */
    const BLACK = "[0;30m";

    /**
     * Color red
     */
    const RED = "[0;31m";

    /**
     * Color green
     */
    const GREEN = "[0;32m";

    /**
     * Color yellow
     */
    const YELLOW = "[0;33m";

    /**
     * Color blue
     */
    const BLUE = "[0;34m";

    /**
     * Color cyan
     */
    const CYAN = "[0;36m";

    /**
     * Color magenta
     */
    const MAGENTA = "[0;35m";

    /**
     * Color brown
     */
    const BROWN = "[0;33m";

    /**
     * Color gray
     */
    const GRAY = "[0;37m";

    /**
     * Bold
     */
    const BOLD = "[1m";

    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // UNDERSCORE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Underscored
     */
    const UNDERSCORE = "[4m";

    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // REVERSE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Reversed
     */
    const REVERSE = "[7m";

    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // MACROS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Send a sequence to turn attributes off
     */
    const SIGNAL_ATTRIBUTES_OFF = "\033[0m";
}
