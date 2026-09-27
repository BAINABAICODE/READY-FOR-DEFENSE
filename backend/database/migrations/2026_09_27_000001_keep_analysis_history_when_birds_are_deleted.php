<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->dropForeign(['parent_1_bird_id']);
            $table->dropForeign(['parent_2_bird_id']);
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_1_bird_id')->nullable()->change();
            $table->unsignedBigInteger('parent_2_bird_id')->nullable()->change();
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->foreign('parent_1_bird_id')->references('id')->on('birds')->nullOnDelete();
            $table->foreign('parent_2_bird_id')->references('id')->on('birds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->dropForeign(['parent_1_bird_id']);
            $table->dropForeign(['parent_2_bird_id']);
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_1_bird_id')->nullable(false)->change();
            $table->unsignedBigInteger('parent_2_bird_id')->nullable(false)->change();
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->foreign('parent_1_bird_id')->references('id')->on('birds')->restrictOnDelete();
            $table->foreign('parent_2_bird_id')->references('id')->on('birds')->restrictOnDelete();
        });
    }
};
