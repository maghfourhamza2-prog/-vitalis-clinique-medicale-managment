<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = match ($user->role) {
            Role::ADMINISTRATEUR->value => $this->adminDashboard(),
            Role::MEDECIN->value        => $this->medecinDashboard($user),
            Role::SECRETAIRE->value     => $this->secretaireDashboard(),
            Role::INFIRMIER->value      => $this->infirmierDashboard(),
            Role::PATIENT->value        => $this->patientDashboard($user),
            default => [],
        };

        return response()->json([
            'role' => $user->role,
            'data' => $data,
        ]);
    }

    private function adminDashboard(): array
    {
        return [
            'total_patients'            => DB::table('patients')->count(),
            'total_medecins'            => DB::table('medecins')->count(),
            'rendez_vous_aujourdhui'    => DB::table('rendez_vous')
                ->whereDate('date_rendez_vous', now()->toDateString())
                ->count(),
            'hospitalisations_en_cours' => DB::table('hospitalisations')
                ->whereNull('date_sortie')
                ->count(),
            'factures_impayees'         => DB::table('factures')
                ->where('statut_paiement', '!=', 'payee')
                ->count(),
            'revenu_total'               => DB::table('factures')->sum('montant_net'),
        ];
    }

        private function medecinDashboard(User $user): array
    {
        if (! $user->id_medecin) {
            return ['message' => 'Aucun profil médecin associé à cet utilisateur'];
        }

        $today = now()->toDateString();

        $rendezVousAujourdhui = DB::table('rendez_vous')
            ->join('patients', 'rendez_vous.id_patient', '=', 'patients.id_patient')
            ->where('rendez_vous.id_medecin', $user->id_medecin)
            ->whereDate('rendez_vous.date_rendez_vous', $today)
            ->orderBy('rendez_vous.heure_debut')
            ->select(
                'rendez_vous.heure_debut',
                'rendez_vous.motif',
                'rendez_vous.statut',
                DB::raw("CONCAT(patients.prenom, ' ', patients.nom) as patient")
            )
            ->get();

        $consultationsAujourdhui = DB::table('consultations')
            ->join('rendez_vous', 'consultations.id_rendez_vous', '=', 'rendez_vous.id_rendez_vous')
            ->where('rendez_vous.id_medecin', $user->id_medecin)
            ->whereDate('rendez_vous.date_rendez_vous', $today)
            ->count();

        return [
            'stats' => [
                'patients' => $rendezVousAujourdhui->count(),
                'rendezVous' => $rendezVousAujourdhui->count(),
                'consultations' => $consultationsAujourdhui,
            ],
            'rendezVous' => $rendezVousAujourdhui->map(fn ($rdv) => [
                'heure' => substr($rdv->heure_debut, 0, 5),
                'patient' => $rdv->patient,
                'motif' => $rdv->motif,
                'statut' => $rdv->statut,
            ]),
        ];
    }

    private function secretaireDashboard(): array
    {
        return [
            'rendez_vous_aujourdhui' => DB::table('rendez_vous')
                ->whereDate('date_rendez_vous', now()->toDateString())
                ->count(),
            'rendez_vous_en_attente' => DB::table('rendez_vous')
                ->where('statut', 'en_attente')
                ->count(),
            'total_patients' => DB::table('patients')->count(),
        ];
    }

    private function infirmierDashboard(): array
    {
        return [
            'hospitalisations_en_cours' => DB::table('hospitalisations')
                ->whereNull('date_sortie')
                ->count(),
            'entrees_aujourdhui' => DB::table('hospitalisations')
                ->whereDate('date_entree', now()->toDateString())
                ->count(),
        ];
    }

    private function patientDashboard(User $user): array
    {
        if (! $user->id_patient) {
            return ['message' => 'Aucun profil patient associé à cet utilisateur'];
        }

        return [
            'prochains_rendez_vous' => DB::table('rendez_vous')
                ->where('id_patient', $user->id_patient)
                ->where('date_rendez_vous', '>=', now()->toDateString())
                ->orderBy('date_rendez_vous')
                ->get(),
            'hospitalisation_en_cours' => DB::table('hospitalisations')
                ->where('id_patient', $user->id_patient)
                ->whereNull('date_sortie')
                ->exists(),
            'factures_impayees' => DB::table('factures')
                ->join('consultations', 'factures.id_consultation', '=', 'consultations.id_consultation')
                ->join('rendez_vous', 'consultations.id_rendez_vous', '=', 'rendez_vous.id_rendez_vous')
                ->where('rendez_vous.id_patient', $user->id_patient)
                ->where('factures.statut_paiement', '!=', 'payee')
                ->count(),
        ];
    }

    public function nombrePatients(Request $request): JsonResponse
{
    return response()->json([
        'total_patients' => DB::table('patients')->count(),
    ]);
}
public function nombreRendezVous(Request $request): JsonResponse
{
    return response()->json([
        'total_rendez_vous' => DB::table('rendez_vous')->count(),
    ]);
}
public function nombreConsultations(Request $request): JsonResponse
{
    return response()->json([
        'total_consultations' => DB::table('consultations')->count(),
    ]);
}
/// chiffre Affaires
public function chiffreAffaires(Request $request): JsonResponse
{
    $total = DB::table('factures')->sum('montant_net');

    return response()->json([
        'chiffre_affaires' => number_format($total, 0, ',', ' ') . ' DH',
    ]);
}

