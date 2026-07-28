<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CredentialType;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\Program;
use App\Models\User;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CredentialAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.credentials.index', [
            'credentials' => Credential::query()->with(['student', 'program', 'offering.course'])->latest('issued_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request, CredentialService $credentials): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'type' => ['required', Rule::enum(CredentialType::class)],
            'program_id' => 'nullable|exists:programs,id',
            'offering_id' => 'nullable|exists:course_offerings,id',
            'language' => 'nullable|in:ar,en,fr',
        ]);

        $student = User::query()->findOrFail($data['student_id']);
        $language = $data['language'] ?? 'en';
        $type = CredentialType::from($data['type']);

        $credential = match ($type) {
            CredentialType::Transcript => $credentials->issueTranscript($request->user(), $student, $language),
            CredentialType::ProgramCertificate => $credentials->issueProgramCertificate(
                $request->user(),
                $student,
                Program::query()->findOrFail($data['program_id']),
                $language
            ),
            CredentialType::StandaloneCertificate => $credentials->issueStandaloneCertificate(
                $request->user(),
                $student,
                CourseOffering::query()->findOrFail($data['offering_id']),
                $language
            ),
        };

        return back()->with('status', __('credentials.issued', ['serial' => $credential->serial]));
    }

    public function regenerate(Request $request, Credential $credential, CredentialService $credentials): RedirectResponse
    {
        $new = $credentials->regenerate($request->user(), $credential);

        return back()->with('status', __('credentials.regenerated', ['serial' => $new->serial]));
    }
}
