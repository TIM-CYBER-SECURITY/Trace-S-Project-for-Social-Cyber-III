<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use App\Models\StatusTransition;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Identity\AccountLifecycle;
use App\Services\Identity\RegistrationWorkflow;
use App\Services\Identity\RegistryStore;
use App\Services\Identity\RegistryValidator;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Portal Developer/Owner. Hanya mengelola permohonan, registry, dan siklus akun.
 * Tidak memiliki rute ke data operasional (informasi, kasus, bukti, laporan).
 */
class PlatformController extends Controller
{
    public function __construct(
        private RegistrationWorkflow $workflow,
        private AccountLifecycle $lifecycle,
        private RegistryStore $store,
        private AuditLogger $audit,
    ) {
    }

    public function dashboard()
    {
        $counts = RegistrationRequest::selectRaw('approval_status, count(*) c')->groupBy('approval_status')->pluck('c', 'approval_status');
        $accounts = User::whereNotNull('trace_id')->selectRaw('account_status, count(*) c')->groupBy('account_status')->pluck('c', 'account_status');
        $recent = StatusTransition::orderByDesc('id')->limit(10)->get();

        return view('platform.dashboard', compact('counts', 'accounts', 'recent'));
    }

    public function registrations(Request $request)
    {
        $status = $request->query('status', 'PENDING REVIEW');
        $q = RegistrationRequest::query()->orderByDesc('id');
        if ($status !== 'ALL') {
            $q->where('approval_status', $status);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('request_reference', 'like', "%{$s}%")->orWhere('full_name', 'like', "%{$s}%")->orWhere('organization', 'like', "%{$s}%"));
        }
        return view('platform.registrations', [
            'items' => $q->paginate(20)->withQueryString(),
            'status' => $status,
            'statuses' => RegistrationRequest::STATUSES,
        ]);
    }

    public function show(string $reference)
    {
        $req = RegistrationRequest::where('request_reference', $reference)->firstOrFail();
        // Akses ke data identitas pemohon dicatat.
        $this->audit->log('REGISTRATION_VIEWED', ['type' => 'RegistrationRequest', 'id' => $req->request_reference]);
        $history = $req->transitions()->get();

        return view('platform.registration-show', compact('req', 'history'));
    }

    public function act(Request $request, string $reference)
    {
        $req = RegistrationRequest::where('request_reference', $reference)->firstOrFail();
        $owner = Auth::guard('platform')->user();
        $v = $request->validate([
            'action' => ['required', Rule::in(['review', 'approve', 'reject', 'revise', 'provision'])],
            'approved_role' => ['nullable', Rule::in(['ADMINISTRATOR', 'OPERATOR'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            switch ($v['action']) {
                case 'review':
                    $this->workflow->startReview($req, $owner);
                    return back()->with('success', 'Permohonan ditandai UNDER REVIEW.');
                case 'approve':
                    $role = $v['approved_role'] ?? $req->requested_role;
                    $tid = $this->workflow->approve($req, $owner, $role, $v['notes'] ?? null);
                    return back()->with('success', "Disetujui. Trace-ID {$tid} ditulis ke registry. Lanjutkan ke provisioning.");
                case 'reject':
                    if (empty($v['notes'])) {
                        return back()->withErrors(['notes' => 'Catatan wajib untuk penolakan.']);
                    }
                    $this->workflow->reject($req, $owner, $v['notes']);
                    return back()->with('success', 'Permohonan ditolak.');
                case 'revise':
                    if (empty($v['notes'])) {
                        return back()->withErrors(['notes' => 'Catatan revisi wajib diisi.']);
                    }
                    $this->workflow->requestRevision($req, $owner, $v['notes']);
                    return back()->with('success', 'Revisi diminta dari pemohon.');
                case 'provision':
                    $password = $this->workflow->provision($req->refresh(), $owner);
                    if ($password === null) {
                        return back()->with('warning', 'Akun sudah diprovisioning sebelumnya; tidak ada kredensial baru.');
                    }
                    // Kredensial awal ditampilkan SATU KALI pada respons ini; tidak disimpan.
                    return back()->with('issued_credential', ['trace_id' => $req->trace_id, 'password' => $password])
                        ->with('success', 'Akun diprovisioning. Serahkan kredensial melalui kanal aman.');
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function registry()
    {
        $validator = app(RegistryValidator::class);
        try {
            $entries = $this->store->approved();
            $errors = $validator->validate($entries);
            $loadError = null;
        } catch (\Throwable $e) {
            $entries = [];
            $errors = [];
            $loadError = $e->getMessage();
        }
        $provisioned = User::whereNotNull('trace_id')->pluck('trace_id')->flip();

        return view('platform.registry', compact('entries', 'errors', 'loadError', 'provisioned'));
    }

    public function history()
    {
        $transitions = StatusTransition::where('subject_type', 'ACCOUNT')->orderByDesc('id')->paginate(25);
        $audit = SystemLog::whereIn('action', ['TRACE_ID_PROVISIONED', 'REGISTRY_IMPORT_REJECTED', 'REGISTRATION_APPROVED'])->orderByDesc('id')->limit(25)->get();

        return view('platform.history', compact('transitions', 'audit'));
    }

    public function accounts(Request $request)
    {
        $q = User::with(['roleRef', 'organization'])->whereNotNull('trace_id')->orderBy('trace_id');
        if ($s = $request->query('status')) {
            $q->where('account_status', $s);
        }
        return view('platform.accounts', ['users' => $q->paginate(25)->withQueryString(), 'status' => $s ?? '']);
    }

    public function accountAction(Request $request, string $traceId)
    {
        $user = User::where('trace_id', $traceId)->firstOrFail();
        $v = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'revoke', 'reactivate'])],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        try {
            match ($v['action']) {
                'suspend' => $this->lifecycle->suspend($user, $v['reason']),
                'revoke' => $this->lifecycle->revoke($user, $v['reason']),
                'reactivate' => $this->lifecycle->reactivate($user, $v['reason']),
            };
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Akun {$traceId} diperbarui.");
    }
}
