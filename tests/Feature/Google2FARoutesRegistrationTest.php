<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lightitlabs\Tests\Fixtures\FakeGoogle2FARoutesCommand;

describe('Google2FAInstaller route registration', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/lightit-2fa-routes-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/routes', 0755, true);
        file_put_contents($this->root.'/routes/api.php', "<?php\n\ndeclare(strict_types=1);\n");

        $this->app->setBasePath($this->root);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->root);
    });

    it('always targets base_path(routes/api.php), regardless of the run count', function (): void {
        Artisan::registerCommand(new FakeGoogle2FARoutesCommand);
        $this->artisan('google2fa-routes-fake')->assertSuccessful();

        Artisan::registerCommand(new FakeGoogle2FARoutesCommand);
        $this->artisan('google2fa-routes-fake')->assertSuccessful();

        expect($this->root.'/routes/two-factor-auth.php')->toBeFile();

        $apiRoutes = (string) file_get_contents($this->root.'/routes/api.php');
        expect(substr_count($apiRoutes, "require __DIR__.'/two-factor-auth.php';"))->toBe(1);
    });
});
