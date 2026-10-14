<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SCRUM-605 / 606 / 607 - Acces aux rendez-vous.
 *
 * Le CRUD ajoute par SCRUM-49 portait bien ses permissions `rendez-vous.*`,
 * mais les CINQ roles detiennent RENDEZ_VOUS_READ : la permission ne
 * distinguait donc personne, et rien d'autre ne protegeait l'agenda.
 * Concretement, avant correction :
 *
 *  - un patient obtenait l'agenda complet de la clinique, avec le motif de
 *    consultation des autres et leur dossier patient entier (CIN, groupe
 *    sanguin, email, telephone) charge par with(['patient','medecin']) ;
 *  - un medecin lisait et MODIFIAIT le rendez-vous d'un confrere, y compris
 *    en se l'attribuant.
 *
 * C'est le seul module dont la permission de lecture est volontairement
 * accordee a tous : son cloisonnement est donc la seule protection, et c'est
 * ce fichier qui en repond (voir PERMISSIONS_CLOISONNEES_PAR_PROPRIETAIRE
 * dans CouvertureEndpointsSecurisesTest).
 */
class RendezVousAccesTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $role, array $attributs = []): User
    {
        return User::factory()->create(['role' => $role] + $attributs);
    }

    private function creerMedecin(string $nom): int
    {
        $idSpecialite = DB::table('specialites')->insertGetId([
            'nom_specialite' => 'Specialite'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_specialite');

        return DB::table('medecins')->insertGetId([
            'matricule' => 'MAT'.uniqid(),
            'nom' => $nom,
            'prenom' => 'Docteur',
            'telephone' => '0600000000',
            'email' => strtolower($nom).uniqid().'@example.com',
            'date_embauche' => '2020-01-01',
            'tarif_consultation' => 300,
            'id_specialite' => $idSpecialite,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_medecin');
    }

    private function creerPatient(string $prenom, ?string $cin = null): Patient
    {
        return Patient::create([
            'nom' => 'Dossier',
            'prenom' => $prenom,
            'date_naissance' => '1990-01-01',
            'sexe' => 'F',
            'cin' => $cin ?? 'CIN'.uniqid(),
            'telephone' => '0611111111',
            'email' => strtolower($prenom).uniqid().'@example.com',
            'groupe_sanguin' => 'AB-',
        ]);
    }

    private function creerRendezVous(int $idPatient, int $idMedecin, string $motif = 'Controle'): RendezVous
    {
        return RendezVous::create([
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'motif' => $motif,
            'statut' => 'confirme',
            'id_patient' => $idPatient,
            'id_medecin' => $idMedecin,
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function routesRendezVous(): array
    {
        return [
            'liste' => ['get', '/api/rendez-vous'],
            'detail' => ['get', '/api/rendez-vous/1'],
            'creation' => ['post', '/api/rendez-vous'],
            'modification' => ['put', '/api/rendez-vous/1'],
            'suppression' => ['delete', '/api/rendez-vous/1'],
        ];
    }

    // -------------------------------------------------- 401 et 403

    #[DataProvider('routesRendezVous')]
    public function test_un_visiteur_non_authentifie_est_refuse(string $methode, string $uri): void
    {
        $this->json(strtoupper($methode), $uri)->assertStatus(401);
    }

    /**
     * L'infirmier lit l'agenda mais ne le modifie pas ; le patient non plus.
     */
    public function test_les_roles_sans_permission_decriture_sont_refuses(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        foreach (['infirmier' => null, 'patient' => $patient->id_patient] as $role => $idPatient) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($this->utilisateur($role, ['id_patient' => $idPatient]));

            $this->putJson("/api/rendez-vous/{$rdv->id_rendez_vous}", [
                'date_rendez_vous' => now()->addDay()->toDateString(),
                'heure_debut' => '10:00',
                'heure_fin' => '10:30',
                'motif' => 'Modifie',
                'statut' => 'confirme',
                'id_patient' => $patient->id_patient,
                'id_medecin' => $idMedecin,
            ])->assertStatus(403);

            $this->deleteJson("/api/rendez-vous/{$rdv->id_rendez_vous}")->assertStatus(403);
        }
    }

    // ----------------------------------------- cloisonnement patient

    /**
     * Le constat le plus visible : la liste renvoyait toute la clinique.
     */
    public function test_un_patient_ne_voit_que_ses_propres_rendez_vous(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre', 'CIN-DE-LAUTRE');

        $this->creerRendezVous($sien->id_patient, $idMedecin, 'Mon controle');
        $this->creerRendezVous($autre->id_patient, $idMedecin, 'MOTIF-CONFIDENTIEL');

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $sien->id_patient]));

        $reponse = $this->getJson('/api/rendez-vous');
        $reponse->assertStatus(200)->assertJsonCount(1, 'rendez_vous.data');

        $corps = $reponse->getContent();
        $this->assertStringContainsString('Mon controle', $corps);
        $this->assertStringNotContainsString('MOTIF-CONFIDENTIEL', $corps);
        $this->assertStringNotContainsString('CIN-DE-LAUTRE', $corps);
    }

    public function test_un_patient_natteint_pas_le_rendez_vous_dun_autre(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');

        $rdvAutre = $this->creerRendezVous($autre->id_patient, $idMedecin, 'MOTIF-CONFIDENTIEL');

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $sien->id_patient]));

        $reponse = $this->getJson("/api/rendez-vous/{$rdvAutre->id_rendez_vous}");

        $reponse->assertStatus(403);
        $this->assertStringNotContainsString('MOTIF-CONFIDENTIEL', $reponse->getContent());
    }

    public function test_un_patient_atteint_son_propre_rendez_vous(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Sien');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $patient->id_patient]));

        $this->getJson("/api/rendez-vous/{$rdv->id_rendez_vous}")->assertStatus(200);
    }

    /**
     * Le patient detient RENDEZ_VOUS_CREATE (prise de rendez-vous en ligne) :
     * il doit pouvoir reserver pour lui, jamais au nom d'un autre.
     */
    public function test_un_patient_ne_prend_rendez_vous_que_pour_lui_meme(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $sien->id_patient]));

        $gabarit = [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '11:00',
            'heure_fin' => '11:30',
            'motif' => 'Consultation',
            'statut' => 'en_attente',
            'id_medecin' => $idMedecin,
        ];

        $this->postJson('/api/rendez-vous', $gabarit + ['id_patient' => $autre->id_patient])
            ->assertStatus(403);

        $this->postJson('/api/rendez-vous', $gabarit + ['id_patient' => $sien->id_patient])
            ->assertStatus(201);

        $this->assertDatabaseMissing('rendez_vous', ['id_patient' => $autre->id_patient]);
    }

    // ----------------------------------------- cloisonnement medecin

    public function test_un_medecin_ne_voit_que_son_propre_agenda(): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');
        $patient = $this->creerPatient('Patient');

        $this->creerRendezVous($patient->id_patient, $idMedecinA, 'Mon rendez-vous');
        $this->creerRendezVous($patient->id_patient, $idMedecinB, 'MOTIF-DU-CONFRERE');

        Sanctum::actingAs($this->utilisateur('medecin', ['id_medecin' => $idMedecinA]));

        $reponse = $this->getJson('/api/rendez-vous');

        $reponse->assertStatus(200)->assertJsonCount(1, 'rendez_vous.data');
        $this->assertStringNotContainsString('MOTIF-DU-CONFRERE', $reponse->getContent());
    }

    public function test_un_medecin_ne_modifie_pas_le_rendez_vous_dun_confrere(): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');
        $patient = $this->creerPatient('Patient');

        $rdvDeB = $this->creerRendezVous($patient->id_patient, $idMedecinB, 'Rendez-vous de B');

        Sanctum::actingAs($this->utilisateur('medecin', ['id_medecin' => $idMedecinA]));

        $this->getJson("/api/rendez-vous/{$rdvDeB->id_rendez_vous}")->assertStatus(403);

        $this->putJson("/api/rendez-vous/{$rdvDeB->id_rendez_vous}", [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '14:30',
            'motif' => 'Detourne',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecinB,
        ])->assertStatus(403);

        $this->assertDatabaseHas('rendez_vous', [
            'id_rendez_vous' => $rdvDeB->id_rendez_vous,
            'motif' => 'Rendez-vous de B',
        ]);
    }

    /**
     * La reattribution : modifier un rendez-vous permet d'en changer le
     * medecin. Sans controle APRES modification, il suffirait de se
     * l'attribuer pour contourner le cloisonnement.
     */
    public function test_un_medecin_ne_sattribue_pas_le_rendez_vous_dun_confrere(): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');
        $patient = $this->creerPatient('Patient');

        $rdvDeB = $this->creerRendezVous($patient->id_patient, $idMedecinB);

        Sanctum::actingAs($this->utilisateur('medecin', ['id_medecin' => $idMedecinA]));

        $this->putJson("/api/rendez-vous/{$rdvDeB->id_rendez_vous}", [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '14:30',
            'motif' => 'Recupere',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecinA,
        ])->assertStatus(403);

        $this->assertDatabaseHas('rendez_vous', [
            'id_rendez_vous' => $rdvDeB->id_rendez_vous,
            'id_medecin' => $idMedecinB,
        ]);
    }

    /**
     * Reciproquement : un medecin ne doit pas pouvoir se debarrasser d'un de
     * ses rendez-vous en le transferant a un confrere.
     */
    public function test_un_medecin_ne_transfere_pas_son_rendez_vous_a_un_confrere(): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');
        $patient = $this->creerPatient('Patient');

        $rdvDeA = $this->creerRendezVous($patient->id_patient, $idMedecinA);

        Sanctum::actingAs($this->utilisateur('medecin', ['id_medecin' => $idMedecinA]));

        $this->putJson("/api/rendez-vous/{$rdvDeA->id_rendez_vous}", [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '14:00',
            'heure_fin' => '14:30',
            'motif' => 'Transfere',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecinB,
        ])->assertStatus(403);

        $this->assertDatabaseHas('rendez_vous', [
            'id_rendez_vous' => $rdvDeA->id_rendez_vous,
            'id_medecin' => $idMedecinA,
        ]);
    }

    public function test_un_medecin_modifie_son_propre_rendez_vous(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('medecin', ['id_medecin' => $idMedecin]));

        $this->putJson("/api/rendez-vous/{$rdv->id_rendez_vous}", [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '15:00',
            'heure_fin' => '15:30',
            'motif' => 'Report',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ])->assertStatus(200);
    }

    // ----------------------------------------- SCRUM-584 : historique

    public function test_un_patient_consulte_son_propre_historique(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');

        $rdvSien = $this->creerRendezVous(
            $sien->id_patient,
            $idMedecin,
            'Mon historique'
        );

        $this->creerRendezVous(
            $autre->id_patient,
            $idMedecin,
            'Historique confidentiel'
        );

        Sanctum::actingAs(
            $this->utilisateur('patient', ['id_patient' => $sien->id_patient])
        );

        $reponse = $this->getJson(
            "/api/patients/{$sien->id_patient}/rendez-vous/historique"
        );

        $reponse
            ->assertStatus(200)
            ->assertJsonCount(1, 'historique')
            ->assertJsonPath(
                'historique.0.id_rendez_vous',
                $rdvSien->id_rendez_vous
            );

        $this->assertStringContainsString(
            'Mon historique',
            $reponse->getContent()
        );

        $this->assertStringNotContainsString(
            'Historique confidentiel',
            $reponse->getContent()
        );
    }

    public function test_un_patient_ne_peut_pas_consulter_lhistorique_dun_autre(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');

        $this->creerRendezVous(
            $autre->id_patient,
            $idMedecin,
            'Historique confidentiel'
        );

        Sanctum::actingAs(
            $this->utilisateur('patient', ['id_patient' => $sien->id_patient])
        );

        $reponse = $this->getJson(
            "/api/patients/{$autre->id_patient}/rendez-vous/historique"
        );

        $reponse
            ->assertStatus(200)
            ->assertJsonCount(0, 'historique');

        $this->assertStringNotContainsString(
            'Historique confidentiel',
            $reponse->getContent()
        );
    }

    public function test_lhistorique_des_rendez_vous_est_trie_du_plus_recent_au_plus_ancien(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');

        $ancien = RendezVous::create([
            'date_rendez_vous' => now()->subDays(3)->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'motif' => 'Ancien rendez-vous',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        $recent = RendezVous::create([
            'date_rendez_vous' => now()->subDay()->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '10:30:00',
            'motif' => 'Rendez-vous recent',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        Sanctum::actingAs(
            $this->utilisateur('patient', ['id_patient' => $patient->id_patient])
        );

        $reponse = $this->getJson(
            "/api/patients/{$patient->id_patient}/rendez-vous/historique"
        );

        $reponse
            ->assertStatus(200)
            ->assertJsonCount(2, 'historique')
            ->assertJsonPath(
                'historique.0.id_rendez_vous',
                $recent->id_rendez_vous
            )
            ->assertJsonPath(
                'historique.1.id_rendez_vous',
                $ancien->id_rendez_vous
            );
    }

    // ------------------------------------------ roles a perimetre global

    public function test_le_personnel_daccueil_voit_tout_lagenda(): void
    {
        $idMedecinA = $this->creerMedecin('Alami');
        $idMedecinB = $this->creerMedecin('Bennani');
        $patient = $this->creerPatient('Patient');

        $this->creerRendezVous($patient->id_patient, $idMedecinA);
        $this->creerRendezVous($patient->id_patient, $idMedecinB);

        foreach (['administrateur', 'secretaire', 'infirmier'] as $role) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($this->utilisateur($role));

            $this->getJson('/api/rendez-vous')
                ->assertStatus(200)
                ->assertJsonCount(2, 'rendez_vous.data');
        }
    }

    // ------------------------------------------------ SCRUM-607 : donnees

    /**
     * with(['patient','medecin']) chargeait les modeles entiers. Meme pour un
     * appelant legitime, l'agenda exposait le CIN et le groupe sanguin du
     * patient, ainsi que le matricule, la date d'embauche et le tarif du
     * medecin - aucun n'est necessaire pour afficher un agenda.
     */
    public function test_lagenda_nexpose_que_lidentite_des_personnes(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient', 'CIN-SENSIBLE');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('secretaire'));

        foreach (['/api/rendez-vous', "/api/rendez-vous/{$rdv->id_rendez_vous}"] as $uri) {
            $corps = $this->getJson($uri)->assertStatus(200)->getContent();

            // L'identite reste disponible : c'est la raison d'etre de l'agenda.
            $this->assertStringContainsString('Dossier', $corps, $uri);

            foreach (['CIN-SENSIBLE', 'groupe_sanguin', 'date_embauche', 'tarif_consultation', 'matricule'] as $champ) {
                $this->assertStringNotContainsString(
                    $champ,
                    $corps,
                    "{$uri} ne doit pas exposer {$champ}"
                );
            }
        }
    }

    // --------------------------------- SCRUM-606 : acces par identifiant

    /**
     * Meme regle que SCRUM-568 : un role au perimetre restreint ne doit pas
     * distinguer "n'existe pas" de "pas le votre", sans quoi il peut
     * enumerer les rendez-vous de la clinique.
     */
    public function test_un_role_restreint_ne_distingue_pas_absent_de_interdit(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');

        $rdvAutre = $this->creerRendezVous($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', ['id_patient' => $sien->id_patient]));

        $surExistant = $this->getJson("/api/rendez-vous/{$rdvAutre->id_rendez_vous}");
        $surInexistant = $this->getJson('/api/rendez-vous/999999');

        $this->assertSame(403, $surExistant->status());
        $this->assertSame(403, $surInexistant->status());
        $this->assertSame($surExistant->json('message'), $surInexistant->json('message'));
    }

    public function test_un_role_global_recoit_404_sur_un_identifiant_inexistant(): void
    {
        Sanctum::actingAs($this->utilisateur('administrateur'));

        $this->getJson('/api/rendez-vous/999999')->assertStatus(404);
    }

    // ------------------------------------------------ integrite metier

    /**
     * SCRUM-607 : une consultation rattachee empeche la suppression en base.
     * Sans controle, la contrainte de cle etrangere remontait en 500.
     */
    public function test_supprimer_un_rendez_vous_consulte_repond_409(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        Consultation::create([
            'motif' => 'Motif',
            'diagnostic' => 'Diagnostic',
            'id_rendez_vous' => $rdv->id_rendez_vous,
        ]);

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $reponse = $this->deleteJson("/api/rendez-vous/{$rdv->id_rendez_vous}");

        $reponse->assertStatus(409);
        $this->assertDatabaseHas('rendez_vous', ['id_rendez_vous' => $rdv->id_rendez_vous]);

        $corps = $reponse->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $corps);
        $this->assertStringNotContainsString('FOREIGN KEY', $corps);
    }

    public function test_un_rendez_vous_sans_consultation_reste_supprimable(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $this->deleteJson("/api/rendez-vous/{$rdv->id_rendez_vous}")->assertStatus(200);
        $this->assertDatabaseMissing('rendez_vous', ['id_rendez_vous' => $rdv->id_rendez_vous]);
    }

    // ----------------------------------------- SCRUM-583 : conflits

    public function test_creation_dun_rendez_vous_en_conflit_est_refusee(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient1 = $this->creerPatient('Patient1');
        $patient2 = $this->creerPatient('Patient2');

        RendezVous::create([
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'motif' => 'Premier rendez-vous',
            'statut' => 'Confirmé',
            'id_patient' => $patient1->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $reponse = $this->postJson('/api/rendez-vous', [
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '10:30',
            'heure_fin' => '11:30',
            'motif' => 'Rendez-vous en conflit',
            'statut' => 'Confirmé',
            'id_patient' => $patient2->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        $reponse
            ->assertStatus(409)
            ->assertJsonPath('message', 'Conflit de rendez-vous : le médecin n’est pas disponible pendant cette période.');

        $this->assertDatabaseCount('rendez_vous', 1);
    }

    public function test_modification_dun_rendez_vous_en_conflit_est_refusee(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient1 = $this->creerPatient('Patient1');
        $patient2 = $this->creerPatient('Patient2');

        $date = now()->addDay()->toDateString();

        $premier = RendezVous::create([
            'date_rendez_vous' => $date,
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'motif' => 'Premier rendez-vous',
            'statut' => 'Confirmé',
            'id_patient' => $patient1->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        $second = RendezVous::create([
            'date_rendez_vous' => $date,
            'heure_debut' => '12:00:00',
            'heure_fin' => '13:00:00',
            'motif' => 'Deuxième rendez-vous',
            'statut' => 'Confirmé',
            'id_patient' => $patient2->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $reponse = $this->putJson("/api/rendez-vous/{$second->id_rendez_vous}", [
            'date_rendez_vous' => $date,
            'heure_debut' => '10:30',
            'heure_fin' => '11:30',
            'motif' => 'Modification en conflit',
            'statut' => 'Confirmé',
            'id_patient' => $patient2->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        $reponse->assertStatus(409);

        $this->assertDatabaseHas('rendez_vous', [
            'id_rendez_vous' => $second->id_rendez_vous,
            'heure_debut' => '12:00:00',
            'heure_fin' => '13:00:00',
        ]);
    }

    public function test_deux_rendez_vous_contigus_ne_sont_pas_en_conflit(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient1 = $this->creerPatient('Patient1');
        $patient2 = $this->creerPatient('Patient2');

        $date = now()->addDay()->toDateString();

        RendezVous::create([
            'date_rendez_vous' => $date,
            'heure_debut' => '10:00:00',
            'heure_fin' => '11:00:00',
            'motif' => 'Premier rendez-vous',
            'statut' => 'Confirmé',
            'id_patient' => $patient1->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $this->postJson('/api/rendez-vous', [
            'date_rendez_vous' => $date,
            'heure_debut' => '11:00',
            'heure_fin' => '12:00',
            'motif' => 'Rendez-vous suivant',
            'statut' => 'Confirmé',
            'id_patient' => $patient2->id_patient,
            'id_medecin' => $idMedecin,
        ])->assertStatus(201);

        $this->assertDatabaseCount('rendez_vous', 2);
    }

    public function test_disponibilite_ne_permet_pas_denumerer_un_rendez_vous_dautrui(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $sien = $this->creerPatient('Sien');
        $autre = $this->creerPatient('Autre');
        $rdvAutre = $this->creerRendezVous($autre->id_patient, $idMedecin);

        Sanctum::actingAs($this->utilisateur('patient', [
            'id_patient' => $sien->id_patient,
        ]));

        $query = http_build_query([
            'id_medecin' => $idMedecin,
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '09:00',
            'heure_fin' => '09:30',
        ]);

        $existantInterdit = $this->getJson(
            '/api/rendez-vous/disponibilite?'.$query.'&id_rendez_vous='.$rdvAutre->id_rendez_vous
        );

        $inexistant = $this->getJson(
            '/api/rendez-vous/disponibilite?'.$query.'&id_rendez_vous=999999999'
        );

        $existantInterdit->assertStatus(403);
        $inexistant->assertStatus(403);
        $this->assertSame($inexistant->json(), $existantInterdit->json());
    }

    public function test_un_rendez_vous_annule_ne_bloque_pas_le_creneau(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');
        $rdv = $this->creerRendezVous($patient->id_patient, $idMedecin);
        $rdv->update(['statut' => 'annulee']);

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $response = $this->getJson('/api/rendez-vous/disponibilite?'.http_build_query([
            'id_medecin' => $idMedecin,
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '09:00',
            'heure_fin' => '09:30',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('disponible', true);
    }

    public function test_les_anciens_statuts_sont_normalises_lors_dune_ecriture_api(): void
    {
        $idMedecin = $this->creerMedecin('Alami');
        $patient = $this->creerPatient('Patient');

        Sanctum::actingAs($this->utilisateur('patient', [
            'id_patient' => $patient->id_patient,
        ]));

        $response = $this->postJson('/api/rendez-vous', [
            'date_rendez_vous' => now()->addDays(2)->toDateString(),
            'heure_debut' => '16:00',
            'heure_fin' => '16:30',
            'motif' => 'Normalisation',
            'statut' => 'en_attente',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ])->assertStatus(201);

        $id = $response->json('rendez_vous.id_rendez_vous');

        $this->assertDatabaseHas('rendez_vous', [
            'id_rendez_vous' => $id,
            'statut' => 'En attente',
        ]);
    }

}