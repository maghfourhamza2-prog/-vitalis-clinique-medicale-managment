<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Facture;
use App\Models\Hospitalisation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Enums\StatutFacture;
use Barryvdh\DomPDF\Facade\Pdf;

class FactureController extends Controller
{
    // Lister toutes les factures
    public function index()
    {
        $factures = Facture::all();

        return response()->json([
            'factures' => $factures
        ], 200);
    }

    // Afficher une facture
    public function show($id)
    {
        $facture = Facture::findOrFail($id);

        return response()->json([
            'facture' => $facture
        ], 200);
    }

    // Ajouter une facture
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_facture' => [
                'required',
                'string',
                'max:255',
                'unique:factures,numero_facture'
            ],

            'date_facture' => [
                'required',
                'date'
            ],

            'remise' => [
                'required',
                'numeric',
                'min:0'
            ],

            'observations' => [
                'nullable',
                'string'
            ],

            'id_consultation' => [
                'nullable',
                'integer',
                'exists:consultations,id_consultation'
            ],

            'id_hospitalisation' => [
                'nullable',
                'integer',
                'exists:hospitalisations,id_hospitalisation'
            ],
        ]);

        $montantTotal = $this->calculerMontantTotal(
            $validated['id_consultation'] ?? null,
            $validated['id_hospitalisation'] ?? null,
            $validated['date_facture']
        );

        $remise = (float) $validated['remise'];

        if ($remise > $montantTotal) {
            throw ValidationException::withMessages([
                'remise' => 'La remise ne peut pas dépasser le montant total.'
            ]);
        }

        $validated['montant_total'] = $montantTotal;
        $validated['montant_net'] = $montantTotal - $remise;
        $validated['statut_paiement'] = StatutFacture::IMPAYEE->value;

        $facture = Facture::create($validated);

        return response()->json([
            'message' => 'Facture ajoutée avec succès',
            'facture' => $facture
        ], 201);
    }

    // Modifier une facture
    public function update(Request $request, $id)
    {
        $facture = Facture::findOrFail($id);

        $validated = $request->validate([
            'numero_facture' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('factures', 'numero_facture')
                    ->ignore($facture->id_facture, 'id_facture')
            ],

            'date_facture' => [
                'sometimes',
                'required',
                'date'
            ],

            'remise' => [
                'sometimes',
                'required',
                'numeric',
                'min:0'
            ],

            'observations' => [
                'sometimes',
                'nullable',
                'string'
            ],

            'id_consultation' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:consultations,id_consultation'
            ],

            'id_hospitalisation' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:hospitalisations,id_hospitalisation'
            ],
        ]);

        $idConsultation = array_key_exists('id_consultation', $validated)
            ? $validated['id_consultation']
            : $facture->id_consultation;

        $idHospitalisation = array_key_exists('id_hospitalisation', $validated)
            ? $validated['id_hospitalisation']
            : $facture->id_hospitalisation;

        $dateFacture = $validated['date_facture']
            ?? $facture->date_facture;

        $remise = array_key_exists('remise', $validated)
            ? (float) $validated['remise']
            : (float) $facture->remise;

        $montantTotal = $this->calculerMontantTotal(
            $idConsultation,
            $idHospitalisation,
            $dateFacture
        );

        if ($remise > $montantTotal) {
            throw ValidationException::withMessages([
                'remise' => 'La remise ne peut pas dépasser le montant total.'
            ]);
        }

        $validated['montant_total'] = $montantTotal;
        $validated['montant_net'] = $montantTotal - $remise;

        $facture->update($validated);
        $this->synchroniserStatutPaiement($facture);

        return response()->json([
            'message' => 'Facture modifiée avec succès',
            'facture' => $facture
        ], 200);
    }

    // Calcul automatique du montant total
    private function calculerMontantTotal(
        $idConsultation,
        $idHospitalisation,
        $dateFacture
    ): float {
        if (!$idConsultation && !$idHospitalisation) {
            throw ValidationException::withMessages([
                'facture' => 'Une consultation ou une hospitalisation est obligatoire.'
            ]);
        }

        $montantTotal = 0;

        // Cas consultation
        if ($idConsultation) {
            $consultation = Consultation::findOrFail($idConsultation);

            $medecin = $consultation->medecin();

            if (!$medecin) {
                throw ValidationException::withMessages([
                    'id_consultation' => 'Aucun médecin associé à cette consultation.'
                ]);
            }

            $montantTotal += (float) $medecin->tarif_consultation;
        }

        // Cas hospitalisation
        if ($idHospitalisation) {
            $hospitalisation = Hospitalisation::with('chambre')
                ->findOrFail($idHospitalisation);

            if (!$hospitalisation->chambre) {
                throw ValidationException::withMessages([
                    'id_hospitalisation' => 'Aucune chambre associée à cette hospitalisation.'
                ]);
            }

            $dateEntree = Carbon::parse($hospitalisation->date_entree);

            $dateFin = $hospitalisation->date_sortie
                ? Carbon::parse($hospitalisation->date_sortie)
                : Carbon::parse($dateFacture);

            if ($dateFin->lt($dateEntree)) {
                throw ValidationException::withMessages([
                    'date_facture' => 'La date de fin ne peut pas être antérieure à la date d’entrée.'
                ]);
            }

            $nombreJours = max(
                1,
                $dateEntree->diffInDays($dateFin)
            );

            $montantTotal +=
                $nombreJours *
                (float) $hospitalisation->chambre->tarif_journalier;
        }

        return round($montantTotal, 2);
    }

    // Annuler une facture
    public function annuler($id)
    {
    $facture = Facture::findOrFail($id);

    // Vérifier si la facture est déjà annulée
    if ($facture->statut_paiement === StatutFacture::ANNULEE->value) {
        return response()->json([
            'message' => 'Cette facture est déjà annulée.'
        ], 409);
    }

    if ($facture->paiements()->where('statut', 'valide')->exists()) {
        return response()->json([
            'message' => 'Impossible d\'annuler une facture avec des paiements validés. Annulez ou remboursez d\'abord les paiements.',
        ], 409);
    }

    $facture->update([
        'statut_paiement' => StatutFacture::ANNULEE->value
    ]);

    return response()->json([
        'message' => 'Facture annulée avec succès',
        'facture' => $facture
    ], 200);
    }

    // Afficher l'historique de facturation d'un patient
    public function historiquePatient($idPatient)
    {
        $factures = Facture::where(function ($query) use ($idPatient) {

        // Factures liées aux consultations du patient
        $query->whereHas(
            'consultation.rendezVous',
            fn ($q) => $q->where('id_patient', $idPatient)
        )

        // Ou factures liées aux hospitalisations du patient
        ->orWhereHas(
            'hospitalisation',
            fn ($q) => $q->where('id_patient', $idPatient)
        );
    })
    ->orderBy('date_facture', 'desc')
    ->get();

    return response()->json([
        'historique' => $factures
    ], 200);
    }

    // Afficher une facture pour impression
    public function imprimer($id)
    {
        $facture = Facture::with([
        'consultation.rendezVous.patient',
        'consultation.rendezVous.medecin',
        'hospitalisation.patient',
        'hospitalisation.medecin',
        'hospitalisation.chambre',
        'paiements',
        ])->findOrFail($id);

        $patient = $facture->consultation?->rendezVous?->patient
        ?? $facture->hospitalisation?->patient;

        $medecin = $facture->consultation?->rendezVous?->medecin
        ?? $facture->hospitalisation?->medecin;

          return response()->json([
        'facture' => $facture,
        'patient' => $patient,
        'medecin' => $medecin,
    ], 200);
    }

    // Télécharger une facture au format PDF
    public function telecharger($id)
    { 
    $facture = Facture::with([
        'consultation.rendezVous.patient',
        'consultation.rendezVous.medecin',
        'hospitalisation.patient',
        'hospitalisation.medecin',
        'hospitalisation.chambre',
        'paiements',
        ])->findOrFail($id);

    // Déterminer le patient concerné par la facture
         $patient = $facture->consultation?->rendezVous?->patient
        ?? $facture->hospitalisation?->patient;

    // Déterminer le médecin concerné
         $medecin = $facture->consultation?->rendezVous?->medecin
        ?? $facture->hospitalisation?->medecin;

        $pdf = Pdf::loadView('factures.pdf', [
        'facture' => $facture,
        'patient' => $patient,
        'medecin' => $medecin,
    ]);

    return $pdf->download(
        'facture-' . $facture->numero_facture . '.pdf'
    );
    }

    private function synchroniserStatutPaiement(Facture $facture): void
    {
        if ($facture->statut_paiement === StatutFacture::ANNULEE->value) {
            return;
        }

        $totalPaye = (float) $facture->paiements()
            ->where('statut', 'valide')
            ->sum('montant_paye');

        $montantNet = (float) $facture->montant_net;

        $statut = match (true) {
            $totalPaye <= 0 => StatutFacture::IMPAYEE->value,
            $totalPaye < $montantNet => StatutFacture::PARTIELLE->value,
            default => StatutFacture::PAYEE->value,
        };

        $facture->update(['statut_paiement' => $statut]);
    }

    // Supprimer une facture
    public function destroy($id)
    {
        $facture = Facture::findOrFail($id);

        if ($facture->paiements()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une facture qui contient des paiements.',
            ], 409);
        }

        $facture->delete();

        return response()->json([
            'message' => 'Facture supprimée avec succès'
        ], 200);
    }
}