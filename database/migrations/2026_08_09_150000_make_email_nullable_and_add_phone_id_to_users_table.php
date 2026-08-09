<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone_number')->nullable()->unique()->after('email');
            $table->string('id_number')->nullable()->unique()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->dropUnique(['id_number']);
            $table->dropColumn(['phone_number', 'id_number']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
