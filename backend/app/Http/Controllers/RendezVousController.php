<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\RendezVousCreeNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
/**
 * SCRUM-605 / SCRUM-607 - Rendez-vous.
 *
 * Le CRUD ajoute par SCRUM-49 portait bien ses permissions `rendez-vous.*`,
 * mais aucun cloisonnement : les cinq roles detiennent RENDEZ_VOUS_READ, la
 * permission ne distinguait donc personne. Concretement, un patient obtenait
 * l'agenda complet de la clinique - motif de consultation d'autrui compris -
 * et un medecin pouvait modifier, voire reattribuer, le rendez-vous d'un
 * confrere.
 *
 * Trois protections sont ajoutees, toutes en reutilisant l'existant :
 *
 * 1. Le cloisonnement, via les Gates a ressource de SCRUM-528. RendezVous
 *    porte id_patient et id_medecin en direct, PerimetreDossier le traite
 *    donc deja sans rien y ajouter.
 *
 * 2. La reattribution. Modifier un rendez-vous permet d'en changer le patient
 *    ou le medecin : on verifie le perimetre AVANT et APRES, sans quoi il
 *    suffirait de s'attribuer le rendez-vous d'autrui pour y acceder.
 *
 * 3. La minimisation des donnees. index() et show() chargeaient les modeles
 *    Patient et Medecin entiers : CIN, groupe sanguin, email et telephone du
 *    patient, matricule, date d'embauche et tarif du medecin. Un agenda n'a
 *    besoin que de l'identite.
 */
class RendezVousController extends Controller
{
    /**
     * Colonnes strictement necessaires a l'affichage d'un agenda.
     *
     * La cle primaire est incluse : sans elle, Eloquent ne sait pas rattacher
     * la relation chargee a son rendez-vous.
     */
    private const COLONNES_PATIENT = ['id_patient', 'nom', 'prenom'];

    private const COLONNES_MEDECIN = ['id_medecin', 'nom', 'prenom', 'id_specialite'];

    /**
     * Regles de validation d'un rendez-vous.
     *
     * Deja posees par SCRUM-49 et conservees telles quelles : dates bornees,
     * heure de fin posterieure au debut, cles etrangeres verifiees.
     *
     * @return array<string, array<int, string>>
     */
private function regles(bool $creation): array
{
    return [
        'date_rendez_vous' => $creation
            ? ['required', 'date', 'after_or_equal:today']
            : ['required', 'date'],
        'heure_debut' => ['required', 'date_format:H:i'],
        'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        'motif' => ['required', 'string', 'max:255'],
        'statut' => [
            'required',
            'string',
            Rule::in([
                'Confirmé',
                'En attente',
                'En cours',
                'Annulé',
                'Terminé',
                // Valeurs historiques encore presentes dans certains jeux
                // de donnees/tests ; elles sont normalisees avant ecriture.
                'confirme',
                'en_attente',
                'en_cours',
                'annule',
                'annulee',
                'termine',
                'terminee',
            ]),
        ],
        'id_patient' => ['required', 'integer', 'exists:patients,id_patient'],
        'id_medecin' => ['required', 'integer', 'exists:medecins,id_medecin'],
    ];
}

    private function normaliserStatut(string $statut): string
    {
        return match ($statut) {
            'confirme' => 'Confirmé',
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'annule', 'annulee' => 'Annulé',
            'termine', 'terminee' => 'Terminé',
            default => $statut,
        };
    }

    /**
     * Restreint une requete au perimetre du role qui la formule.
     *
     * PerimetreDossier repond dossier par dossier ; pour une liste il faut
     * filtrer en base. La regle appliquee est la meme : un medecin son
     * agenda, un patient ses rendez-vous, le reste du personnel toute la
     * clinique.
     */
    private function limiterAuPerimetre(Builder $requete, Request $request): Builder
    {
        $utilisateur = $request->user();
        $role = Role::tryFrom((string) $utilisateur->role);

        if ($role === null) {
            // Role inattendu en base : on n'expose rien plutot que tout.
            return $requete->whereRaw('1 = 0');
        }

        if ($role->accedeATousLesDossiers()) {
            return $requete;
        }

        return $role === Role::MEDECIN
            ? $requete->where('id_medecin', $utilisateur->id_medecin)
            : $requete->where('id_patient', $utilisateur->id_patient);
    }

