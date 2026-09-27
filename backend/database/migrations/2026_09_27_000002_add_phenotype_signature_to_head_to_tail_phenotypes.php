<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('head_to_tail_phenotypes', function (Blueprint $table) {
            $table->char('phenotype_signature', 40)->nullable()->unique()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('head_to_tail_phenotypes', function (Blueprint $table) {
            $table->dropUnique(['phenotype_signature']);
            $table->dropColumn('phenotype_signature');
        });
    }
};
