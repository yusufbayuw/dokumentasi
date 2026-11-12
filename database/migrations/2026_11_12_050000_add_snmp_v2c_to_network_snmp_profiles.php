<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing SNMPv3 rows with a default version of 3.
        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->string('version', 4)->default('3')->after('device_id');
            $table->text('community')->nullable()->after('version');
        });

        // SNMPv2c has only a community secret. Existing v3 credentials are
        // never converted, and may be null on v2c-only rows.
        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->text('security_name')->nullable()->change();
            $table->text('auth_passphrase')->nullable()->change();
            $table->text('privacy_passphrase')->nullable()->change();
        });

        Schema::table('network_interface_samples', function (Blueprint $table): void {
            $table->string('snmp_version', 4)->default('3')->after('snmp_if_index');
        });
    }

    public function down(): void
    {
        // Refuse destructive rollback when any v2c profile is present.
        if (DB::table('network_snmp_profiles')->where('version', '2c')->exists()
            || DB::table('network_snmp_profiles')->whereNull('security_name')->exists()
            || DB::table('network_snmp_profiles')->whereNull('auth_passphrase')->exists()
            || DB::table('network_snmp_profiles')->whereNull('privacy_passphrase')->exists()) {
            throw new RuntimeException('Remove or migrate v2c profiles before reverting this schema.');
        }

        Schema::table('network_interface_samples', function (Blueprint $table): void {
            $table->dropColumn('snmp_version');
        });

        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->text('security_name')->nullable(false)->change();
            $table->text('auth_passphrase')->nullable(false)->change();
            $table->text('privacy_passphrase')->nullable(false)->change();
        });

        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->dropColumn(['community', 'version']);
        });
    }
};