    private function aUnConflit(
    int $idMedecin,
    string $date,
    string $heureDebut,
    string $heureFin,
    ?int $idRendezVous = null
): bool {
    return RendezVous::query()
        ->where('id_medecin', $idMedecin)
        ->where('date_rendez_vous', $date)
        ->whereNotIn('statut', ['Annulé', 'annule', 'annulee'])
        ->when(
            $idRendezVous !== null,
            fn ($query) => $query->where(
                'id_rendez_vous',
                '!=',
                $idRendezVous
            )
        )
        ->where(function ($query) use ($heureDebut, $heureFin) {
            $query
                ->where('heure_debut', '<', $heureFin)
                ->where('heure_fin', '>', $heureDebut);
        })
        ->exists();
}
public function disponibilite(Request $request)
{
    $valide = $request->validate([
        'id_medecin' => [
            'required',
            'integer',
            'exists:medecins,id_medecin',
        ],
        'date_rendez_vous' => [
            'required',
            'date',
        ],
        'heure_debut' => [
            'required',
            'date_format:H:i',
        ],
        'heure_fin' => [
            'required',
            'date_format:H:i',
            'after:heure_debut',
        ],
        'id_rendez_vous' => [
            'nullable',
            'integer',
        ],
    ]);

    $idRendezVous = null;

    if (isset($valide['id_rendez_vous'])) {
        $rendezVousExclu = $this->trouverOuRefuser(
            RendezVous::find($valide['id_rendez_vous']),
            $request
        );

        Gate::authorize('rendez-vous.read', $rendezVousExclu);
        $idRendezVous = (int) $rendezVousExclu->id_rendez_vous;
    }

    $conflit = $this->aUnConflit(
        (int) $valide['id_medecin'],
        $valide['date_rendez_vous'],
        $valide['heure_debut'],
        $valide['heure_fin'],
        $idRendezVous
    );

    if ($conflit) {
        return response()->json([
            'disponible' => false,
            'message' => 'Le médecin n’est pas disponible pendant cette période.',
        ], 200);
    }

    return response()->json([
        'disponible' => true,
        'message' => 'Le médecin est disponible pendant cette période.',
    ], 200);
}
    public function index(Request $request)
    {
        $rendezVous = $this->limiterAuPerimetre(RendezVous::query(), $request)
            ->with([
                'patient:'.implode(',', self::COLONNES_PATIENT),
                'medecin:'.implode(',', self::COLONNES_MEDECIN),
            ])
            ->paginate(10);

        return response()->json([
            'rendez_vous' => $rendezVous,
        ], 200);
    }

    public function store(Request $request)
    {
        $valide = $request->validate($this->regles(creation: true));
        $valide['statut'] = $this->normaliserStatut($valide['statut']);

        $candidat = new RendezVous($valide);
        Gate::authorize('rendez-vous.create', $candidat);

        $rendezVous = DB::transaction(function () use ($valide, $candidat) {
            // Le verrou sur le medecin serialise les reservations concurrentes
            // pour un meme agenda : deux requetes simultanees ne peuvent pas
            // toutes les deux observer le creneau comme libre.
            DB::table('medecins')
                ->where('id_medecin', $valide['id_medecin'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->aUnConflit(
                (int) $valide['id_medecin'],
                $valide['date_rendez_vous'],
                $valide['heure_debut'],
                $valide['heure_fin']
            )) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Conflit de rendez-vous : le médecin n’est pas disponible pendant cette période.',
                ], 409));
            }

            $candidat->save();

