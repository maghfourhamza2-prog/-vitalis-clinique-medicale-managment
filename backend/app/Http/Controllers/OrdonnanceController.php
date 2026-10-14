<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Ordonnance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * SCRUM-564 / SCRUM-566 - Ordonnances.
 *
 * Deux protections completent le middleware `can:` pose sur les routes.
 *
 * 1. Le cloisonnement. Une permission dit "a le droit de lire une
 *    ordonnance", pas "celle-ci". Le role patient detient ORDONNANCES_READ
 *    pour SON dossier (SCRUM-524) : sans verification du dossier vise, cette
 *    permission ouvrirait l'ordonnance de tout le monde. On s'appuie sur les
 *    Gates a ressource de SCRUM-528, qui delegent a PerimetreDossier.
 *
 * 2. La validation. Les entrees etaient reprises telles quelles via
 *    $request->only(), sans aucune regle : un id_consultation inexistant
 *    provoquait une erreur SQL, et rien ne bornait la taille des champs.
 */
class OrdonnanceController extends Controller
{
    public function consultationsDisponibles(Request $request)
{
    $user = $request->user();

    $query = DB::table('consultations')
        ->join(
            'rendez_vous',
            'consultations.id_rendez_vous',
            '=',
            'rendez_vous.id_rendez_vous'
        )
        ->join(
            'patients',
            'rendez_vous.id_patient',
            '=',
            'patients.id_patient'
        )
        ->join(
            'medecins',
            'rendez_vous.id_medecin',
            '=',
            'medecins.id_medecin'
        )
         
        ->select(
    'consultations.id_consultation',
    'consultations.diagnostic',
    'patients.nom as patient_nom',
    'patients.prenom as patient_prenom',
    'patients.cin',
    'medecins.nom as medecin_nom',
    'medecins.prenom as medecin_prenom'
);

    if ($user->role === Role::MEDECIN->value) {
        $query->where('rendez_vous.id_medecin', $user->id_medecin);
    }

    return response()->json([
        'consultations' => $query->get(),
    ]);
}
    /**
     * Regles communes a la creation et a la modification.
     *
     * `exists` sur id_consultation evite qu'une ordonnance soit rattachee a
     * une consultation inexistante : c'est cette cle qui determine a quel
     * patient et a quel medecin l'ordonnance appartient, donc qui pourra la
     * lire ensuite.
     *
     * @return array<string, array<int, string>>
     */
    private function regles(bool $creation): array
    {
        $requis = $creation ? 'required' : 'sometimes';

        return [
            'date_ordonnance' => [$requis, 'date'],
            'instructions_generales' => [$requis, 'string', 'max:2000'],
            'duree_traitement' => [$requis, 'string', 'max:255'],
            'type' => [$requis, 'string', 'max:255'],
            'id_consultation' => [$requis, 'integer', 'exists:consultations,id_consultation'],
        ];
    }

