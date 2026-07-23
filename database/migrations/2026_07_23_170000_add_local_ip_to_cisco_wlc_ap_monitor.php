<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cisco_wlc_ap_monitor', 'local_ip')) {
            Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table): void {
                $table->string('local_ip', 45)->nullable()->after('location')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cisco_wlc_ap_monitor', 'local_ip')) {
            Schema::table('cisco_wlc_ap_monitor', function (Blueprint $table): void {
                $table->dropColumn('local_ip');
            });
        }
    }
};
