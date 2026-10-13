<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\DemandeExamen;
use App\Models\Hospitalisation;
use App\Models\Ordonnance;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Resultat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SCRUM-528 - Verification des autorisations au niveau de la requete.
 *
 * Deux sujets, distincts de ceux des tickets precedents :
 *
 * 1. L'acces a un OBJET precis (IDOR / BOLA). Les Gates de SCRUM-518
 *    repondaient "cet utilisateur peut lire une consultation" sans jamais
 *    verifier laquelle. Ces tests figent le cloisonnement "les siennes"
 *    annonce par les enums depuis SCRUM-524.
 *
 * 2. Le volume de donnees qu'une permission autorise a extraire :
 *    /api/patients/search renvoyait tout le fichier patients lorsqu'il etait
 *    appele sans critere.
 *
 * Le cloisonnement est teste au niveau des Gates plutot que par HTTP : aucune
 * route ne consomme encore les permissions sur les donnees medicales, les
 * controllers correspondants n'existant pas. C'est precisement ce qui rendait
 * la faille invisible - elle se serait declaree au premier controller ecrit.
 */
class AutorisationsRequetesTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $role, array $attributs = []): User
    {
        return User::factory()->create(['role' => $role] + $attributs);
    }

    // ------------------------------------------------------- jeu d essai

    private function creerPatient(string $prenom): Patient
    {
        return Patient::create([
            'nom' => 'Dossier',
            'prenom' => $prenom,
            'date_naissance' => '1990-01-01',
            'sexe' => 'F',
            'cin' => 'CIN'.random_int(100000, 999999),
            'telephone' => '0600000000',
            'email' => strtolower($prenom).random_int(1000, 9999).'@example.com',
            'groupe_sanguin' => 'O+',
        ]);
    }

    private function creerMedecin(string $nom): int
    {
        $idSpecialite = DB::table('specialites')->insertGetId([
            'nom_specialite' => 'Specialite '.$nom.random_int(100, 999),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_specialite');

        return DB::table('medecins')->insertGetId([
            'matricule' => 'MAT'.random_int(100000, 999999),
            'nom' => $nom,
            'prenom' => 'Docteur',
            'telephone' => '0600000000',
            'email' => strtolower($nom).random_int(1000, 9999).'@example.com',
            'date_embauche' => '2020-01-01',
            'tarif_consultation' => 300,
            'id_specialite' => $idSpecialite,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_medecin');
    }

    /**
     * Construit la chaine complete du MLD pour un couple patient/medecin :
     * rendez-vous -> consultation -> ordonnance / demande d'examen ->
     * resultat, plus une hospitalisation.
     *
     * @return array<string, object>
     */
    private function creerDossierComplet(int $idPatient, int $idMedecin): array
    {
        $rendezVous = RendezVous::create([
            'date_rendez_vous' => now()->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'motif' => 'Motif',
            'statut' => 'confirme',
            'id_patient' => $idPatient,
            'id_medecin' => $idMedecin,
        ]);

        $consultation = Consultation::create([
            'motif' => 'Motif',
            'diagnostic' => 'Diagnostic confidentiel',
            'id_rendez_vous' => $rendezVous->id_rendez_vous,
        ]);

        $ordonnance = Ordonnance::create([
            'date_ordonnance' => now()->toDateString(),
            'instructions_generales' => 'Instructions',
            'duree_traitement' => '7 jours',
            'type' => 'medicament',
            'id_consultation' => $consultation->id_consultation,
        ]);

        $demande = DemandeExamen::create([
            'date_demande' => now()->toDateString(),
            'niveau_urgence' => 'normal',
            'indications_cliniques' => 'Indications',
            'statut' => 'en_attente',
            'date_prevue' => now()->toDateString(),
            'date_realisation' => now()->toDateString(),
            'observation' => 'Observation',
            'id_consultation' => $consultation->id_consultation,
        ]);

        $resultat = Resultat::create([
            'date_resultat' => now()->toDateString(),
            'resultat_detaille' => 'Resultat',
            'conclusion' => 'Conclusion',
            'valeurs_mesurees' => 'Valeurs',
            'fichier_resultat' => 'fichier.pdf',
            'image_resultat' => 'image.png',
            'observations' => 'Observations',
            'id_demande_examen' => $demande->id_demande_examen,
        ]);

        $idChambre = DB::table('chambres')->insertGetId([
            'numero_chambre' => (string) random_int(100, 999),
            'type_chambre' => 'simple',
            'etage' => '1',
            'capacite' => 1,
            'tarif_journalier' => 500,
            'statut' => 'libre',
            'description' => 'Chambre',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_chambre');

        $hospitalisation = Hospitalisation::create([
            'date_entree' => now()->toDateString(),
            'heure_entree' => '10:00:00',
            'motif_hospitalisation' => 'Motif',
            'diagnostic_entree' => 'Diagnostic',
            'statut' => 'en_cours',
            'id_patient' => $idPatient,
            'id_chambre' => $idChambre,
            'id_medecin' => $idMedecin,
        ]);

        return [
            'rendez_vous' => $rendezVous,
            'consultation' => $consultation,
            'ordonnance' => $ordonnance,
            'demande_examen' => $demande,
            'resultat' => $resultat,
            'hospitalisation' => $hospitalisation,
        ];
    }

    /**
     * Permission de lecture correspondant a chaque type de dossier.
     *
     * @return array<string, array{string, string}>
     */
    public static function dossiersEtPermissions(): array
    {
        return [
            'rendez-vous' => ['rendez_vous', 'consultations.read'],
            'consultation' => ['consultation', 'consultations.read'],
            'ordonnance' => ['ordonnance', 'ordonnances.read'],
            'demande d examen' => ['demande_examen', 'examens.read'],
            'resultat d examen' => ['resultat', 'resultats.read'],
            'hospitalisation' => ['hospitalisation', 'hospitalisations.read'],
        ];
    }

    // ------------------------------------- cloisonnement cote medecin

    /**
     * Le scenario BOLA classique, a la seule difference que l'identifiant
     * n'est pas encore manipulable via une URL : un medecin muni de la bonne
     * permission ne doit pas atteindre le dossier d'un confrere, quel que
     * soit le maillon de la chaine vise.
     */
    #[DataProvider('dossiersEtPermissions')]
    public function test_un_medecin_natteint_pas_le_dossier_dun_confrere(string $cle, string $permission): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');

        $patientDeB = $this->creerPatient('PatientDeB');
        $dossiersDeB = $this->creerDossierComplet($patientDeB->id_patient, $idMedecinB);

        $medecinA = $this->utilisateur('medecin', ['id_medecin' => $idMedecinA]);

        $this->assertTrue(
            Gate::forUser($medecinA)->allows($permission),
            "Le medecin doit conserver la permission {$permission} en general."
        );

        $this->assertFalse(
            Gate::forUser($medecinA)->allows($permission, $dossiersDeB[$cle]),
            "Le medecin A ne doit pas acceder au {$cle} du medecin B."
        );
    }

    #[DataProvider('dossiersEtPermissions')]
    public function test_un_medecin_atteint_ses_propres_dossiers(string $cle, string $permission): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('SonPatient');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $medecin = $this->utilisateur('medecin', ['id_medecin' => $idMedecin]);

        $this->assertTrue(
            Gate::forUser($medecin)->allows($permission, $dossiers[$cle]),
            "Le medecin doit acceder a son propre {$cle}."
        );
    }

    /**
     * Un compte medecin sans profil rattache n'est proprietaire de rien : on
     * refuse plutot que de laisser passer.
     */
    #[DataProvider('dossiersEtPermissions')]
    public function test_un_medecin_sans_profil_natteint_aucun_dossier(string $cle, string $permission): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('UnPatient');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $medecin = $this->utilisateur('medecin', ['id_medecin' => null]);

        $this->assertFalse(
            Gate::forUser($medecin)->allows($permission, $dossiers[$cle]),
            "Un medecin sans profil ne doit atteindre aucun {$cle}."
        );
    }

    // ------------------------------------- cloisonnement cote patient

    /**
     * Le risque le plus direct : le role patient detient CONSULTATIONS_READ,
     * ORDONNANCES_READ, EXAMENS_READ et HOSPITALISATIONS_READ. Sans
     * cloisonnement, ces permissions donnaient acces aux dossiers de tous les
     * patients de la clinique.
     */
    #[DataProvider('dossiersEtPermissions')]
    public function test_un_patient_natteint_pas_le_dossier_dun_autre_patient(string $cle, string $permission): void
    {
        $idMedecin = $this->creerMedecin('Alami');

        $autrePatient = $this->creerPatient('AutrePatient');
        $dossiersDeLAutre = $this->creerDossierComplet($autrePatient->id_patient, $idMedecin);

        $sien = $this->creerPatient('SonDossier');
        $utilisateur = $this->utilisateur('patient', ['id_patient' => $sien->id_patient]);

        $this->assertFalse(
            Gate::forUser($utilisateur)->allows($permission, $dossiersDeLAutre[$cle]),
            "Un patient ne doit pas acceder au {$cle} d'un autre patient."
        );
    }

    #[DataProvider('dossiersEtPermissions')]
    public function test_un_patient_atteint_son_propre_dossier(string $cle, string $permission): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('SonDossier');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $utilisateur = $this->utilisateur('patient', ['id_patient' => $patient->id_patient]);

        $this->assertTrue(
            Gate::forUser($utilisateur)->allows($permission, $dossiers[$cle]),
            "Un patient doit acceder a son propre {$cle}."
        );
    }

    public function test_un_patient_sans_profil_natteint_aucun_dossier(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('UnPatient');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $utilisateur = $this->utilisateur('patient', ['id_patient' => null]);

        $this->assertFalse(
            Gate::forUser($utilisateur)->allows('consultations.read', $dossiers['consultation'])
        );
    }

    // --------------------------------------- roles a perimetre global

    /**
     * Le cloisonnement ne doit pas casser les roles dont le metier couvre
     * toute la clinique : l'accueil et les soins ne suivent pas le decoupage
     * par medecin traitant.
     */
    #[DataProvider('dossiersEtPermissions')]
    public function test_les_roles_a_perimetre_global_ne_sont_pas_cloisonnes(string $cle, string $permission): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('UnPatient');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        foreach (['administrateur', 'secretaire', 'infirmier'] as $role) {
            $utilisateur = $this->utilisateur($role);

            // Seuls les roles qui detiennent deja la permission sont
            // concernes : la secretaire n'a pas acces aux donnees cliniques
            // (SCRUM-524), et ce test ne doit pas le lui accorder.
            if (! Gate::forUser($utilisateur)->allows($permission)) {
                continue;
            }

            $this->assertTrue(
                Gate::forUser($utilisateur)->allows($permission, $dossiers[$cle]),
                "Le role {$role} ne doit pas etre cloisonne sur le {$cle}."
            );
        }
    }

    /**
     * Le cloisonnement s'ajoute au controle de permission, il ne le remplace
     * pas : posseder le dossier ne donne pas une permission qu'on n'a pas.
     */
    public function test_posseder_un_dossier_naccorde_pas_une_permission_absente(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('SonDossier');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $utilisateur = $this->utilisateur('patient', ['id_patient' => $patient->id_patient]);

        // Le patient lit son dossier mais ne le supprime pas, ni ne gere les
        // roles, meme sur ses propres donnees.
        $this->assertTrue(Gate::forUser($utilisateur)->allows('consultations.read', $dossiers['consultation']));
        $this->assertFalse(Gate::forUser($utilisateur)->allows('consultations.delete', $dossiers['consultation']));
        $this->assertFalse(Gate::forUser($utilisateur)->allows('consultations.create', $dossiers['consultation']));
        $this->assertFalse(Gate::forUser($utilisateur)->allows('roles.manage', $dossiers['consultation']));
    }

    /**
     * Un role inconnu en base ne doit rien obtenir, dossier ou pas.
     */
    public function test_un_role_inconnu_natteint_aucun_dossier(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('UnPatient');
        $dossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);

        $utilisateur = $this->utilisateur('role-inconnu');

        $this->assertFalse(
            Gate::forUser($utilisateur)->allows('consultations.read', $dossiers['consultation'])
        );
    }

    /**
     * Les routes actuelles utilisent 'can:permission' sans argument. Le
     * comportement doit rester exactement celui d'avant SCRUM-528, sans quoi
     * le cloisonnement casserait l'API existante.
     */
    public function test_une_verification_sans_dossier_conserve_le_comportement_precedent(): void
    {
        $medecin = $this->utilisateur('medecin');
        $patient = $this->utilisateur('patient');

        $this->assertTrue(Gate::forUser($medecin)->allows('patients.read'));
        $this->assertFalse(Gate::forUser($medecin)->allows('patients.delete'));
        $this->assertFalse(Gate::forUser($patient)->allows('patients.read'));
        $this->assertTrue(Gate::forUser($patient)->allows('consultations.read'));
    }

    // ------------------------------- volume de donnees extractible

    /**
     * Le point le plus concret du ticket : appelee sans aucun critere, la
     * recherche renvoyait Patient::query()->get(), soit tout le fichier
     * patients, sans pagination et avec toutes les donnees personnelles.
     */
    public function test_la_recherche_patient_exige_au_moins_un_critere(): void
    {
        foreach (range(1, 3) as $i) {
            $this->creerPatient('Patient'.$i);
        }

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $reponse = $this->getJson('/api/patients/search');

        $reponse->assertStatus(422);
        $this->assertStringNotContainsString('Patient1', $reponse->getContent());
        $this->assertStringNotContainsString('Patient2', $reponse->getContent());
    }

    public function test_un_critere_vide_ne_permet_pas_de_tout_extraire(): void
    {
        $this->creerPatient('Patient1');

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $this->getJson('/api/patients/search?cin=')->assertStatus(422);
        $this->getJson('/api/patients/search?date_naissance=')->assertStatus(422);
    }

    public function test_la_recherche_reste_utilisable_avec_un_critere(): void
    {
        $cible = $this->creerPatient('Cible');
        $this->creerPatient('Autre');

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $reponse = $this->getJson('/api/patients/search?cin='.$cible->cin);

        $reponse->assertStatus(200)
            ->assertJsonFragment(['cin' => $cible->cin]);

        $this->assertStringNotContainsString('Autre', $reponse->getContent());
    }

    public function test_les_criteres_de_recherche_sont_valides(): void
    {
        Sanctum::actingAs($this->utilisateur('secretaire'));

        $this->getJson('/api/patients/search?date_naissance=pas-une-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_naissance');
    }

    /**
     * La recherche est paginee comme la liste : une permission de lecture ne
     * doit pas se transformer en export du registre.
     */
    public function test_la_recherche_est_paginee(): void
    {
        foreach (range(1, 12) as $i) {
            Patient::create([
                'nom' => 'Commun',
                'prenom' => 'Patient'.$i,
                'date_naissance' => '1985-06-15',
                'sexe' => 'F',
                'cin' => 'CIN'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'telephone' => '0600000000',
                'email' => 'patient'.$i.'@example.com',
                'groupe_sanguin' => 'O+',
            ]);
        }

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $this->getJson('/api/patients/search?date_naissance=1985-06-15')
            ->assertStatus(200)
            ->assertJsonPath('patients.total', 12)
            ->assertJsonCount(10, 'patients.data');
    }

    // ------------------ balayage des routes portant un identifiant

    /**
     * Toutes les routes ou un identifiant est manipulable dans l'URL.
     *
     * @return array<string, array{string, string}>
     */
    public static function routesAvecIdentifiant(): array
    {
        return [
            'fiche patient' => ['get', '/api/patients/1'],
            'modification patient' => ['put', '/api/patients/1'],
            'suppression patient' => ['delete', '/api/patients/1'],
            'fiche medecin' => ['get', '/api/medecins/1'],
            'modification medecin' => ['put', '/api/medecins/1'],
            'suppression medecin' => ['delete', '/api/medecins/1'],
            'fiche specialite' => ['get', '/api/specialites/1'],
            'modification specialite' => ['put', '/api/specialites/1'],
            'suppression specialite' => ['delete', '/api/specialites/1'],
            'modification de role' => ['put', '/api/users/1/role'],
        ];
    }

    /**
     * Le role patient ne doit atteindre aucune route portant un identifiant.
     * Ce balayage sert de garde-fou : si une route future en ouvre une au
     * role patient sans cloisonnement, ce test le signalera.
     */
    #[DataProvider('routesAvecIdentifiant')]
    public function test_le_role_patient_natteint_aucune_route_portant_un_identifiant(string $methode, string $uri): void
    {
        Sanctum::actingAs($this->utilisateur('patient'));

        $this->json(strtoupper($methode), $uri)->assertStatus(403);
    }

    #[DataProvider('routesAvecIdentifiant')]
    public function test_un_visiteur_non_authentifie_natteint_aucune_route_portant_un_identifiant(string $methode, string $uri): void
    {
        $this->json(strtoupper($methode), $uri)->assertStatus(401);
    }

    /**
     * L'autorisation doit passer AVANT la lecture en base : un utilisateur
     * non autorise recoit 403, jamais 404. Sinon la difference entre les deux
     * reponses revelerait quels identifiants existent.
     */
    public function test_le_refus_ne_revele_pas_lexistence_dun_dossier(): void
    {
        $existant = $this->creerPatient('Existant');

        Sanctum::actingAs($this->utilisateur('patient'));

        $surExistant = $this->getJson('/api/patients/'.$existant->id_patient);
        $surInexistant = $this->getJson('/api/patients/999999');

        $this->assertSame(403, $surExistant->status());
        $this->assertSame(403, $surInexistant->status());
        $this->assertSame($surExistant->json('message'), $surInexistant->json('message'));
    }
    // ---------------- HTTP : controllers des dossiers medicaux sensibles

    public function test_un_patient_ne_peut_pas_lire_lhospitalisation_dun_autre_par_url(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('PatientAutorise');
        $autre = $this->creerPatient('PatientInterdit');

        $sesDossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);
        $autresDossiers = $this->creerDossierComplet($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $patient->id_patient]));

        $this->getJson('/api/hospitalisations/'.$sesDossiers['hospitalisation']->id_hospitalisation)
            ->assertStatus(200);

        $this->getJson('/api/hospitalisations/'.$autresDossiers['hospitalisation']->id_hospitalisation)
            ->assertStatus(403);
    }

    public function test_un_patient_ne_peut_pas_lire_la_demande_examen_dun_autre_par_url(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('PatientAutorise');
        $autre = $this->creerPatient('PatientInterdit');

        $sesDossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);
        $autresDossiers = $this->creerDossierComplet($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $patient->id_patient]));

        $this->getJson('/api/demandes-examen/'.$sesDossiers['demande_examen']->id_demande_examen)
            ->assertStatus(200);

        $this->getJson('/api/demandes-examen/'.$autresDossiers['demande_examen']->id_demande_examen)
            ->assertStatus(403);
    }

    public function test_un_patient_ne_peut_pas_lire_le_resultat_dun_autre_par_url(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('PatientAutorise');
        $autre = $this->creerPatient('PatientInterdit');

        $sesDossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);
        $autresDossiers = $this->creerDossierComplet($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $patient->id_patient]));

        $this->getJson('/api/resultats/'.$sesDossiers['resultat']->id_resultat)
            ->assertStatus(200);

        $this->getJson('/api/resultats/'.$autresDossiers['resultat']->id_resultat)
            ->assertStatus(403);
    }

    public function test_les_listes_medicales_du_patient_ne_contiennent_que_son_dossier(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('PatientAutorise');
        $autre = $this->creerPatient('PatientInterdit');

        $sesDossiers = $this->creerDossierComplet($patient->id_patient, $idMedecin);
        $autresDossiers = $this->creerDossierComplet($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $patient->id_patient]));

        $hospitalisations = collect($this->getJson('/api/hospitalisations')->assertStatus(200)->json('hospitalisations'));
        $this->assertTrue($hospitalisations->contains('id_hospitalisation', $sesDossiers['hospitalisation']->id_hospitalisation));
        $this->assertFalse($hospitalisations->contains('id_hospitalisation', $autresDossiers['hospitalisation']->id_hospitalisation));

        $demandes = collect($this->getJson('/api/demandes-examen')->assertStatus(200)->json('demandes_examens'));
        $this->assertTrue($demandes->contains('id_demande_examen', $sesDossiers['demande_examen']->id_demande_examen));
        $this->assertFalse($demandes->contains('id_demande_examen', $autresDossiers['demande_examen']->id_demande_examen));

        $resultats = collect($this->getJson('/api/resultats')->assertStatus(200)->json('resultats'));
        $this->assertTrue($resultats->contains('id_resultat', $sesDossiers['resultat']->id_resultat));
        $this->assertFalse($resultats->contains('id_resultat', $autresDossiers['resultat']->id_resultat));
    }

}
