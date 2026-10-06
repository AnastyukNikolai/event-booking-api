<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('event_id')->constrained();
            $table->enum('status', ['active', 'cancelled']);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'id']);
        });

        DB::statement("CREATE UNIQUE INDEX bookings_user_event_active_unique ON bookings (user_id, event_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