/**
 * Agenda du jour.
 *
 * SCRUM-527 - Cette route renvoyait l'agenda de TOUTE la clinique a qui la
 * demandait. C'est la reponse la plus sensible du tableau de bord : elle
 * associe le nom d'un patient au motif de sa venue, donc a une information
 * de sante.
 *
 * Un medecin n'y voit desormais que ses propres rendez-vous. La regle
 * n'est pas nouvelle : medecinDashboard() l'appliquait deja pour
 * /api/dashboard, mais cette route-ci la contournait. Le personnel
 * d'accueil et de soin (secretaire, infirmier) et l'administrateur gardent
 * l'agenda complet : accueillir les patients et coordonner les soins de la
 * journee suppose de le voir en entier.
 */
public function rendezVousDuJour(Request $request): JsonResponse
{
    $user = $request->user();

    $requete = DB::table('rendez_vous')
        ->join('patients', 'rendez_vous.id_patient', '=', 'patients.id_patient')
        ->whereDate('rendez_vous.date_rendez_vous', now()->toDateString());

    if ($user->role === Role::MEDECIN->value) {
        // Un compte medecin sans profil associe ne se voit attribuer aucun
        // rendez-vous : on renvoie une liste vide plutot que l'agenda
        // complet. Se tromper dans ce sens est sans consequence, l'inverse
        // exposerait les patients des confreres.
        $requete->where('rendez_vous.id_medecin', $user->id_medecin);
    }

    $rendezVous = $requete
        ->orderBy('rendez_vous.heure_debut')
        ->select(
            'rendez_vous.heure_debut',
            'rendez_vous.motif',
            'rendez_vous.statut',
            DB::raw("CONCAT(patients.prenom, ' ', patients.nom) as patient")
        )
        ->get();

    return response()->json(['rendez_vous' => $rendezVous]);
}
////
public function examensEnAttente(Request $request): JsonResponse
{
    $count = DB::table('demandes_examen')
        ->where('statut', 'en_attente')
        ->count();

    return response()->json(['examens_en_attente' => $count]);
}
////
public function statistiquesMensuelles(Request $request): JsonResponse
{
    $mois = collect(range(0, 5))->map(function ($i) {
        $date = now()->subMonths($i);
        return [
            'mois' => $date->translatedFormat('F Y'),
            'annee' => $date->year,
            'numero_mois' => $date->month,
        ];
    })->reverse()->values();

    $stats = $mois->map(function ($m) {
        return [
            'mois' => $m['mois'],
            'patients' => DB::table('patients')
                ->whereYear('created_at', $m['annee'])
                ->whereMonth('created_at', $m['numero_mois'])
                ->count(),
            'consultations' => DB::table('consultations')
                ->whereYear('created_at', $m['annee'])
                ->whereMonth('created_at', $m['numero_mois'])
                ->count(),
            'revenus' => DB::table('factures')
                ->whereYear('date_facture', $m['annee'])
                ->whereMonth('date_facture', $m['numero_mois'])
                ->sum('montant_net'),
        ];
    });

    return response()->json(['statistiques' => $stats]);
}
public function exportStatistiques(Request $request): Response
{
    $mois = collect(range(0, 5))->map(function ($i) {
        $date = now()->subMonths($i);
        return [
            'mois' => $date->translatedFormat('F Y'),
            'annee' => $date->year,
            'numero_mois' => $date->month,
        ];
    })->reverse()->values();

    $stats = $mois->map(function ($m) {
        return [
            'mois' => $m['mois'],
            'patients' => DB::table('patients')
                ->whereYear('created_at', $m['annee'])
                ->whereMonth('created_at', $m['numero_mois'])
                ->count(),
            'consultations' => DB::table('consultations')
                ->whereYear('created_at', $m['annee'])
                ->whereMonth('created_at', $m['numero_mois'])
                ->count(),
            'revenus' => DB::table('factures')
                ->whereYear('date_facture', $m['annee'])
                ->whereMonth('date_facture', $m['numero_mois'])
                ->sum('montant_net'),
        ];
    });

    $csv = "Mois,Patients,Consultations,Revenus\n";
    foreach ($stats as $s) {
        $csv .= "{$s['mois']},{$s['patients']},{$s['consultations']},{$s['revenus']}\n";
    }

    return response($csv, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="statistiques.csv"',
    ]);
}
}