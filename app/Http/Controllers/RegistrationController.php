<?php

namespace App\Http\Controllers;

use App\Models\StatusTransition;
use App\Services\Identity\RegistrationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Permohonan akses Trace-ID (publik). Tidak pernah membuat akun aktif. */
class RegistrationController extends Controller
{
    public function __construct(private RegistrationWorkflow $workflow)
    {
    }

    public function create()
    {
        $this->locale();
        return view('registration.form');
    }

    public function store(Request $request)
    {
        $this->locale();
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'identification_type' => ['required', Rule::in(['NRP', 'NIP', 'NIK_DINAS', 'LAINNYA'])],
            'identification_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\/\- ]+$/'],
            'organization' => ['required', 'string', 'min:2', 'max:160'],
            'unit' => ['nullable', 'string', 'max:160'],
            'position' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\- ]+$/'],
            'requested_role' => ['required', Rule::in(['ADMINISTRATOR', 'OPERATOR'])],
            'access_reason' => ['required', 'string', 'min:20', 'max:1000'],
            'recommender_name' => ['nullable', 'string', 'max:120'],
            'recommender_position' => ['nullable', 'string', 'max:120'],
            'recommender_contact' => ['nullable', 'string', 'max:160'],
            'verification_info' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ]);

        [$req, $code] = $this->workflow->submit($data);

        // Kode status ditampilkan sekali saja; hanya hash-nya yang disimpan.
        return redirect()->route('registration.submitted')->with('registration_receipt', [
            'reference' => $req->request_reference,
            'status_code' => $code,
        ]);
    }

    public function submitted(Request $request)
    {
        $this->locale();
        $receipt = $request->session()->get('registration_receipt');
        if (! $receipt) {
            return redirect()->route('registration.create');
        }
        return view('registration.submitted', compact('receipt'));
    }

    public function statusForm()
    {
        $this->locale();
        return view('registration.status', ['req' => null]);
    }

    public function statusLookup(Request $request)
    {
        $this->locale();
        $v = $request->validate([
            'request_reference' => ['required', 'string', 'max:24'],
            'status_code' => ['required', 'string', 'max:16'],
        ]);

        $req = RegistrationWorkflow::findByCredentials($v['request_reference'], $v['status_code']);
        if (! $req) {
            return back()->withErrors(['request_reference' => 'Referensi atau kode status tidak cocok.'])->onlyInput('request_reference');
        }

        $history = StatusTransition::where('subject_type', 'REGISTRATION')
            ->where('subject_id', $req->request_reference)->orderBy('id')->get();

        return view('registration.status', [
            'req' => $req,
            'history' => $history,
            'code' => strtoupper(trim($v['status_code'])),
        ]);
    }

    public function revise(Request $request)
    {
        $this->locale();
        $v = $request->validate([
            'request_reference' => ['required', 'string', 'max:24'],
            'status_code' => ['required', 'string', 'max:16'],
            'access_reason' => ['required', 'string', 'min:20', 'max:1000'],
            'verification_info' => ['nullable', 'string', 'max:1000'],
        ]);

        $req = RegistrationWorkflow::findByCredentials($v['request_reference'], $v['status_code']);
        if (! $req) {
            return redirect()->route('registration.status')->withErrors(['request_reference' => 'Referensi atau kode status tidak cocok.']);
        }
        try {
            $this->workflow->applicantRevise($req, $v);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('registration.status')->with('success', 'Revisi dikirim. Status: PENDING REVIEW. Masukkan kembali referensi dan kode untuk memantau.');
    }

    private function locale(): void
    {
        $l = session('locale', 'en');
        if (in_array($l, ['en', 'id'], true)) {
            app()->setLocale($l);
        }
    }
}
