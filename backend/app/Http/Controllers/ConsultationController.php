<?php

namespace App\Http\Controllers;

use App\Auth\PerimetreDossier;
use App\Enums\Role;
use App\Models\Consultation;
use App\Models\RendezVous;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * SCRUM-37 - Gestion des consultations.
 *
 * Une consultation n'a pas de patient/medecin en direct : elle passe par le
 * rendez-vous (id_rendez_vous). Le cloisonnement (PerimetreDossier) le sait
 * deja via viaRendezVous(), on reutilise donc exactement le meme mecanisme
 * que RendezVousController et OrdonnanceController.
 *
 * Creer une consultation, c'est acter que le rendez-vous a eu lieu : le
 * statut du rendez-vous passe a "Terminé" au moment du store().
 */
class ConsultationController extends Controller
{
    private const COLONNES_PATIENT = ['id_patient', 'nom', 'prenom', 'cin'];

    private const COLONNES_MEDECIN = ['id_medecin', 'nom', 'prenom', 'id_specialite'];

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regles(bool $creation): array
    {
        return [
            'motif' => ['required', 'string', 'max:255'],
            'diagnostic' => ['required', 'string', 'max:10000'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'poids' => ['nullable', 'numeric', 'between:0,999.99'],
            'taille' => ['nullable', 'numeric', 'between:0,999.99'],
            'tension_arterielle' => ['nullable', 'string', 'max:20'],
            'temperature' => ['nullable', 'numeric', 'between:0,99.9'],
            'id_rendez_vous' => $creation
                ? ['required', 'integer', 'exists:rendez_vous,id_rendez_vous']
                : ['required', 'integer', 'exists:rendez_vous,id_rendez_vous'],
        ];
    }

    /**
     * Restreint une requete au perimetre du role : un medecin ses
     * consultations, un patient les siennes, le reste du personnel tout.
     * On filtre via la relation rendezVous, seule porteuse de id_patient /
     * id_medecin.
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

        return $requete->whereHas('rendezVous', function (Builder $q) use ($role, $utilisateur) {
            $role === Role::MEDECIN
                ? $q->where('id_medecin', $utilisateur->id_medecin)
                : $q->where('id_patient', $utilisateur->id_patient);
        });
    }

    public function index(Request $request)
    {
        $requete = $this->limiterAuPerimetre(Consultation::query(), $request)
            ->with([
                'rendezVous.patient:'.implode(',', self::COLONNES_PATIENT),
                'rendezVous.medecin:'.implode(',', self::COLONNES_MEDECIN),
                'rendezVous.medecin.specialite',
            ]);

        // Filtres (tous facultatifs)
        if ($request->filled('id_medecin')) {
            $requete->whereHas('rendezVous', fn ($q) => $q->where('id_medecin', $request->integer('id_medecin')));
        }

        if ($request->filled('date')) {
            $requete->whereHas('rendezVous', fn ($q) => $q->whereDate('date_rendez_vous', $request->date('date')));
        }

        if ($request->filled('statut')) {
            $requete->whereHas('rendezVous', fn ($q) => $q->where('statut', $request->string('statut')));
        }

        if ($request->filled('recherche')) {
            $terme = $request->string('recherche');
            $requete->where(function (Builder $q) use ($terme) {
                $q->where('motif', 'like', "%{$terme}%")
                    ->orWhere('diagnostic', 'like', "%{$terme}%")
                    ->orWhereHas('rendezVous.patient', function (Builder $qp) use ($terme) {
                        $qp->where('nom', 'like', "%{$terme}%")
                            ->orWhere('prenom', 'like', "%{$terme}%");
                    })
                    ->orWhereHas('rendezVous.medecin', function (Builder $qm) use ($terme) {
                        $qm->where('nom', 'like', "%{$terme}%")
                            ->orWhere('prenom', 'like', "%{$terme}%");
                    });
            });
        }

        $consultations = $requete->latest('id_consultation')->paginate(10);

        return response()->json([
            'consultations' => $consultations,
        ], 200);
    }

    public function store(Request $request)
    {
        $valide = $request->validate($this->regles(creation: true));

        $consultation = new Consultation($valide);
        Gate::authorize('consultations.create', $consultation);

        $consultation = DB::transaction(function () use ($valide) {
            $rendezVous = RendezVous::whereKey($valide['id_rendez_vous'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($rendezVous->consultation()->exists()) {
                abort(409, 'Une consultation existe déjà pour ce rendez-vous.');
            }

            if (in_array($rendezVous->statut, ['Annulé', 'annule', 'annulee'], true)) {
                abort(409, 'Impossible de créer une consultation pour un rendez-vous annulé.');
            }

            $consultation = Consultation::create($valide);

            $rendezVous->update([
                'statut' => 'Terminé',
            ]);

            return $consultation;
        });

        return response()->json([
            'message' => 'Consultation créée avec succès',
            'consultation' => $consultation,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $consultation = $this->trouverOuRefuser(Consultation::find($id), $request);

        Gate::authorize('consultations.read', $consultation);

        $consultation->load([
            'rendezVous.patient:'.implode(',', self::COLONNES_PATIENT),
            'rendezVous.medecin:'.implode(',', self::COLONNES_MEDECIN),
            'rendezVous.medecin.specialite',
        ]);

        return response()->json([
            'consultation' => $consultation,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $consultation = $this->trouverOuRefuser(Consultation::find($id), $request);

        Gate::authorize('consultations.update', $consultation);

        $valide = $request->validate($this->regles(creation: false));

        $apresModification = (clone $consultation)->fill($valide);
        Gate::authorize('consultations.update', $apresModification);

        $consultation->update($valide);

        return response()->json([
            'message' => 'Consultation modifiée avec succès',
            'consultation' => $consultation,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $consultation = $this->trouverOuRefuser(Consultation::find($id), $request);

        Gate::authorize('consultations.delete', $consultation);

        // Une ordonnance ou une demande d'examen rattachee rend la
        // suppression impossible (meme logique que RendezVousController).
        if ($consultation->ordonnances()->exists() || $consultation->demandesExamen()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette consultation car des ordonnances ou examens y sont rattachés.',
            ], 409);
        }

        $consultation->delete();

        return response()->json([
            'message' => 'Consultation supprimée avec succès',
        ], 200);
    }

    /**
     * SCRUM-37 - Cartes du tableau "Gestion des consultations".
     */
    public function stats(Request $request)
    {
        $requeteBase = $this->limiterAuPerimetre(Consultation::query(), $request);

        $totalConsultations = (clone $requeteBase)->count();

        $patientsConsultes = (clone $requeteBase)
            ->join('rendez_vous', 'consultations.id_rendez_vous', '=', 'rendez_vous.id_rendez_vous')
            ->distinct('rendez_vous.id_patient')
            ->count('rendez_vous.id_patient');

        $medecinsActifs = (clone $requeteBase)
            ->join('rendez_vous', 'consultations.id_rendez_vous', '=', 'rendez_vous.id_rendez_vous')
            ->distinct('rendez_vous.id_medecin')
            ->count('rendez_vous.id_medecin');

        $ceMoisCi = (clone $requeteBase)
            ->join('rendez_vous', 'consultations.id_rendez_vous', '=', 'rendez_vous.id_rendez_vous')
            ->whereMonth('rendez_vous.date_rendez_vous', now()->month)
            ->whereYear('rendez_vous.date_rendez_vous', now()->year)
            ->count();

        return response()->json([
            'total_consultations' => $totalConsultations,
            'patients_consultes' => $patientsConsultes,
            'medecins_actifs' => $medecinsActifs,
            'consultations_ce_mois' => $ceMoisCi,
        ], 200);
    }
}