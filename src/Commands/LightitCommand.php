<?php

namespace Lightit\Commands;

use Illuminate\Console\Command;

class LightitCommand extends Command
{
    public $signature = 'lightit-auth-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
