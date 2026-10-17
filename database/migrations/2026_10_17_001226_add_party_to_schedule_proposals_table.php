<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_proposals', fn (Blueprint $table) => $table->enum('proposed_party', ['company', 'client'])->default('company'));
        DB::table('schedule_proposals')->orderBy('id')->each(function (object $proposal): void {
            $creatorId = DB::table('meetings')->where('id', $proposal->meeting_id)->value('creator_id');
            DB::table('schedule_proposals')->where('id', $proposal->id)->update(['proposed_party' => (int) $creatorId === (int) $proposal->proposed_by ? 'company' : 'client']);
        });
    }

    public function down(): void
    {
        Schema::table('schedule_proposals', fn (Blueprint $table) => $table->dropColumn('proposed_party'));
    }
};