    /**
     * Restreint une requete au perimetre du role qui la formule.
     *
     * PerimetreDossier repond dossier par dossier ; pour une liste il faut
     * filtrer en base, sans quoi il faudrait charger toute la clinique pour
     * n'en garder que quelques lignes. La regle appliquee est la meme : un
     * medecin ses patients, un patient son dossier, le reste du personnel
     * tout l'etablissement.
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

        return $requete->whereHas(
            'consultation.rendezVous',
            function ($q) use ($role, $utilisateur) {
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
    $requete = Ordonnance::with([
        'consultation:id_consultation,id_rendez_vous,diagnostic',
        'consultation.rendezVous:id_rendez_vous,id_patient,id_medecin',
        'consultation.rendezVous.patient:id_patient,nom,prenom,cin',
        'consultation.rendezVous.medecin:id_medecin,nom,prenom,id_specialite',
        'lignes.medicament',
    ]);

    $ordonnances = $this->limiterAuPerimetre($requete, $request)
        ->orderBy('date_ordonnance', 'desc')
        ->get();

    return response()->json([
        'ordonnances' => $ordonnances,
    ], 200);
}

    public function show(Request $request, $id)
    {
        $ordonnance = $this->trouverOuRefuser(Ordonnance::find($id), $request);

        // Repond 403 si l'ordonnance ne releve pas du perimetre de l'appelant.
        Gate::authorize('ordonnances.read', $ordonnance);

        return response()->json([
            'ordonnance' => $ordonnance,
        ], 200);
    }

    public function imprimer(Request $request, $id)
    {
        $ordonnance = $this->trouverOuRefuser(
        Ordonnance::with([
            'consultation:id_consultation,id_rendez_vous',
            'consultation.rendezVous:id_rendez_vous,id_patient,id_medecin',
            'consultation.rendezVous.patient:id_patient,nom,prenom,date_naissance,cin',
            'consultation.rendezVous.medecin:id_medecin,nom,prenom,matricule',
            'lignes.medicament',
        ])->find($id),
        $request
        );

        Gate::authorize('ordonnances.read', $ordonnance);

        return response()->json([
        'ordonnance' => $ordonnance,
        'patient' => $ordonnance->consultation?->patient(),
        'medecin' => $ordonnance->consultation?->medecin(),
         ], 200);
    }

    public function telecharger(Request $request, $id)
    {
        $ordonnance = $this->trouverOuRefuser(
        Ordonnance::with([
            'consultation:id_consultation,id_rendez_vous',
            'consultation.rendezVous:id_rendez_vous,id_patient,id_medecin',
            'consultation.rendezVous.patient:id_patient,nom,prenom,date_naissance,cin',
            'consultation.rendezVous.medecin:id_medecin,nom,prenom,matricule',
            'lignes.medicament',
        ])->find($id),
        $request
         );

        Gate::authorize('ordonnances.read', $ordonnance);

        $pdf = Pdf::loadView('ordonnances.pdf', [
        'ordonnance' => $ordonnance,
        'patient' => $ordonnance->consultation?->patient(),
        'medecin' => $ordonnance->consultation?->medecin(),
        ]);

         return $pdf->download(
        'ordonnance-' . $ordonnance->id_ordonnance . '.pdf'
        );
    }


   public function store(Request $request)
   {
        $valide = $request->validate($this->regles(creation: true));

        $ordonnance = new Ordonnance($valide);
        Gate::authorize('ordonnances.create', $ordonnance);
        $ordonnance->save();

        return response()->json([
            'message' => 'Ordonnance ajoutée avec succès',
            'ordonnance' => $ordonnance,
        ], 201);
   }

   public function update(Request $request, $id)
   {
        $ordonnance = $this->trouverOuRefuser(
            Ordonnance::find($id),
            $request
        );

        Gate::authorize('ordonnances.update', $ordonnance);

        $valide = $request->validate($this->regles(creation: false));

        $apresModification = (clone $ordonnance)->fill($valide);
        Gate::authorize('ordonnances.update', $apresModification);

        $ordonnance->update($valide);

        return response()->json([
            'message' => 'Ordonnance modifiée avec succès',
            'ordonnance' => $ordonnance,
        ], 200);
   }

    /**
     * Historique des ordonnances d'un patient.
     *
     * SCRUM-564 : l'identifiant vient de l'URL. Il suffisait de le changer
     * pour obtenir l'historique medicamenteux de n'importe quel patient de la
     * clinique - un antecedent de traitement est une donnee de sante.
     *
     * Le filtre de perimetre s'applique en plus du patient demande : un
     * patient qui reclame l'historique d'un autre obtient une liste vide, et
     * non un refus. On ne revele donc pas non plus quels identifiants
     * existent.
     */
    public function historiquePatient(Request $request, $idPatient)
    {
        $requete = Ordonnance::whereHas(
            'consultation.rendezVous',
            fn ($q) => $q->where('id_patient', $idPatient)
        );

        $ordonnances = $this->limiterAuPerimetre($requete, $request)
            ->orderBy('date_ordonnance', 'desc')
            ->get();

        return response()->json([
            'historique' => $ordonnances,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $ordonnance = $this->trouverOuRefuser(Ordonnance::find($id), $request);

        Gate::authorize('ordonnances.delete', $ordonnance);

        // SCRUM-566 : sans ce controle, la contrainte de cle etrangere des
        // lignes remontait en PDOException, donc en 500 - avec la trace SQL
        // quand APP_DEBUG est actif. Une dependance qui empeche la
        // suppression est un conflit metier (409), pas une panne serveur.
        // Meme traitement que PatientController pour ses rendez-vous.
        if (DB::table('ligne_ordonnances')->where('id_ordonnance', $ordonnance->id_ordonnance)->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette ordonnance car elle contient des médicaments.',
            ], 409);
        }

        $ordonnance->delete();

        return response()->json([
            'message' => 'Ordonnance supprimée avec succès',
        ], 200);
    }
}
