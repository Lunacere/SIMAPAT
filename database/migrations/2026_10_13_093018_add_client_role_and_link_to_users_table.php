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
            $table->enum('role', ['admin', 'operator', 'user', 'client'])->default('user')->change();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'client')->update(['role' => 'user']);
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
            $table->enum('role', ['admin', 'operator', 'user'])->default('user')->change();
        });
    }
};
