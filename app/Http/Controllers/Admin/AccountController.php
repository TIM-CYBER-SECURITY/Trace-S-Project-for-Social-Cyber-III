<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistrationRequest;
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\Identity\AccountLifecycle;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Pengelolaan akun Operator & rekaman Trace-ID dalam organisasi Administrator. */
class AccountController extends Controller
{
    public function __construct(private AccountLifecycle $lifecycle, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $me = Auth::user();
        $q = User::with(['roleRef', 'unit'])->where('organization_id', $me->organization_id)->whereNotNull('trace_id');
        if ($s = $request->query('status')) {
            $q->where('account_status', $s);
        }
        if ($r = $request->query('role')) {
            $q->whereHas('roleRef', fn ($w) => $w->where('name', $r));
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('trace_id', 'like', "%{$term}%"));
        }
        $users = $q->orderBy('trace_id')->paginate(20)->withQueryString();

        return view('admin.accounts.index', compact('users'));
    }

    public function show(string $traceId)
    {
        $user = $this->target($traceId);
        $history = StatusTransition::where('subject_type', 'ACCOUNT')->where('subject_id', $user->trace_id)->orderByDesc('id')->get();
        return view('admin.accounts.show', compact('user', 'history'));
    }

    public function act(Request $request, string $traceId)
    {
        $user = $this->target($traceId);
        $me = Auth::user();
        $v = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reactivate', 'reset', 'expiry'])],
            'reason' => ['nullable', 'string', 'max:300'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($user->id === $me->id) {
            return back()->with('error', 'Anda tidak dapat mengubah akun Anda sendiri.');
        }
        // Administrator hanya mengelola akun OPERATOR; akun ADMINISTRATOR dikelola jalur Developer/Owner.
        if (! $user->hasRole('OPERATOR')) {
            $this->audit->log('ACCOUNT_ACTION_DENIED', $user, ['action' => $v['action']], $me->id, 'DENIED');
            return back()->with('error', 'Administrator hanya dapat mengelola akun Operator.');
        }

        try {
            switch ($v['action']) {
                case 'suspend':
                    $this->lifecycle->suspend($user, $v['reason'] ?? null, AccountLifecycle::currentActor());
                    return back()->with('success', "Akun {$user->trace_id} ditangguhkan; sesi dicabut.");
                case 'reactivate':
                    $this->lifecycle->reactivate($user, $v['reason'] ?? null, AccountLifecycle::currentActor());
                    return back()->with('success', "Akun {$user->trace_id} diaktifkan kembali.");
                case 'reset':
                    $temp = $this->lifecycle->resetCredentials($user, AccountLifecycle::currentActor());
                    return back()->with('success', 'Kredensial direset. Serahkan sandi sementara melalui kanal aman.')
                        ->with('issued_credential', ['trace_id' => $user->trace_id, 'password' => $temp]);
                case 'expiry':
                    $user->update(['expires_at' => $v['expires_at'] ?? null]);
                    $this->audit->log('ACCOUNT_EXPIRY_SET', $user, ['expires_at' => $v['expires_at'] ?? null], $me->id);
                    return back()->with('success', 'Masa berlaku akun diperbarui.');
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function registrations(Request $request)
    {
        $me = Auth::user();
        $q = RegistrationRequest::where('organization', $me->organization?->name)->orderByDesc('id');
        if ($s = $request->query('status')) {
            $q->where('approval_status', $s);
        }
        // Hanya baca; tanpa data identitas (NRP/kontak). Persetujuan hanya oleh Developer/Owner.
        $items = $q->paginate(20, ['id', 'request_reference', 'full_name', 'position', 'requested_role', 'approved_role', 'approval_status', 'trace_id', 'created_at', 'updated_at'])->withQueryString();

        return view('admin.registrations', compact('items'));
    }

    private function target(string $traceId): User
    {
        return User::with(['roleRef', 'unit', 'organization'])
            ->where('trace_id', $traceId)
            ->where('organization_id', Auth::user()->organization_id)
            ->firstOrFail();
    }
}
