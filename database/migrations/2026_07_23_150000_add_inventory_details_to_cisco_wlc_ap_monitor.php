<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'location' => fn (Blueprint $table) => $table->string('location')->nullable()->after('radio_mac'),
            'client_count' => fn (Blueprint $table) => $table->unsignedInteger('client_count')->nullable()->after('location'),
            'radio_count' => fn (Blueprint $table) => $table->unsignedSmallInteger('radio_count')->nullable()->after('client_count'),
            'channels' => fn (Blueprint $table) => $table->string('channels')->nullable()->after('radio_count'),
            'max_utilization' => fn (Blueprint $table) => $table->unsignedSmallInteger('max_utilization')->nullable()->after('channels'),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('cisco_wlc_ap_monitor', $name)) {
                Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['max_utilization', 'channels', 'radio_count', 'client_count', 'location'] as $column) {
            if (Schema::hasColumn('cisco_wlc_ap_monitor', $column)) {
                Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
