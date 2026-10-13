<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Resultat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResultatController extends Controller
{
    private function limiterAuPerimetre(Builder $requete, Request $request): Builder
    {
        $utilisateur = $request->user();
        $role = Role::tryFrom((string) $utilisateur->role);

        if ($role === null) {
            return $requete->whereRaw('1 = 0');
        }

        if ($role->accedeATousLesDossiers()) {
            return $requete;
        }

        return $requete->whereHas(
            'demandeExamen.consultation.rendezVous',
            function (Builder $q) use ($role, $utilisateur) {
                if ($role === Role::MEDECIN) {
                    $q->where('id_medecin', $utilisateur->id_medecin);
                } else {
                    $q->where('id_patient', $utilisateur->id_patient);
                }
            }
        );
    }

    public function index(Request $request)
    {
        $resultats = $this->limiterAuPerimetre(Resultat::query(), $request)
            ->with([
                'demandeExamen.consultation.rendezVous.patient',
                'demandeExamen.consultation.rendezVous.medecin',
            ])
            ->get();

        return response()->json(['resultats' => $resultats], 200);
    }

    public function show(Request $request, $id)
    {
        $resultat = $this->trouverOuRefuser(Resultat::find($id), $request);
        Gate::authorize('examens.read', $resultat);

        $resultat->load([
            'demandeExamen.consultation.rendezVous.patient',
            'demandeExamen.consultation.rendezVous.medecin',
        ]);

        return response()->json(['resultat' => $resultat], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->regles(creation: true));

        $resultat = new Resultat($validated);
        Gate::authorize('examens.update', $resultat);
        $resultat->save();

        return response()->json([
            'message' => 'Résultat ajouté avec succès',
            'resultat' => $resultat,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $resultat = $this->trouverOuRefuser(Resultat::find($id), $request);
        Gate::authorize('examens.update', $resultat);

        $validated = $request->validate($this->regles(creation: false));

        $apresModification = (clone $resultat)->fill($validated);
        Gate::authorize('examens.update', $apresModification);

        $resultat->update($validated);

        return response()->json([
            'message' => 'Résultat modifié avec succès',
            'resultat' => $resultat,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $resultat = $this->trouverOuRefuser(Resultat::find($id), $request);
        Gate::authorize('examens.delete', $resultat);

        $resultat->delete();

        return response()->json([
            'message' => 'Résultat supprimé avec succès',
        ], 200);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function regles(bool $creation): array
    {
        $requis = $creation ? 'required' : 'sometimes';

        return [
            'date_resultat' => [$requis, 'date'],
            'resultat_detaille' => [$requis, 'string'],
            'conclusion' => ['nullable', 'string'],
            'valeurs_mesurees' => ['nullable', 'string'],
            'fichier_resultat' => ['nullable', 'string'],
            'image_resultat' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'id_demande_examen' => [$requis, 'integer', 'exists:demandes_examen,id_demande_examen'],
        ];
    }
}
