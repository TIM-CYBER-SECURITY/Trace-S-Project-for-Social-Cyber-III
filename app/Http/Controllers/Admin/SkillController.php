<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\User;
use App\Services\Skills\SkillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function __construct(private SkillService $skills)
    {
    }

    public function index()
    {
        $installed = Skill::orderBy('name')->get()->keyBy('skill_key');
        $catalog = $this->skills->catalog();
        return view('admin.skills.index', compact('installed', 'catalog'));
    }

    public function show(string $key)
    {
        $skill = Skill::where('skill_key', $key)->firstOrFail();
        $me = Auth::user();
        $operators = User::with('roleRef')->where('organization_id', $me->organization_id)->whereNotNull('trace_id')
            ->whereIn('account_status', ['PROVISIONED', 'ACTIVE'])->orderBy('trace_id')->get();
        $granted = $skill->users()->get()->keyBy('id');
        $executions = $skill->executions()->with('user')->orderByDesc('id')->limit(10)->get();
        return view('admin.skills.show', compact('skill', 'operators', 'granted', 'executions'));
    }

    public function act(Request $request, string $key)
    {
        $v = $request->validate([
            'action' => ['required', Rule::in(['install', 'update', 'remove', 'enable', 'disable', 'configure', 'assign'])],
            'max_input_chars' => ['nullable', 'integer'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);
        $me = Auth::user();
        if (in_array($v['action'], ['install', 'update', 'remove'], true)) {
            abort_unless($me->hasPermission('skills.install'), 403, 'Memasang atau menghapus Skill memerlukan izin skills.install.');
        } else {
            abort_unless($me->hasPermission('skills.configure'), 403, 'Mengonfigurasi atau menugaskan Skill memerlukan izin skills.configure.');
        }

        try {
            if (in_array($v['action'], ['install', 'update'], true)) {
                $this->skills->install($me, $key);
                return back()->with('success', 'Skill ' . ($v['action'] === 'install' ? 'dipasang (status DISABLED; aktifkan secara eksplisit).' : 'diperbarui dari katalog.'));
            }

            $skill = Skill::where('skill_key', $key)->firstOrFail();
            switch ($v['action']) {
                case 'enable':
                    $this->skills->setStatus($me, $skill, true);
                    return back()->with('success', 'Skill diaktifkan.');
                case 'disable':
                    $this->skills->setStatus($me, $skill, false);
                    return back()->with('success', 'Skill dinonaktifkan.');
                case 'remove':
                    $this->skills->remove($me, $skill);
                    return back()->with('success', 'Skill dihapus dari instalasi.');
                case 'configure':
                    $this->skills->configure($me, $skill, ['max_input_chars' => $v['max_input_chars'] ?? 5000]);
                    return back()->with('success', 'Konfigurasi disimpan.');
                case 'assign':
                    $selected = collect($v['user_ids'] ?? []);
                    $candidates = User::where('organization_id', $me->organization_id)->whereNotNull('trace_id')->get();
                    foreach ($candidates as $u) {
                        $should = $selected->contains($u->id);
                        $current = (bool) $skill->users()->where('users.id', $u->id)->wherePivot('enabled', true)->exists();
                        if ($should !== $current) {
                            $this->skills->assign($me, $skill, $u, $should);
                        }
                    }
                    return back()->with('success', 'Penugasan Skill diperbarui.');
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}
