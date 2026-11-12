<?php

namespace App\Http\Controllers;

use App\Models\InformationRecord;
use App\Models\User;
use App\Services\Records\RecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InformationController extends Controller
{
    public function __construct(private RecordService $records)
    {
    }

    public function index(Request $request)
    {
        $me = Auth::user();
        $q = $this->records->visibleTo($me)->with('creator');

        if ($request->query('scope') === 'mine') {
            $q->where('created_by', $me->id);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('record_code', 'like', "%{$s}%"));
        }
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($cat = $request->query('category')) {
            $q->where('category', $cat);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('updated_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('updated_at', '<=', $to);
        }

        $sort = in_array($request->query('sort'), ['updated_at', 'created_at', 'title', 'status'], true) ? $request->query('sort') : 'updated_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $items = $q->orderBy($sort, $dir)->paginate(15)->withQueryString();

        return view('information.index', ['items' => $items, 'sort' => $sort, 'dir' => $dir]);
    }

    public function create()
    {
        $this->authorizePerm('info.submit');
        return view('information.form', ['record' => null, 'version' => null]);
    }

    public function store(Request $request)
    {
        $this->authorizePerm('info.submit');
        $data = $this->validated($request);

        try {
            $record = $this->records->create(Auth::user(), $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->input('submit_now')) {
            $this->records->submit(Auth::user(), $record);
            return redirect()->route('records.show', $record->record_code)->with('success', 'Rekaman dibuat dan diajukan untuk review.');
        }
        return redirect()->route('records.show', $record->record_code)->with('success', 'Draft disimpan.');
    }

    public function show(string $code)
    {
        $record = $this->find($code);
        $me = Auth::user();
        abort_unless($this->records->canView($me, $record), 404);

        $shown = $record->approved_version && ! ($record->created_by === $me->id || $me->isAdministrator())
            ? $record->version($record->approved_version)
            : $record->version($record->current_version);

        $versions = $record->versions()->with('author')->get();
        // Operator lain hanya melihat versi yang disetujui, bukan riwayat internal.
        $internal = $record->created_by === $me->id || $me->hasPermission('info.review');
        $reviews = $internal ? $record->reviews()->with('reviewer')->get() : collect();

        return view('information.show', [
            'record' => $record, 'shown' => $shown, 'versions' => $internal ? $versions : collect(),
            'reviews' => $reviews, 'canEdit' => $this->records->canEdit($me, $record),
            'canReview' => $me->hasPermission('info.review') && $me->hasPermission('info.approve') && $record->created_by !== $me->id
                && $record->organization_id === $me->organization_id,
        ]);
    }

    public function edit(string $code)
    {
        $record = $this->find($code);
        abort_unless($this->records->canEdit(Auth::user(), $record), 403, 'Rekaman tidak dapat diubah.');
        return view('information.form', ['record' => $record, 'version' => $record->version($record->current_version)]);
    }

    public function update(Request $request, string $code)
    {
        $record = $this->find($code);
        $data = $this->validated($request);
        try {
            $this->records->update(Auth::user(), $record, $data);
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }
        return redirect()->route('records.show', $record->record_code)->with('success', 'Perubahan disimpan sebagai versi baru.');
    }

    public function submit(string $code)
    {
        $record = $this->find($code);
        abort_unless($this->records->canView(Auth::user(), $record), 404);
        try {
            $this->records->submit(Auth::user(), $record);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Diajukan untuk review.');
    }

    public function review(Request $request, string $code)
    {
        $record = $this->find($code);
        $v = $request->validate([
            'decision' => ['required', Rule::in(['UNDER REVIEW', 'APPROVED', 'REJECTED', 'REVISION REQUIRED'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            if ($v['decision'] === 'UNDER REVIEW') {
                $this->records->startReview(Auth::user(), $record);
            } else {
                $this->records->decide(Auth::user(), $record, $v['decision'], $v['comment'] ?? null);
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Keputusan review dicatat: ' . $v['decision'] . '.');
    }

    public function archive(Request $request, string $code)
    {
        $record = $this->find($code);
        $v = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        try {
            $this->records->archive(Auth::user(), $record, $v['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Rekaman diarsipkan.');
    }

    public function destroy(Request $request, string $code)
    {
        $record = $this->find($code);
        $v = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        try {
            $this->records->destroy(Auth::user(), $record, $v['reason']);
        } catch (\InvalidArgumentException $e) {
            // Operator tidak berwenang -> 403; kegagalan aturan (retensi/status) -> pesan.
            if (! Auth::user()->hasPermission('info.delete')) {
                abort(403, $e->getMessage());
            }
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('records.index')->with('success', 'Rekaman dihapus (soft delete) dan tercatat di audit log.');
    }

    private function find(string $code): InformationRecord
    {
        // Rute destroy/arsip/review tidak boleh membocorkan keberadaan rekaman organisasi lain.
        return InformationRecord::where('record_code', $code)
            ->where('organization_id', Auth::user()->organization_id ?? 0)
            ->firstOrFail();
    }

    private function authorizePerm(string $perm): void
    {
        abort_unless(Auth::user()->hasPermission($perm), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(config('trace.record_categories')))],
            'content' => ['required', 'string', 'min:10', 'max:20000'],
            'change_reason' => ['nullable', 'string', 'max:250'],
        ]);
    }
}