            return $candidat;
        });

        $patientUser = User::where('id_patient', $rendezVous->id_patient)->first();
        $medecinUser = User::where('id_medecin', $rendezVous->id_medecin)->first();

        if ($patientUser) {
            $patientUser->notify(new RendezVousCreeNotification($rendezVous));
        }

        if ($medecinUser && (! $patientUser || $medecinUser->id !== $patientUser->id)) {
            $medecinUser->notify(new RendezVousCreeNotification($rendezVous));
        }

        return response()->json([
            'message' => 'Rendez-vous créé avec succès',
            'rendez_vous' => $rendezVous,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $rendezVous = $this->trouverOuRefuser(RendezVous::find($id), $request);

        Gate::authorize('rendez-vous.read', $rendezVous);

        $rendezVous->load([
            'patient:'.implode(',', self::COLONNES_PATIENT),
            'medecin:'.implode(',', self::COLONNES_MEDECIN),
        ]);

        // Lire l'agenda ne donne pas automatiquement le droit de lire le
        // dossier clinique. La consultation (diagnostic, observations, signes
        // vitaux...) n'est jointe que si sa propre Gate l'autorise.
        $consultation = $rendezVous->consultation()->first();

        if (
            $consultation !== null
            && Gate::allows('consultations.read', $consultation)
        ) {
            $rendezVous->setRelation('consultation', $consultation);
        }

        return response()->json([
            'rendez_vous' => $rendezVous,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $rendezVous = $this->trouverOuRefuser(RendezVous::find($id), $request);
        Gate::authorize('rendez-vous.update', $rendezVous);

        $valide = $request->validate($this->regles(creation: false));
        $valide['statut'] = $this->normaliserStatut($valide['statut']);

        $apresModification = (clone $rendezVous)->fill($valide);
        Gate::authorize('rendez-vous.update', $apresModification);

        $rendezVous = DB::transaction(function () use ($rendezVous, $valide) {
            DB::table('medecins')
                ->where('id_medecin', $valide['id_medecin'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->aUnConflit(
                (int) $valide['id_medecin'],
                $valide['date_rendez_vous'],
                $valide['heure_debut'],
                $valide['heure_fin'],
                (int) $rendezVous->id_rendez_vous
            )) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Conflit de rendez-vous : le médecin n’est pas disponible pendant cette période.',
                ], 409));
            }

            $rendezVous->update($valide);

            return $rendezVous->fresh();
        });

        foreach (
            User::where('id_patient', $rendezVous->id_patient)
                ->orWhere('id_medecin', $rendezVous->id_medecin)
                ->get() as $u
        ) {
            $u->notify(new \App\Notifications\RendezVousModifieNotification($rendezVous));
        }

        return response()->json([
            'message' => 'Rendez-vous modifié avec succès',
            'rendez_vous' => $rendezVous,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $rendezVous = $this->trouverOuRefuser(RendezVous::find($id), $request);

        Gate::authorize('rendez-vous.delete', $rendezVous);

        // SCRUM-607 : une consultation rattachee rend la suppression
        // impossible en base. Sans ce controle, la contrainte de cle
        // etrangere remonterait en 500 au lieu d'un conflit metier - meme
        // traitement que PatientController et OrdonnanceController.
        if ($rendezVous->consultation()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce rendez-vous car une consultation y est rattachée.',
            ], 409);
        }

        $rendezVous->delete();

        return response()->json([
            'message' => 'Rendez-vous supprimé avec succès',
        ], 200);
    }
    public function annuler(Request $request, $id)
    {
        $rendezVous = $this->trouverOuRefuser(RendezVous::find($id), $request);

        Gate::authorize('rendez-vous.update', $rendezVous);

        // Un rendez-vous déjà terminé ou déjà annulé ne peut pas être annulé.
        if (in_array(
            $this->normaliserStatut((string) $rendezVous->statut),
            ['Annulé', 'Terminé'],
            true
        )) {
            return response()->json([
                'message' => 'Ce rendez-vous ne peut plus être annulé (statut actuel : '.$rendezVous->statut.').',
            ], 409);
        }

        // Une consultation déjà rattachée signifie que le rendez-vous a eu lieu.
        if ($rendezVous->consultation()->exists()) {
            return response()->json([
                'message' => 'Impossible d’annuler ce rendez-vous car une consultation y est rattachée.',
            ], 409);
        }

        $rendezVous->statut = 'Annulé';
        $rendezVous->save();
        foreach (User::where('id_patient', $rendezVous->id_patient)->orWhere('id_medecin', $rendezVous->id_medecin)->get() as $u) { $u->notify(new \App\Notifications\RendezVousAnnuleNotification($rendezVous)); }
        return response()->json([
            'message' => 'Rendez-vous annulé avec succès',
            'rendez_vous' => $rendezVous,
        ], 200);
    }
    public function historiquePatient(Request $request, $idPatient)
{
    $requete = RendezVous::where('id_patient', $idPatient);

    $rendezVous = $this->limiterAuPerimetre($requete, $request)
        ->with([
            'patient:'.implode(',', self::COLONNES_PATIENT),
            'medecin:'.implode(',', self::COLONNES_MEDECIN),
        ])
        ->orderBy('date_rendez_vous', 'desc')
        ->orderBy('heure_debut', 'desc')
        ->get();

    return response()->json([
        'historique' => $rendezVous,
    ], 200);
}
}
