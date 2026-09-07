<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Lightitlabs\Tools\FileManipulator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function fakeFileManipulatorCommand(): Command
{
    $command = new class extends Command
    {
        protected $signature = 'file-manipulator-fake';

        public function handle(): int
        {
            return self::SUCCESS;
        }
    };
    $command->setLaravel(app());
    $command->run(new ArrayInput([]), new BufferedOutput);

    return $command;
}

describe('FileManipulator', function (): void {
    beforeEach(function (): void {
        $this->path = sys_get_temp_dir().'/file-manipulator-'.uniqid().'.php';
    });

    afterEach(function (): void {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    });

    it('replaces the search string and returns true when the anchor is found', function (): void {
        file_put_contents($this->path, "<?php\n\nreturn [\n];\n");

        $result = (new FileManipulator(fakeFileManipulatorCommand()))->replaceInFile(
            'return [',
            'return ['."\n    'inserted',",
            $this->path
        );

        expect($result)->toBeTrue()
            ->and(file_get_contents($this->path))->toBe("<?php\n\nreturn [\n    'inserted',\n];\n");
    });

    it('leaves the file untouched and returns false when the anchor is not found', function (): void {
        $original = "<?php\n\nreturn array(\n);\n";
        file_put_contents($this->path, $original);

        $result = (new FileManipulator(fakeFileManipulatorCommand()))->replaceInFile(
            'return [',
            'return ['."\n    'inserted',",
            $this->path
        );

        expect($result)->toBeFalse()
            ->and(file_get_contents($this->path))->toBe($original);
    });

    it('returns false without writing when the file does not exist', function (): void {
        $missing = $this->path;

        $result = (new FileManipulator(fakeFileManipulatorCommand()))->replaceInFile(
            'return [',
            'return [inserted',
            $missing
        );

        expect($result)->toBeFalse()
            ->and(file_exists($missing))->toBeFalse();
    });
});
