<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedInteger('interviewer_id');
            $table->dateTime('interview_date');
            $table->string('interview_type')->nullable(); // ceo, director, hr
            $table->string('interview_notes', 150)->nullable();

            // Scorecard scores (1-5 each)
            $table->unsignedTinyInteger('score_communication')->nullable();
            $table->unsignedTinyInteger('score_technical')->nullable();
            $table->unsignedTinyInteger('score_cultural_fit')->nullable();
            $table->unsignedTinyInteger('score_experience')->nullable();
            $table->unsignedTinyInteger('score_personality')->nullable();

            $table->string('verdict')->nullable(); // strong_hire, hire, no_hire, strong_no_hire

            $table->unsignedInteger('account_id');

            $table->timestamps();

            $table->index(['candidate_id', 'account_id']);
            $table->index('interviewer_id');
            $table->index('interview_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_interviews');
    }
};
