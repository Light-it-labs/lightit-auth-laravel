<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lightit\Authentication\Domain\TwoFactorAuthenticatable;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME)->nullable();
            $table->datetime(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME)->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_SECRET_COLUMN_NAME);
            $table->dropColumn(TwoFactorAuthenticatable::TWO_FACTOR_AUTH_ACTIVATED_AT_COLUMN_NAME);
        });
    }
};
