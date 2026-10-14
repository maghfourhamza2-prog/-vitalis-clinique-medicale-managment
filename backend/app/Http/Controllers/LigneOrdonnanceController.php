<?php

namespace App\Http\Controllers;

use App\Models\LigneOrdonnance;
use App\Models\Ordonnance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * SCRUM-564 / SCRUM-566 - Contenu des ordonnances.
 *
 * Une ligne porte le medicament prescrit, sa posologie et sa duree : c'est
 * une donnee de sante au meme titre que l'ordonnance qui la contient, et elle
 * suit exactement le meme regime d'acces.
 *
 * Ces routes n'exigeaient qu'un compte valide et ne validaient aucune entree.
 * N'importe quel compte authentifie pouvait donc lire le traitement d'un
 * patient, y ajouter un medicament ou en retirer un.
 */
class LigneOrdonnanceController extends Controller
{
    /**
     * Ordonnance visee, apres verification que l'appelant peut y acceder.
     *
     * Le middleware `can:` de la route verifie la permission ; ici on verifie
     * le DOSSIER, via les Gates a ressource de SCRUM-528.
     */
    private function ordonnanceAutorisee(int|string $idOrdonnance, string $permission, Request $request): Ordonnance
    {
        // SCRUM-568 : voir trouverOuRefuser() - un role au perimetre
        // restreint ne doit pas distinguer "n'existe pas" de "pas la votre".
        $ordonnance = $this->trouverOuRefuser(Ordonnance::find($idOrdonnance), $request);

        Gate::authorize($permission, $ordonnance);

        return $ordonnance;
    }

    public function index(Request $request, $idOrdonnance)
    {
        $this->ordonnanceAutorisee($idOrdonnance, 'ordonnances.read', $request);

        $lignes = LigneOrdonnance::where('id_ordonnance', $idOrdonnance)->get();

        return response()->json([
            'lignes' => $lignes,
        ], 200);
    }

    public function store(Request $request, $idOrdonnance)
    {
        $ordonnance = $this->ordonnanceAutorisee(
            $idOrdonnance,
            'ordonnances.update',
            $request
        );

        $valide = $request->validate([
            'dosologie' => ['required', 'string', 'max:255'],
            'frequence' => ['required', 'string', 'max:255'],
            'duree' => ['required', 'string', 'max:255'],
            'quantite' => ['required', 'integer', 'min:1'],
            'instructions' => ['required', 'string', 'max:2000'],
            'id_medicament' => ['required', 'integer', 'exists:medicaments,id_medicament'],
        ]);

        $ligne = LigneOrdonnance::create(
            $valide + ['id_ordonnance' => $ordonnance->id_ordonnance]
        );

        return response()->json([
            'message' => 'Médicament ajouté à l\'ordonnance avec succès',
            'ligne' => $ligne,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $ligne = LigneOrdonnance::findOrFail($id);
        $request->validate([
          'dosologie' => 'sometimes|required|string|max:255',
          'frequence' => 'sometimes|required|string|max:255',
          'duree' => 'sometimes|required|string|max:255',
          'quantite' => 'sometimes|required|integer|min:1',
          'instructions' => 'sometimes|required|string',
          'id_medicament' => 'sometimes|required|integer|exists:medicaments,id_medicament',
        ]);

        // La ligne est rattachee a son ordonnance par PerimetreDossier.
        Gate::authorize('ordonnances.update', $ligne);

        $valide = $request->validate([
            'dosologie' => ['sometimes', 'string', 'max:255'],
            'frequence' => ['sometimes', 'string', 'max:255'],
            'duree' => ['sometimes', 'string', 'max:255'],
            'quantite' => ['sometimes', 'integer', 'min:1'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'id_medicament' => ['sometimes', 'integer', 'exists:medicaments,id_medicament'],
        ]);

        $ligne->update($valide);

        return response()->json([
            'message' => 'Contenu de l\'ordonnance modifié avec succès',
            'ligne' => $ligne,
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $ligne = $this->trouverOuRefuser(LigneOrdonnance::find($id), $request);

        Gate::authorize('ordonnances.update', $ligne);

        $ligne->delete();

        return response()->json([
            'message' => 'Ligne supprimée de l\'ordonnance avec succès',
        ], 200);
    }
}
