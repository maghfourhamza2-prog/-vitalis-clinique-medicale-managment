<?php

namespace App\Http\Controllers;

use App\Models\Chambre;
use App\Models\Hospitalisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChambreController extends Controller
{
    public function index()
    {
        return response()->json(Chambre::all(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_chambre' => ['required', 'string', 'max:255', 'unique:chambres,numero_chambre'],
            'type_chambre' => ['required', 'string', 'max:255'],
            'etage' => ['required', 'string', 'max:255'],
            'capacite' => ['required', 'integer', 'min:1', 'max:100'],
            'tarif_journalier' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        // Le statut est derive de l'occupation reelle, jamais choisi par le client.
        $validated['statut'] = 'disponible';

        $chambre = Chambre::create($validated);

        return response()->json([
            'message' => 'Chambre ajoutée avec succès',
            'chambre' => $chambre,
        ], 201);
    }

    public function show($id)
    {
        return response()->json(Chambre::findOrFail($id), 200);
    }

    public function update(Request $request, $id)
    {
        $chambre = Chambre::findOrFail($id);

        $validated = $request->validate([
            'numero_chambre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('chambres', 'numero_chambre')
                    ->ignore($chambre->id_chambre, 'id_chambre'),
            ],
            'type_chambre' => ['sometimes', 'required', 'string', 'max:255'],
            'etage' => ['sometimes', 'required', 'string', 'max:255'],
            'capacite' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'tarif_journalier' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
        ]);

        $chambre = DB::transaction(function () use ($id, $validated) {
            $chambre = Chambre::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $occupants = Hospitalisation::where('id_chambre', $chambre->id_chambre)
                ->where('statut', 'en_cours')
                ->count();

            $capacite = (int) ($validated['capacite'] ?? $chambre->capacite);

            if ($capacite < $occupants) {
                throw ValidationException::withMessages([
                    'capacite' => 'La capacité ne peut pas être inférieure au nombre de patients actuellement hospitalisés.',
                ]);
            }

            $chambre->update($validated + [
                'statut' => $occupants >= $capacite ? 'occupee' : 'disponible',
            ]);

            return $chambre->fresh();
        });

        return response()->json([
            'message' => 'Chambre modifiée avec succès',
            'chambre' => $chambre,
        ], 200);
    }

    public function disponibles(Request $request)
    {
        $validated = $request->validate([
            'type_chambre' => ['nullable', 'string', 'max:255'],
            'etage' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Chambre::query()->where('statut', 'disponible');

        if (! empty($validated['type_chambre'])) {
            $query->where('type_chambre', $validated['type_chambre']);
        }

        if (! empty($validated['etage'])) {
            $query->where('etage', $validated['etage']);
        }

        return response()->json($query->get(), 200);
    }

    public function destroy($id)
    {
        $chambre = Chambre::findOrFail($id);

        if ($chambre->hospitalisations()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une chambre liée à un historique d\'hospitalisation.',
            ], 409);
        }

        $chambre->delete();

        return response()->json([
            'message' => 'Chambre supprimée avec succès',
        ], 200);
    }
}
