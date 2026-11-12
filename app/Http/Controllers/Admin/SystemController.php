<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\SystemLog;
use App\Services\Support\AppSettings;
use App\Services\Support\AuditLogger;
use App\Services\Support\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SystemController extends Controller
{
    public function __construct(private SystemHealth $health, private AppSettings $settings, private AuditLogger $audit)
    {
    }

    public function index()
    {
        $checks = $this->health->checks();
        $connectors = Connector::orderBy('key')->get();
        $rssFeeds = \App\Models\RssFeed::orderBy('name')->get();
        $security = SystemLog::whereIn('action', ['LOGIN_FAILURE', 'LOGIN_LOCKED', 'LOGIN_DENIED', 'ACCESS_DENIED', 'CREDENTIAL_RESET', 'ACCOUNT_SUSPENDED', 'ACCOUNT_REVOKED'])
            ->orderByDesc('id')->limit(20)->get();
        $definitions = AppSettings::DEFINITIONS;
        $values = collect($definitions)->map(fn ($d, $k) => $this->settings->get($k))->all();

        return view('admin.system', compact('checks', 'connectors', 'rssFeeds', 'security', 'definitions', 'values'));
    }

    public function update(Request $request)
    {
        $rules = collect(AppSettings::DEFINITIONS)->map(fn ($d) => $d[3])->all();
        $data = Validator::make($request->all(), $rules)->validate();

        foreach ($data as $key => $value) {
            if (isset(AppSettings::DEFINITIONS[$key]) && (string) $value !== (string) $this->settings->get($key)) {
                $this->settings->set($key, $value, Auth::user(), $this->audit);
            }
        }

        return back()->with('success', 'Konfigurasi sistem disimpan dan dicatat pada audit log.');
    }

    public function purgeSimulation()
    {
        Artisan::call('trace:purge-simulation', ['--force' => true]);
        return back()->with('success', 'Seluruh data uji simulasi awal berhasil dibersihkan. Akun Trace-ID, RBAC, dan Audit Log tetap utuh.');
    }
}
