<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Medecin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedecinController extends Controller
{
    public function index(Request $request)
    {
        $medecins = Medecin::all();

        $this->minimiserAnnuaire($medecins, $request);

        return response()->json([
            'medecins' => $medecins,
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $medecin = Medecin::findOrFail($id);

        $this->minimiserAnnuaire(collect([$medecin]), $request);

        return response()->json([
            'medecin' => $medecin,
        ], 200);
    }

    private function minimiserAnnuaire($medecins, Request $request): void
    {
        if (Role::tryFrom((string) $request->user()->role) === Role::ADMINISTRATEUR) {
            return;
        }

        foreach ($medecins as $medecin) {
            $medecin->makeHidden([
                'matricule',
                'date_embauche',
                'tarif_consultation',
            ]);
        }
    }

    public function store(Request $request)
    {
    // SCRUM-567 : bornes alignees sur le schema (VARCHAR(255),
    // DECIMAL(10,2)). Sans elles, une valeur surdimensionnee passait la
    // validation et n'echouait qu'en base, en 500.
    $validated = $request->validate([
        'matricule' => 'required|string|max:255|unique:medecins,matricule',
        'nom' => 'required|string|max:255',
        'prenom' => 'required|string|max:255',
        'telephone' => 'required|string|max:20',
        'email' => 'required|email|max:255|unique:medecins,email',
        'date_embauche' => 'required|date',
        'tarif_consultation' => 'required|numeric|min:0|max:99999999.99',
        'id_specialite' => 'required|exists:specialites,id_specialite',
    ]);

    $medecin = Medecin::create($validated);

    return response()->json([
        'message' => 'Médecin ajouté avec succès',
        'medecin' => $medecin
    ], 201);
   }

   public function update(Request $request, $id)
   {
    $medecin = Medecin::findOrFail($id);

    $validated = $request->validate([
        'matricule' => 'required|string|max:255|unique:medecins,matricule,' . $id . ',id_medecin',
        'nom' => 'required|string|max:255',
        'prenom' => 'required|string|max:255',
        'telephone' => 'required|string|max:20',
        'email' => 'required|email|max:255|unique:medecins,email,' . $id . ',id_medecin',
        'date_embauche' => 'required|date',
        'tarif_consultation' => 'required|numeric|min:0|max:99999999.99',
        'id_specialite' => 'required|exists:specialites,id_specialite',
    ]);

    $medecin->update($validated);

    return response()->json([
        'message' => 'Médecin modifié avec succès',
        'medecin' => $medecin
    ], 200);
    }

public function destroy($id)
   {
    $medecin = Medecin::findOrFail($id);

    // SCRUM-563 : un medecin est reference par les rendez-vous, les
    // hospitalisations et eventuellement un compte utilisateur. Sans ce
    // controle, la contrainte de cle etrangere remontait en PDOException,
    // donc en 500 avec la trace SQL quand APP_DEBUG est actif. Meme
    // traitement que PatientController : un conflit metier vaut 409.
    $dependances = [
        'rendez-vous' => DB::table('rendez_vous')->where('id_medecin', $medecin->id_medecin)->exists(),
        'hospitalisations' => DB::table('hospitalisations')->where('id_medecin', $medecin->id_medecin)->exists(),
        'compte utilisateur' => DB::table('users')->where('id_medecin', $medecin->id_medecin)->exists(),
    ];

    $bloquantes = array_keys(array_filter($dependances));

    if ($bloquantes !== []) {
        return response()->json([
            'message' => 'Impossible de supprimer ce medecin car des '
                . implode(', ', $bloquantes) . ' lui sont associes.'
        ], 409);
    }

    $medecin->delete();

    return response()->json([
        'message' => 'Médecin supprimé avec succès'
    ], 200);
   }

}