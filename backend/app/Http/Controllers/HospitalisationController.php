<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Chambre;
use App\Models\Hospitalisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HospitalisationController extends Controller
{
    /**
     * Applique le meme cloisonnement que les autres dossiers medicaux :
     * medecin -> ses hospitalisations, patient -> les siennes, roles globaux -> tout.
     */
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

        return $role === Role::MEDECIN
            ? $requete->where('id_medecin', $utilisateur->id_medecin)
            : $requete->where('id_patient', $utilisateur->id_patient);
    }

    public function index(Request $request)
    {
        $hospitalisations = $this->limiterAuPerimetre(Hospitalisation::query(), $request)
            ->with(['patient', 'chambre', 'medecin'])
            ->get();

        $this->masquerDonneesCliniquesPourSecretariat($hospitalisations, $request);

        return response()->json(['hospitalisations' => $hospitalisations], 200);
    }

    public function show(Request $request, $id)
    {
        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.read', $hospitalisation);

        $hospitalisation->load(['patient', 'chambre', 'medecin']);
        $this->masquerDonneesCliniquesPourSecretariat(collect([$hospitalisation]), $request);

        return response()->json(['hospitalisation' => $hospitalisation], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->regles(creation: true));

        $hospitalisation = new Hospitalisation($validated);
        Gate::authorize('hospitalisations.create', $hospitalisation);
        $hospitalisation->save();

        return response()->json([
            'message' => 'Hospitalisation ajoutée avec succès',
            'hospitalisation' => $hospitalisation,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.update', $hospitalisation);

        $validated = $request->validate($this->regles(creation: false));

        $apresModification = (clone $hospitalisation)->fill($validated);
        Gate::authorize('hospitalisations.update', $apresModification);

        $hospitalisation->update($validated);

        return response()->json([
            'message' => 'Hospitalisation modifiée avec succès',
            'hospitalisation' => $hospitalisation,
        ], 200);
    }

    public function admettre(Request $request)
    {
        $validated = $request->validate([
            'date_entree' => ['required', 'date'],
            'heure_entree' => ['required', 'date_format:H:i'],
            'motif_hospitalisation' => ['required', 'string'],
            'diagnostic_entree' => ['required', 'string'],
            'observations' => ['nullable', 'string'],
            'id_patient' => ['required', 'integer', 'exists:patients,id_patient'],
            'id_chambre' => ['required', 'integer', 'exists:chambres,id_chambre'],
            'id_medecin' => ['required', 'integer', 'exists:medecins,id_medecin'],
        ]);

        $candidat = new Hospitalisation([
            ...$validated,
            'date_sortie' => null,
            'statut' => 'en_cours',
        ]);
        Gate::authorize('hospitalisations.create', $candidat);

        $hospitalisation = DB::transaction(function () use ($candidat) {
            $chambre = Chambre::whereKey($candidat->id_chambre)
                ->lockForUpdate()
                ->firstOrFail();

            $nombrePatients = Hospitalisation::where('id_chambre', $chambre->id_chambre)
                ->where('statut', 'en_cours')
                ->count();

            if ($nombrePatients >= $chambre->capacite) {
                throw ValidationException::withMessages([
                    'id_chambre' => 'Cette chambre a atteint sa capacité maximale.',
                ]);
            }

            $candidat->save();
            $nombrePatients++;

            $chambre->update([
                'statut' => $nombrePatients >= $chambre->capacite ? 'occupee' : 'disponible',
            ]);

            return $candidat;
        });

        return response()->json([
            'message' => 'Patient admis avec succès',
            'hospitalisation' => $hospitalisation,
        ], 201);
    }

    public function sortir(Request $request, $id)
    {
        $validated = $request->validate([
            'date_sortie' => ['required', 'date'],
        ]);

        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.update', $hospitalisation);

        DB::transaction(function () use ($validated, $hospitalisation) {
            if ($hospitalisation->statut !== 'en_cours') {
                throw ValidationException::withMessages([
                    'hospitalisation' => 'Cette hospitalisation est déjà terminée.',
                ]);
            }

            if ($validated['date_sortie'] < $hospitalisation->date_entree) {
                throw ValidationException::withMessages([
                    'date_sortie' => 'La date de sortie ne peut pas être antérieure à la date d\'entrée.',
                ]);
            }

            $hospitalisation->update([
                'date_sortie' => $validated['date_sortie'],
                'statut' => 'terminee',
            ]);

            Chambre::whereKey($hospitalisation->id_chambre)
                ->lockForUpdate()
                ->firstOrFail()
                ->update(['statut' => 'disponible']);
        });

        return response()->json([
            'message' => 'Sortie du patient enregistrée avec succès',
            'hospitalisation' => $hospitalisation->fresh(),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.delete', $hospitalisation);

        $hospitalisation->delete();

        return response()->json([
            'message' => 'Hospitalisation supprimée avec succès',
        ], 200);
    }

    private function masquerDonneesCliniquesPourSecretariat($hospitalisations, Request $request): void
    {
        if (Role::tryFrom((string) $request->user()->role) !== Role::SECRETAIRE) {
            return;
        }

        foreach ($hospitalisations as $hospitalisation) {
            $hospitalisation->makeHidden([
                'motif_hospitalisation',
                'diagnostic_entree',
                'observations',
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
            'date_entree' => [$requis, 'date'],
            'heure_entree' => [$requis, 'date_format:H:i'],
            'date_sortie' => ['nullable', 'date'],
            'motif_hospitalisation' => [$requis, 'string'],
            'diagnostic_entree' => [$requis, 'string'],
            'statut' => [$requis, 'string'],
            'observations' => ['nullable', 'string'],
            'id_patient' => [$requis, 'integer', 'exists:patients,id_patient'],
            'id_chambre' => [$requis, 'integer', 'exists:chambres,id_chambre'],
            'id_medecin' => [$requis, 'integer', 'exists:medecins,id_medecin'],
        ];
    }
}
