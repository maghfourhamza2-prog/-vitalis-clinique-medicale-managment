<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\DemandeExamen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DemandeExamenController extends Controller
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

        return $requete->whereHas('consultation.rendezVous', function (Builder $q) use ($role, $utilisateur) {
            if ($role === Role::MEDECIN) {
                $q->where('id_medecin', $utilisateur->id_medecin);
            } else {
                $q->where('id_patient', $utilisateur->id_patient);
            }
        });
    }

    public function index(Request $request)
    {
        $demandes = $this->limiterAuPerimetre(DemandeExamen::query(), $request)
            ->with([
                'consultation:id_consultation,id_rendez_vous',
                'consultation.rendezVous:id_rendez_vous,id_patient,id_medecin',
                'consultation.rendezVous.patient:id_patient,nom,prenom',
                'consultation.rendezVous.medecin:id_medecin,nom,prenom',
            ])
            ->get();

        $this->masquerDonneesCliniquesPourSecretariat($demandes, $request);

        return response()->json(['demandes_examens' => $demandes], 200);
    }

    public function show(Request $request, $id)
    {
        $demande = $this->trouverOuRefuser(DemandeExamen::find($id), $request);
        Gate::authorize('examens.read', $demande);

        $demande->load([
            'consultation:id_consultation,id_rendez_vous',
            'consultation.rendezVous:id_rendez_vous,id_patient,id_medecin',
            'consultation.rendezVous.patient:id_patient,nom,prenom',
            'consultation.rendezVous.medecin:id_medecin,nom,prenom',
        ]);

        $this->masquerDonneesCliniquesPourSecretariat(collect([$demande]), $request);

        return response()->json(['demande_examen' => $demande], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->regles(creation: true));

        $demande = new DemandeExamen($validated);
        Gate::authorize('examens.create', $demande);
        $demande->save();

        return response()->json([
            'message' => 'Demande d\'examen ajoutée avec succès',
            'demande_examen' => $demande,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $demande = $this->trouverOuRefuser(DemandeExamen::find($id), $request);
        Gate::authorize('examens.update', $demande);

        $validated = $request->validate($this->regles(creation: false));

        $apresModification = (clone $demande)->fill($validated);
        Gate::authorize('examens.update', $apresModification);

        $demande->update($validated);

        return response()->json([
            'message' => 'Demande d\'examen modifiée avec succès',
            'demande_examen' => $demande,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $demande = $this->trouverOuRefuser(DemandeExamen::find($id), $request);
        Gate::authorize('examens.delete', $demande);

        $demande->delete();

        return response()->json([
            'message' => 'Demande d\'examen supprimée avec succès',
        ], 200);
    }

    private function masquerDonneesCliniquesPourSecretariat($demandes, Request $request): void
    {
        if (Role::tryFrom((string) $request->user()->role) !== Role::SECRETAIRE) {
            return;
        }

        foreach ($demandes as $demande) {
            $demande->makeHidden([
                'indications_cliniques',
                'observation',
            ]);
        }
    }


    /**
     * @return array<string, array<int, string>>
     */
    private function regles(bool $creation): array
    {
        $requis = $creation ? 'required' : 'sometimes';

        return [
            'date_demande' => [$requis, 'date'],
            'type_examen' => [$requis, 'string', 'max:255'],
            'niveau_urgence' => [$requis, Rule::in(['normal', 'urgent'])],
            'indications_cliniques' => [$requis, 'string', 'max:5000'],
            'statut' => [$requis, Rule::in(['en_attente', 'en_cours', 'realise'])],
            'date_prevue' => [$requis, 'date'],
            'date_realisation' => ['nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:5000'],
            'id_consultation' => [$requis, 'integer', 'exists:consultations,id_consultation'],
        ];
    }
}
