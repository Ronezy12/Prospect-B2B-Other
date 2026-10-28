<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('settings.users.index', [
            'users' => User::with(['roles:id,name', 'team:id,name'])->orderBy('name')->paginate(50),
            'roleLabels' => RolesAndPermissionsSeeder::ROLE_LABELS,
        ]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('settings.users.form', ['user' => new User(['is_active' => true])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request, null);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'team_id' => $this->teamId($data),
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $user->syncRoles([$data['role']]);

            return $user;
        });
        $this->audit->log('user.created', $user, ['email' => $user->email, 'role' => $data['role']]);

        return redirect()->route('pm.settings.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->guard($request);

        return view('settings.users.form', ['user' => $user] + $this->formData());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request, $user);

        // Empêche de se verrouiller soi-même hors de l'administration.
        if ($user->is($request->user()) && ($data['role'] !== 'administrateur' || empty($data['is_active']))) {
            return back()->withInput()->withErrors(['role' => 'Vous ne pouvez pas retirer vos propres droits d\'administration ni désactiver votre compte.']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'team_id' => $this->teamId($data),
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->syncRoles([$data['role']]);
        });

        $changes = $this->audit->diff($user);
        $changes['role'] = $data['role'];
        $this->audit->log('user.updated', $user, $changes);

        return redirect()->route('pm.settings.users.index')->with('success', 'Utilisateur enregistré.');
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('users.manage'), 403);
    }

    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(array_keys(RolesAndPermissionsSeeder::ROLES))],
            'team_id' => ['nullable', 'exists:pm_teams,id'],
            'new_team' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function teamId(array $data): ?int
    {
        if (filled($data['new_team'] ?? null)) {
            return Team::firstOrCreate(['name' => trim($data['new_team'])])->id;
        }

        return $data['team_id'] ?? null;
    }

    private function formData(): array
    {
        return [
            'teams' => Team::orderBy('name')->get(),
            'roleLabels' => RolesAndPermissionsSeeder::ROLE_LABELS,
        ];
    }
}
