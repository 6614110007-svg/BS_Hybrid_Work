<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking', function (Blueprint $table) {
            $table->char('booking_id', 11)->primary();
            $table->date('booking_date');
            $table->string('time_slot', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->dateTime('actual_checkin_time')->nullable();
            $table->dateTime('actual_checkout_time')->nullable();
            $table->text('checkin_photo')->nullable();
            $table->string('booking_status', 20);
            $table->string('employee_id', 11);
            $table->string('desk_id', 11);

            $table->index('booking_date');
            $table->index('booking_status');
            $table->index('desk_id');
            $table->index('employee_id');

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('employee')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('desk_id')
                ->references('desk_id')
                ->on('desk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking');
    }
};
