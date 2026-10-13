<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = array_map(
            fn (Role $role) => $role->value,
            Role::cases()
        );

        return response()->json([
            'roles' => $roles,
        ]);
    }

    /**
     * Attribuer ou modifier le role d'un utilisateur.
     *
     * SCRUM-527 - L'operation la plus privilegiee de l'application : elle
     * decide de qui peut lire les dossiers medicaux. Deux protections s'y
     * ajoutent au controle 'roles.manage' de la route.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                Rule::enum(Role::class),
            ],
        ]);

        // 1. Un administrateur ne modifie pas son propre role.
        //
        // Se retrograder est irreversible depuis l'application : aucune route
        // ne permet de creer un administrateur, seul un administrateur
        // existant peut en promouvoir un autre. Un simple clic pouvait donc
        // priver definitivement la clinique de la gestion des roles, sans
        // autre issue qu'une intervention en base.
        //
        // Cette seule regle garantit aussi qu'il reste toujours au moins un
        // administrateur : pour tomber a zero il faudrait que le dernier se
        // retrograde lui-meme, ce qui est desormais refuse.
        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre role',
            ], 403);
        }

        $ancienRole = $user->role;

        $user->role = $validated['role'];
        $user->save();

        // Une modification de privileges invalide toutes les sessions
        // existantes afin qu'aucun appareil ne conserve un contexte ancien.
        $user->tokens()->delete();

        // 2. Toute elevation ou reduction de privileges est tracee.
        //
        // Si un compte se met a consulter des dossiers qu'il ne devrait pas,
        // cette ligne est la seule facon de savoir qui lui a donne ce droit
        // et quand.
        Log::info('Role utilisateur modifie', [
            'cible_id' => $user->id,
            'ancien_role' => $ancienRole,
            'nouveau_role' => $user->role,
            'auteur_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Role modifie avec succes',
            'user' => $user,
        ]);
    }
}