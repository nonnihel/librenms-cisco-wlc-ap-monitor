<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cisco_wlc_ap_monitor')) {
            return;
        }

        Schema::create('cisco_wlc_ap_monitor', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('device_id');
            $table->string('ap_name', 191);
            $table->string('radio_mac', 64)->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->string('location', 191)->nullable();
            $table->enum('state', ['up', 'down', 'ignored', 'retired'])->default('up');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('down_since')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('transition_count')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'ap_name'], 'cwlc_ap_device_name_unique');
            $table->index(['device_id', 'state'], 'cwlc_ap_device_state_idx');
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cisco_wlc_ap_monitor');
    }
};
