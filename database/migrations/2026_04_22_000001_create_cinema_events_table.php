<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cinema_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('event_type', ['festival', 'projection', 'cycle', 'workshop', 'other'])->default('other');
            $table->string('venue')->nullable();
            $table->enum('island', ['GC', 'TF', 'LZ', 'FV', 'LP', 'EH', 'GO', 'ALL'])->nullable();
            $table->string('ticket_url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('source_url')->nullable();
            $table->text('raw_text')->nullable();             // texto bruto scrapeado (incluyendo alt/OG)
            $table->decimal('ai_confidence', 3, 2)->nullable(); // 0.00–1.00
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'needs_review'])->default('pending');
            $table->timestamps();

            $table->index(['start_date', 'status']);
            $table->index(['end_date']);          // para el borrado periódico
            $table->index(['island', 'event_type']);
            $table->index('source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_events');
    }
};
