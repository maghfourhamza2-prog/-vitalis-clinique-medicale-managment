<?php

namespace App\Http\Controllers;

use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    public function index()
    {
        return response()->json([
            'paiements' => Paiement::all(),
        ], 200);
    }

    public function show($id)
    {
        return response()->json([
            'paiement' => Paiement::findOrFail($id),
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->regles(creation: true));

        $paiement = DB::transaction(function () use ($validated) {
            // Le verrou empeche deux caisses de valider simultanement des
            // paiements qui, pris separement, paraissent tous les deux valides
            // mais depasseraient ensemble le solde de la facture.
            $facture = Facture::whereKey($validated['id_facture'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->verifierPaiement(
                $facture,
                (float) $validated['montant_paye'],
                (string) $validated['statut']
            );

            $paiement = Paiement::create($validated);
            $this->synchroniserStatutFacture($facture);

            return $paiement;
        });

        return response()->json([
            'message' => 'Paiement ajouté avec succès',
            'paiement' => $paiement,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate($this->regles(creation: false));

        $paiement = DB::transaction(function () use ($validated, $id) {
            $paiement = Paiement::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $ancienneFactureId = (int) $paiement->id_facture;
            $nouvelleFactureId = (int) ($validated['id_facture'] ?? $ancienneFactureId);

            // Verrouillage dans un ordre stable pour limiter le risque de
            // deadlock quand un paiement change de facture.
            $ids = array_values(array_unique([$ancienneFactureId, $nouvelleFactureId]));
            sort($ids);

            $factures = Facture::whereIn('id_facture', $ids)
                ->orderBy('id_facture')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_facture');

            $facture = $factures->get($nouvelleFactureId);
            $ancienneFacture = $factures->get($ancienneFactureId);

            if ($facture === null || $ancienneFacture === null) {
                abort(404);
            }

            $montantPaye = (float) ($validated['montant_paye'] ?? $paiement->montant_paye);
            $statut = (string) ($validated['statut'] ?? $paiement->statut);

            $this->verifierPaiement(
                $facture,
                $montantPaye,
                $statut,
                (int) $paiement->id_paiement
            );

            $paiement->update($validated);

            $this->synchroniserStatutFacture($facture);

            if ($ancienneFactureId !== $nouvelleFactureId) {
                $this->synchroniserStatutFacture($ancienneFacture);
            }

            return $paiement;
        });

        return response()->json([
            'message' => 'Paiement modifié avec succès',
            'paiement' => $paiement,
        ], 200);
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $paiement = Paiement::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $facture = Facture::whereKey($paiement->id_facture)
                ->lockForUpdate()
                ->firstOrFail();

            $paiement->delete();
            $this->synchroniserStatutFacture($facture);
        });

        return response()->json([
            'message' => 'Paiement supprimé avec succès',
        ], 200);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regles(bool $creation): array
    {
        $requis = $creation ? 'required' : 'sometimes';

        return [
            'date_paiement' => [$requis, 'date'],
            'heure_paiement' => [$requis, 'date_format:H:i'],
            'montant_paye' => [$requis, 'numeric', 'min:0.01'],
            'mode_paiement' => [$requis, Rule::in(['especes', 'carte_bancaire', 'virement'])],
            'statut' => [$requis, Rule::in(['valide', 'annule'])],
            'observations' => ['nullable', 'string', 'max:5000'],
            'id_facture' => [$requis, 'integer', 'exists:factures,id_facture'],
        ];
    }

    private function verifierPaiement(
        Facture $facture,
        float $montantPaye,
        string $statut,
        ?int $paiementExclu = null
    ): void {
        if ($facture->statut_paiement === StatutFacture::ANNULEE->value) {
            throw ValidationException::withMessages([
                'id_facture' => 'Impossible de modifier les paiements d\'une facture annulée.',
            ]);
        }

        $totalDejaPaye = (float) $facture->paiements()
            ->where('statut', 'valide')
            ->when(
                $paiementExclu !== null,
                fn ($query) => $query->where('id_paiement', '!=', $paiementExclu)
            )
            ->sum('montant_paye');

        $resteAPayer = max(0, (float) $facture->montant_net - $totalDejaPaye);

        if ($statut === 'valide' && $montantPaye > $resteAPayer) {
            throw ValidationException::withMessages([
                'montant_paye' => 'Le montant du paiement dépasse le reste à payer.',
            ]);
        }
    }

    private function synchroniserStatutFacture(Facture $facture): void
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
}
