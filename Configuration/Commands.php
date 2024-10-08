<?php

return [
    'assetic:compile' => [
        'class' => Cundd\Assetic\Command\CompileCommand::class,
    ],
    'assetic:watch' => [
        'class' => Cundd\Assetic\Command\WatchCommand::class,
    ],
    'assetic:livereload' => [
        'class' => Cundd\Assetic\Command\LiveReloadCommand::class,
    ],
];
