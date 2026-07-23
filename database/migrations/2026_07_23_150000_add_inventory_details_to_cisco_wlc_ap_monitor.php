<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('radio_mac');
            $table->unsignedInteger('client_count')->nullable()->after('location');
            $table->unsignedSmallInteger('radio_count')->nullable()->after('client_count');
            $table->string('channels')->nullable()->after('radio_count');
            $table->unsignedSmallInteger('max_utilization')->nullable()->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table): void {
            $table->dropColumn([
                'location',
                'client_count',
                'radio_count',
                'channels',
                'max_utilization',
            ]);
        });
    }
};
