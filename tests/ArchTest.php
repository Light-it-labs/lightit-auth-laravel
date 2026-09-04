<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('tools stay path-injected')
    ->expect('Lightitlabs\Tools')
    ->not->toUse(['base_path', 'config_path', 'app_path', 'app']);
