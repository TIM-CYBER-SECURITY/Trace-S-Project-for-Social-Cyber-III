<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillExecution;
use App\Services\Skills\SkillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Penggunaan Skills oleh pengguna (Operator/Administrator). */
class SkillRunController extends Controller
{
    public function __construct(private SkillService $skills)
    {
    }

    public function index()
    {
        $me = Auth::user();
        $available = $this->skills->availableFor($me);
        $history = SkillExecution::with('skill')->where('user_id', $me->id)->orderByDesc('id')->paginate(10);
        return view('skills.index', compact('available', 'history'));
    }

    public function show(string $key)
    {
        $skill = Skill::where('skill_key', $key)->firstOrFail();
        abort_unless($this->skills->canUse(Auth::user(), $skill), 404);
        return view('skills.run', ['skill' => $skill, 'execution' => null]);
    }

    public function execute(Request $request, string $key)
    {
        $skill = Skill::where('skill_key', $key)->firstOrFail();
        try {
            $exec = $this->skills->execute(Auth::user(), $skill, $request->all());
        } catch (\InvalidArgumentException $e) {
            if (! $this->skills->canUse(Auth::user(), $skill)) {
                abort(403, $e->getMessage());
            }
            return back()->withInput()->with('error', $e->getMessage());
        }
        return view('skills.run', ['skill' => $skill, 'execution' => $exec]);
    }

    public function execution(int $id)
    {
        // Hanya hasil eksekusi milik sendiri.
        $exec = SkillExecution::with('skill')->where('user_id', Auth::id())->findOrFail($id);
        return view('skills.run', ['skill' => $exec->skill, 'execution' => $exec]);
    }
}
