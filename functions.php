<?php

use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use BookStackKatex\MathExtension;
use League\CommonMark\Environment\Environment;

require_once __DIR__ . '/src/MathExtension.php';

Theme::listen(
    ThemeEvents::COMMONMARK_ENVIRONMENT_CONFIGURE,
    static function (Environment $environment): void {
        $environment->addExtension(new MathExtension());
    }
);
