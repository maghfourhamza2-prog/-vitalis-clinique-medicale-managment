<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    /**
     * Le personnel global (admin, secretariat, infirmier) voit le registre.
     * Un medecin ne voit que les patients qui ont au moins un rendez-vous
     * rattache a son profil. Tout role inattendu recoit un ensemble vide.
     */
    private function limiterAuPerimetre(Builder $query, Request $request): Builder
    {
        $user = $request->user();
        $role = Role::tryFrom((string) $user->role);

        if ($role === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($role->accedeATousLesDossiers()) {
            return $query;
        }

        if ($role === Role::MEDECIN && $user->id_medecin !== null) {
            return $query->whereExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('rendez_vous')
                    ->whereColumn('rendez_vous.id_patient', 'patients.id_patient')
                    ->where('rendez_vous.id_medecin', $user->id_medecin);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function patientAccessible(Request $request, int|string $id): Patient
    {
        return $this->trouverOuRefuser(
            $this->limiterAuPerimetre(Patient::query(), $request)
                ->where('id_patient', $id)
                ->first(),
            $request
        );
    }

    public function index(Request $request)
    {
        return response()->json([
            'patients' => $this->limiterAuPerimetre(Patient::query(), $request)
                ->paginate(10),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglesPatient());

        $patient = Patient::create($validated);

        return response()->json([
            'message' => 'Patient ajouté avec succès',
            'patient' => $patient,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json([
            'patient' => $this->patientAccessible($request, $id),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $patient = $this->patientAccessible($request, $id);

        $validated = $request->validate($this->reglesPatient($patient->id_patient));

        $patient->update($validated);

        return response()->json([
            'message' => 'Patient modifié avec succès',
            'patient' => $patient,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Seul l'administrateur possede patients.delete, mais on garde la
        // meme resolution de dossier pour un comportement uniforme.
        $patient = $this->patientAccessible($request, $id);

        if (DB::table('rendez_vous')->where('id_patient', $patient->id_patient)->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce patient car il possède des rendez-vous associés.',
            ], 409);
        }

        $patient->delete();

        return response()->json([
            'message' => 'Patient supprimé avec succès.',
        ], 200);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'cin' => ['nullable', 'string', 'max:50'],
            'date_naissance' => ['nullable', 'date'],
        ]);

        $criteres = array_filter(
            $validated,
            fn ($value) => $value !== null && $value !== ''
        );

        if ($criteres === []) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => [
                    'cin' => ['Indiquez au moins un critere de recherche (cin ou date_naissance).'],
                ],
            ], 422);
        }

        $query = $this->limiterAuPerimetre(Patient::query(), $request);

        if (isset($criteres['cin'])) {
            $query->where('cin', $criteres['cin']);
        }

        if (isset($criteres['date_naissance'])) {
            $query->where('date_naissance', $criteres['date_naissance']);
        }

        return response()->json([
            'patients' => $query->paginate(10),
        ], 200);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function reglesPatient(?int $patientId = null): array
    {
        $uniqueCin = 'unique:patients,cin'.($patientId === null ? '' : ','.$patientId.',id_patient');
        $uniqueEmail = 'unique:patients,email'.($patientId === null ? '' : ','.$patientId.',id_patient');

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'date_naissance' => ['required', 'date', 'before:today'],
            'sexe' => ['required', 'string', 'max:20'],
            'cin' => ['required', 'string', 'max:50', $uniqueCin],
            'telephone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', $uniqueEmail],
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
        ];
    }
}
