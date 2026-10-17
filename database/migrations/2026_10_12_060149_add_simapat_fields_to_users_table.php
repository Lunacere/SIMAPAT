<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 100)->nullable()->unique();
            $table->enum('role', ['admin', 'operator', 'user'])->default('user');
        });
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['username' => 'user_'.$user->id]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('username', 100)->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
        });
    }
};
