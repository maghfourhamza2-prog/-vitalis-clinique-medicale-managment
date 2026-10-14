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
            ->with([
                'patient:id_patient,nom,prenom',
                'chambre:id_chambre,numero_chambre,type_chambre,etage,capacite,statut',
                'medecin:id_medecin,nom,prenom',
            ])
            ->get();

        $this->masquerDonneesCliniquesPourSecretariat($hospitalisations, $request);

        return response()->json(['hospitalisations' => $hospitalisations], 200);
    }

    public function show(Request $request, $id)
    {
        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.read', $hospitalisation);

        $hospitalisation->load([
            'patient:id_patient,nom,prenom',
            'chambre:id_chambre,numero_chambre,type_chambre,etage,capacite,statut',
            'medecin:id_medecin,nom,prenom',
        ]);
        $this->masquerDonneesCliniquesPourSecretariat(collect([$hospitalisation]), $request);

        return response()->json(['hospitalisation' => $hospitalisation], 200);
    }

    /**
     * Compatibilite de l'ancien POST /hospitalisations.
     * Toute creation passe par la meme logique atomique que /admettre afin
     * qu'aucun client ne puisse contourner le controle de capacite.
     */
    public function store(Request $request)
    {
        return $this->admettre($request);
    }

    public function update(Request $request, $id)
    {
        $hospitalisation = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.update', $hospitalisation);

        $validated = $request->validate($this->reglesModification());

        $apresModification = (clone $hospitalisation)->fill($validated);
        Gate::authorize('hospitalisations.update', $apresModification);

        $hospitalisation = DB::transaction(function () use ($id, $validated) {
            $hospitalisation = Hospitalisation::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $ancienneChambreId = (int) $hospitalisation->id_chambre;
            $nouvelleChambreId = (int) ($validated['id_chambre'] ?? $ancienneChambreId);

            if (
                $hospitalisation->statut === 'en_cours'
                && $nouvelleChambreId !== $ancienneChambreId
            ) {
                $ids = [$ancienneChambreId, $nouvelleChambreId];
                sort($ids);

                $chambres = Chambre::whereIn('id_chambre', $ids)
                    ->orderBy('id_chambre')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_chambre');

                $ancienneChambre = $chambres->get($ancienneChambreId);
                $nouvelleChambre = $chambres->get($nouvelleChambreId);

                if ($ancienneChambre === null || $nouvelleChambre === null) {
                    abort(404);
                }

                $occupantsNouvelle = Hospitalisation::where('id_chambre', $nouvelleChambreId)
                    ->where('statut', 'en_cours')
                    ->count();

                if ($occupantsNouvelle >= $nouvelleChambre->capacite) {
                    throw ValidationException::withMessages([
                        'id_chambre' => 'Cette chambre a atteint sa capacité maximale.',
                    ]);
                }

                $hospitalisation->update($validated);

                $this->synchroniserStatutChambre($ancienneChambre);
                $this->synchroniserStatutChambre($nouvelleChambre);
            } else {
                $hospitalisation->update($validated);
            }

            return $hospitalisation->fresh();
        });

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
            'motif_hospitalisation' => ['required', 'string', 'max:5000'],
            'diagnostic_entree' => ['required', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
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
            $this->synchroniserStatutChambre($chambre);

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

        $cible = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.update', $cible);

        $hospitalisation = DB::transaction(function () use ($validated, $id) {
            $hospitalisation = Hospitalisation::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

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

            $chambre = Chambre::whereKey($hospitalisation->id_chambre)
                ->lockForUpdate()
                ->firstOrFail();

            $hospitalisation->update([
                'date_sortie' => $validated['date_sortie'],
                'statut' => 'terminee',
            ]);

            $this->synchroniserStatutChambre($chambre);

            return $hospitalisation->fresh();
        });

        return response()->json([
            'message' => 'Sortie du patient enregistrée avec succès',
            'hospitalisation' => $hospitalisation,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $cible = $this->trouverOuRefuser(Hospitalisation::find($id), $request);
        Gate::authorize('hospitalisations.delete', $cible);

        DB::transaction(function () use ($id) {
            $hospitalisation = Hospitalisation::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $chambre = Chambre::whereKey($hospitalisation->id_chambre)
                ->lockForUpdate()
                ->firstOrFail();

            $hospitalisation->delete();
            $this->synchroniserStatutChambre($chambre);
        });

        return response()->json([
            'message' => 'Hospitalisation supprimée avec succès',
        ], 200);
    }

    private function synchroniserStatutChambre(Chambre $chambre): void
    {
        $occupants = Hospitalisation::where('id_chambre', $chambre->id_chambre)
            ->where('statut', 'en_cours')
            ->count();

        $chambre->update([
            'statut' => $occupants >= $chambre->capacite ? 'occupee' : 'disponible',
        ]);
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
    private function reglesModification(): array
    {
        return [
            'date_entree' => ['sometimes', 'required', 'date'],
            'heure_entree' => ['sometimes', 'required', 'date_format:H:i'],
            'motif_hospitalisation' => ['sometimes', 'required', 'string', 'max:5000'],
            'diagnostic_entree' => ['sometimes', 'required', 'string', 'max:5000'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'id_patient' => ['sometimes', 'required', 'integer', 'exists:patients,id_patient'],
            'id_chambre' => ['sometimes', 'required', 'integer', 'exists:chambres,id_chambre'],
            'id_medecin' => ['sometimes', 'required', 'integer', 'exists:medecins,id_medecin'],
        ];
    }
}
