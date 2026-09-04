<?php

declare(strict_types=1);

describe('Passkeys migration stub', function (): void {
    it('declares exactly the passkeys table schema', function (): void {
        $stub = (string) file_get_contents(
            __DIR__.'/../../../src/Stubs/Passkeys/database/migrations/create_passkeys_table.stub'
        );

        expect($stub)->toBe(<<<'PHP'
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('passkeys', function (Blueprint $table) {
                        $table->id();
                        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                        $table->string('name');
                        $table->string('credential_id', 512)->unique();
                        $table->json('credential');
                        $table->timestamp('created_at')->useCurrent();
                        $table->timestamp('last_used_at')->nullable();

                        $table->index(['user_id', 'created_at']);
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('passkeys');
                }
            };

            PHP);
    });
});
