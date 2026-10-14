<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'statut' => ['nullable', 'string', Rule::in(['Actif', 'Inactif', 'actif', 'inactif'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = User::query();

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['statut'])) {
            $query->whereRaw('LOWER(statut) = ?', [strtolower($validated['statut'])]);
        }

        if (! empty($validated['search'])) {
            $terme = $validated['search'];
            $query->where(function ($q) use ($terme) {
                $q->where('name', 'like', "%{$terme}%")
                    ->orWhere('email', 'like', "%{$terme}%");
            });
        }

        $users = $query->paginate(10);

        return response()->json([
            'users' => $users,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::enum(Role::class)],
            'statut' => ['nullable', 'string', Rule::in(['Actif', 'Inactif'])],
            'id_medecin' => ['nullable', 'integer', 'exists:medecins,id_medecin'],
            'id_patient' => ['nullable', 'integer', 'exists:patients,id_patient'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'statut' => $validated['statut'] ?? 'Actif',
            'id_medecin' => $validated['id_medecin'] ?? null,
            'id_patient' => $validated['id_patient'] ?? null,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user,
        ], 201);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'user' => $user,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'statut' => ['nullable', 'string', Rule::in(['Actif', 'Inactif'])],
            'id_medecin' => ['nullable', 'integer', 'exists:medecins,id_medecin'],
            'id_patient' => ['nullable', 'integer', 'exists:patients,id_patient'],
        ]);

        if (
            $request->user()->is($user)
            && strtolower((string) ($validated['statut'] ?? $user->statut)) === 'inactif'
        ) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver votre propre compte',
            ], 403);
        }

        $user->update($validated);

        if (strtolower((string) $user->statut) === 'inactif') {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Utilisateur modifié avec succès',
            'user' => $user,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Un administrateur ne peut pas se supprimer lui-même.
        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte',
            ], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès',
        ], 200);
    }
}