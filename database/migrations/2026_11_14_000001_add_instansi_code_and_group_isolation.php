<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations') && ! Schema::hasColumn('organizations', 'code')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('code', 64)->nullable()->index();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'last_device_code')) {
                    $table->string('last_device_code', 64)->nullable();
                }
                if (! Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 64)->nullable();
                }
                if (! Schema::hasColumn('users', 'last_user_agent')) {
                    $table->string('last_user_agent', 255)->nullable();
                }
            });
        }

        foreach (['cases', 'narratives', 'console_sessions'] as $tbl) {
            if (Schema::hasTable($tbl) && ! Schema::hasColumn($tbl, 'organization_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('organization_id')->nullable()->index();
                });
            }
        }

        // Backfill Kode Instansi pada organisasi eksisting (kecuali fixture simulasi)
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'code')) {
            $presetCodes = [
                'DuoDevs_ai' => 'DDS-AI#01',
                'PUSSIBERAD TNI AD' => 'PSB-AD#26',
                'Team Sindikat_Pawpatrol' => 'TSP-OPS#09',
            ];

            $orgs = DB::table('organizations')->get();
            foreach ($orgs as $org) {
                if (! empty($org->code)) {
                    continue;
                }
                if (stripos((string) $org->name, 'Simulasi') !== false) {
                    continue;
                }
                $code = $presetCodes[$org->name]
                    ?? ('ORG-' . strtoupper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $org->name), 0, 5)) . '#' . str_pad((string) $org->id, 2, '0', STR_PAD_LEFT));
                DB::table('organizations')->where('id', $org->id)->update(['code' => $code]);
            }
        }

        // Backfill organization_id pada cases dari creator (users.organization_id)
        if (Schema::hasTable('cases') && Schema::hasColumn('cases', 'organization_id')) {
            $cases = DB::table('cases')->whereNull('organization_id')->get();
            foreach ($cases as $c) {
                $orgId = null;
                if ($c->created_by) {
                    $orgId = DB::table('users')->where('id', $c->created_by)->value('organization_id');
                }
                if ($orgId) {
                    DB::table('cases')->where('id', $c->id)->update(['organization_id' => $orgId]);
                }
            }
        }

        // Backfill organization_id pada narratives dari cases.organization_id
        if (Schema::hasTable('narratives') && Schema::hasColumn('narratives', 'organization_id')) {
            $narratives = DB::table('narratives')->whereNull('organization_id')->get();
            foreach ($narratives as $n) {
                if ($n->case_id) {
                    $orgId = DB::table('cases')->where('id', $n->case_id)->value('organization_id');
                    if ($orgId) {
                        DB::table('narratives')->where('id', $n->id)->update(['organization_id' => $orgId]);
                    }
                }
            }
        }

        // Backfill organization_id pada console_sessions dari users.organization_id
        if (Schema::hasTable('console_sessions') && Schema::hasColumn('console_sessions', 'organization_id')) {
            $sessions = DB::table('console_sessions')->whereNull('organization_id')->get();
            foreach ($sessions as $s) {
                if ($s->user_id) {
                    $orgId = DB::table('users')->where('id', $s->user_id)->value('organization_id');
                    if ($orgId) {
                        DB::table('console_sessions')->where('id', $s->id)->update(['organization_id' => $orgId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['cases', 'narratives', 'console_sessions'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'organization_id')) {
                Schema::table($tbl, fn (Blueprint $table) => $table->dropColumn('organization_id'));
            }
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['last_device_code', 'last_login_ip', 'last_user_agent'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'code')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('code'));
        }
    }
};
