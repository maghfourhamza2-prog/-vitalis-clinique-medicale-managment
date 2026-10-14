<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use App\Models\Resultat;
use App\Models\RendezVous;
use App\Models\Patient;
use App\Models\Hospitalisation;
use App\Models\DemandeExamen;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function creerDossierClinique(): array
    {
        $patient = Patient::create([
            'nom' => 'Patient',
            'prenom' => 'Confidentiel',
            'date_naissance' => '1990-01-01',
            'sexe' => 'F',
            'cin' => 'SEC'.random_int(100000, 999999),
            'telephone' => '0600000000',
            'email' => 'patient'.random_int(1000, 9999).'@example.com',
            'groupe_sanguin' => 'O+',
        ]);

        $idSpecialite = DB::table('specialites')->insertGetId([
            'nom_specialite' => 'Securite '.random_int(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_specialite');

        $idMedecin = DB::table('medecins')->insertGetId([
            'matricule' => 'SEC'.random_int(100000, 999999),
            'nom' => 'Medecin',
            'prenom' => 'Test',
            'telephone' => '0600000001',
            'email' => 'doctor'.random_int(1000, 9999).'@example.com',
            'date_embauche' => '2020-01-01',
            'tarif_consultation' => 300,
            'id_specialite' => $idSpecialite,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_medecin');

        $rendezVous = RendezVous::create([
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'motif' => 'Motif confidentiel',
            'statut' => 'Confirmé',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        $consultation = Consultation::create([
            'motif' => 'Consultation',
            'diagnostic' => 'Diagnostic confidentiel',
            'id_rendez_vous' => $rendezVous->id_rendez_vous,
        ]);

        $demande = DemandeExamen::create([
            'date_demande' => now()->toDateString(),
            'type_examen' => 'Analyse',
            'niveau_urgence' => 'normal',
            'indications_cliniques' => 'Indications confidentielles',
            'statut' => 'en_attente',
            'date_prevue' => now()->addDay()->toDateString(),
            'observation' => 'Observation confidentielle',
            'id_consultation' => $consultation->id_consultation,
        ]);

        $resultat = Resultat::create([
            'date_resultat' => now()->toDateString(),
            'resultat_detaille' => 'Resultat tres confidentiel',
            'conclusion' => 'Conclusion confidentielle',
            'id_demande_examen' => $demande->id_demande_examen,
        ]);

        $idChambre = DB::table('chambres')->insertGetId([
            'numero_chambre' => 'SEC'.random_int(100, 999),
            'type_chambre' => 'simple',
            'etage' => '1',
            'capacite' => 1,
            'tarif_journalier' => 500,
            'statut' => 'occupee',
            'description' => 'Chambre test',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_chambre');

        $hospitalisation = Hospitalisation::create([
            'date_entree' => now()->toDateString(),
            'heure_entree' => '10:00:00',
            'motif_hospitalisation' => 'Motif hospitalier confidentiel',
            'diagnostic_entree' => 'Diagnostic hospitalier confidentiel',
            'statut' => 'en_cours',
            'observations' => 'Observation hospitaliere confidentielle',
            'id_patient' => $patient->id_patient,
            'id_chambre' => $idChambre,
            'id_medecin' => $idMedecin,
        ]);

        return compact('patient', 'idMedecin', 'rendezVous', 'consultation', 'resultat', 'demande', 'hospitalisation');
    }

    public function test_inactive_account_with_existing_token_is_revoked(): void
    {
        $user = User::factory()->create([
            'statut' => 'Inactif',
        ]);

        $token = $user->createToken('ancien-appareil')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson(['message' => 'Non authentifié']);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_user_can_list_and_revoke_only_own_sessions(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $other = User::factory()->create(['statut' => 'Actif']);

        $current = $user->createToken('courant')->plainTextToken;
        $second = $user->createToken('mobile');
        $otherToken = $other->createToken('autre');

        $headers = ['Authorization' => 'Bearer '.$current];

        $this->getJson('/api/sessions', $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'sessions');

        $this->deleteJson('/api/sessions/'.$second->accessToken->id, [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $second->accessToken->id,
        ]);

        $this->deleteJson('/api/sessions/'.$otherToken->accessToken->id, [], $headers)
            ->assertStatus(404);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }

    public function test_logout_all_revokes_every_token_of_the_user(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $current = $user->createToken('courant')->plainTextToken;
        $user->createToken('mobile');
        $user->createToken('tablette');

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->postJson('/api/logout-all')
            ->assertStatus(200);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_authenticated_api_access_is_audited_without_request_payload(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $token = $user->createToken('audit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(200);

        $audit = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('GET', $audit->method);
        $this->assertSame('/api/me', $audit->path);
        $this->assertSame(200, $audit->status_code);
        $this->assertTrue($audit->integriteValide());

        $this->assertFalse(array_key_exists('request_body', $audit->getAttributes()));
        $this->assertFalse(array_key_exists('response_body', $audit->getAttributes()));
    }

    public function test_only_administrator_can_read_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(200);

        $this->app['auth']->forgetGuards();

        $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_sensitive_role_requires_mfa_when_enforced(): void
    {
        config([
            'security.mfa.enforce_sensitive_roles' => true,
            'security.mfa.roles' => ['administrateur', 'medecin'],
        ]);

        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'admin-mfa@example.com',
            'password' => 'SecurePass123',
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('mfa_required', true)
            ->assertJsonStructure(['challenge_id', 'expires_in']);

        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $admin->fresh()->tokens()->count());
    }

    public function test_valid_mfa_challenge_issues_token_once(): void
    {
        config([
            'app.key' => 'test-security-key',
            'security.mfa.enforce_sensitive_roles' => true,
            'security.mfa.max_attempts' => 5,
            'security.mfa.ttl_seconds' => 300,
        ]);

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $challengeId = (string) Str::uuid();
        $code = '123456';

        Cache::put('mfa:'.$challengeId, [
            'user_id' => $admin->id,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'vitalis-security-test'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], now()->addMinutes(5));

        $this->withHeader('User-Agent', 'vitalis-security-test')
            ->postJson('/api/login/mfa', [
                'challenge_id' => $challengeId,
                'code' => $code,
            ])->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        $this->assertNull(Cache::get('mfa:'.$challengeId));

        $this->withHeader('User-Agent', 'vitalis-security-test')
            ->postJson('/api/login/mfa', [
                'challenge_id' => $challengeId,
                'code' => $code,
            ])->assertStatus(401);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Cache-Control');
    }


    public function test_user_update_cannot_bypass_role_management(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $target = User::factory()->create([
            'name' => 'Cible',
            'email' => 'cible@example.com',
            'role' => 'patient',
            'statut' => 'Actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/'.$target->id, [
                'name' => 'Cible',
                'email' => 'cible@example.com',
                'role' => 'administrateur',
                'statut' => 'Actif',
                'id_medecin' => null,
                'id_patient' => null,
            ])
            ->assertStatus(200);

        $this->assertSame('patient', $target->fresh()->role);
    }

    public function test_role_change_revokes_target_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $target = User::factory()->create([
            'role' => 'patient',
            'statut' => 'Actif',
        ]);

        $target->createToken('mobile');
        $this->assertSame(1, $target->tokens()->count());

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/'.$target->id.'/role', [
                'role' => 'medecin',
            ])
            ->assertStatus(200);

        $this->assertSame('medecin', $target->fresh()->role);
        $this->assertSame(0, $target->fresh()->tokens()->count());
    }

    public function test_secretariat_cannot_read_clinical_results(): void
    {
        $dossier = $this->creerDossierClinique();

        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/resultats/'.$dossier['resultat']->id_resultat)
            ->assertStatus(403);
    }

    public function test_secretariat_receives_minimized_exam_and_hospital_data(): void
    {
        $dossier = $this->creerDossierClinique();

        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $exam = $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/demandes-examen/'.$dossier['demande']->id_demande_examen)
            ->assertStatus(200)
            ->json('demande_examen');

        $this->assertArrayNotHasKey('indications_cliniques', $exam);
        $this->assertArrayNotHasKey('observation', $exam);
        $this->assertArrayNotHasKey('resultat', $exam);

        $hospital = $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/hospitalisations/'.$dossier['hospitalisation']->id_hospitalisation)
            ->assertStatus(200)
            ->json('hospitalisation');

        $this->assertArrayNotHasKey('motif_hospitalisation', $hospital);
        $this->assertArrayNotHasKey('diagnostic_entree', $hospital);
        $this->assertArrayNotHasKey('observations', $hospital);
    }

    public function test_public_registration_can_be_disabled(): void
    {
        config(['security.allow_public_registration' => false]);

        $this->postJson('/api/register', [
            'name' => 'Intrus',
            'email' => 'intrus@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.com']);
    }

    public function test_authorization_failures_are_audited(): void
    {
        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $secretary->id,
            'method' => 'GET',
            'path' => '/api/audit-logs',
            'status_code' => 403,
        ]);
    }


    public function test_audit_rows_are_immutable(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $token = $user->createToken('audit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(200);

        $audit = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertTrue($audit->integriteValide());

        $this->expectException(\LogicException::class);
        $audit->update(['path' => '/api/tampered']);
    }


    public function test_failed_login_does_not_write_raw_email_to_logs(): void
    {
        $email = 'private-patient@example.com';
        User::factory()->create([
            'email' => $email,
            'password' => 'CorrectPassword123',
        ]);

        Log::spy();

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'WrongPassword123',
        ])->assertStatus(401);

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($message, $context = []) =>
                ! str_contains(json_encode([$message, $context]), $email)
                && array_key_exists('email_hash', $context)
        );
    }


    public function test_doctor_patient_register_is_limited_to_linked_patients(): void
    {
        $owned = $this->creerDossierClinique();

        $otherPatient = Patient::create([
            'nom' => 'Autre',
            'prenom' => 'Patient',
            'date_naissance' => '1992-02-02',
            'sexe' => 'M',
            'cin' => 'OTH'.random_int(100000, 999999),
            'telephone' => '0600000099',
            'email' => 'other'.random_int(1000, 9999).'@example.com',
            'groupe_sanguin' => 'A+',
        ]);

        $doctor = User::factory()->create([
            'role' => 'medecin',
            'statut' => 'Actif',
            'id_medecin' => $owned['idMedecin'],
        ]);

        $this->actingAs($doctor, 'sanctum')
            ->getJson('/api/patients/'.$owned['patient']->id_patient)
            ->assertStatus(200);

        $this->actingAs($doctor, 'sanctum')
            ->getJson('/api/patients/'.$otherPatient->id_patient)
            ->assertStatus(403);

        $list = $this->actingAs($doctor, 'sanctum')
            ->getJson('/api/patients')
            ->assertStatus(200)
            ->json('patients.data');

        $ids = collect($list)->pluck('id_patient');
        $this->assertTrue($ids->contains($owned['patient']->id_patient));
        $this->assertFalse($ids->contains($otherPatient->id_patient));
    }

    public function test_doctor_cannot_update_unlinked_patient_record(): void
    {
        $owned = $this->creerDossierClinique();

        $otherPatient = Patient::create([
            'nom' => 'Autre',
            'prenom' => 'Patient',
            'date_naissance' => '1992-02-02',
            'sexe' => 'M',
            'cin' => 'OTH'.random_int(100000, 999999),
            'telephone' => '0600000099',
            'email' => 'other'.random_int(1000, 9999).'@example.com',
            'groupe_sanguin' => 'A+',
        ]);

        $doctor = User::factory()->create([
            'role' => 'medecin',
            'statut' => 'Actif',
            'id_medecin' => $owned['idMedecin'],
        ]);

        $this->actingAs($doctor, 'sanctum')
            ->putJson('/api/patients/'.$otherPatient->id_patient, [
                'nom' => 'Tentative',
                'prenom' => 'Interdite',
                'date_naissance' => '1992-02-02',
                'sexe' => 'M',
                'cin' => $otherPatient->cin,
                'telephone' => '0600000098',
                'email' => $otherPatient->email,
                'groupe_sanguin' => 'A+',
            ])
            ->assertStatus(403);

        $this->assertSame('Autre', $otherPatient->fresh()->nom);
    }


    public function test_invoice_status_cannot_be_spoofed_by_client(): void
    {
        $dossier = $this->creerDossierClinique();

        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $response = $this->actingAs($secretary, 'sanctum')
            ->postJson('/api/factures', [
                'numero_facture' => 'SEC-'.random_int(100000, 999999),
                'date_facture' => now()->toDateString(),
                'remise' => 0,
                'statut_paiement' => 'payee',
                'id_consultation' => $dossier['consultation']->id_consultation,
                'id_hospitalisation' => null,
            ])
            ->assertStatus(201);

        $this->assertSame('impayee', $response->json('facture.statut_paiement'));
    }

    public function test_paid_invoice_cannot_be_cancelled_or_deleted_directly(): void
    {
        $dossier = $this->creerDossierClinique();

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $invoice = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/factures', [
                'numero_facture' => 'SEC-'.random_int(100000, 999999),
                'date_facture' => now()->toDateString(),
                'remise' => 0,
                'id_consultation' => $dossier['consultation']->id_consultation,
                'id_hospitalisation' => null,
            ])
            ->assertStatus(201)
            ->json('facture');

        $amount = (float) $invoice['montant_net'];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/paiements', [
                'date_paiement' => now()->toDateString(),
                'heure_paiement' => '12:00',
                'montant_paye' => $amount,
                'mode_paiement' => 'carte_bancaire',
                'statut' => 'valide',
                'id_facture' => $invoice['id_facture'],
            ])
            ->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/factures/'.$invoice['id_facture'].'/annuler')
            ->assertStatus(409);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/factures/'.$invoice['id_facture'])
            ->assertStatus(409);
    }


    public function test_invoice_cannot_be_reduced_below_already_paid_amount(): void
    {
        $dossier = $this->creerDossierClinique();

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $invoice = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/factures', [
                'numero_facture' => 'SEC-'.random_int(100000, 999999),
                'date_facture' => now()->toDateString(),
                'remise' => 0,
                'id_consultation' => $dossier['consultation']->id_consultation,
                'id_hospitalisation' => null,
            ])
            ->assertStatus(201)
            ->json('facture');

        $paid = max(1, (float) $invoice['montant_net'] / 2);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/paiements', [
                'date_paiement' => now()->toDateString(),
                'heure_paiement' => '12:00',
                'montant_paye' => $paid,
                'mode_paiement' => 'carte_bancaire',
                'statut' => 'valide',
                'id_facture' => $invoice['id_facture'],
            ])
            ->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/factures/'.$invoice['id_facture'], [
                'remise' => (float) $invoice['montant_total'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remise');
    }


    public function test_deleted_user_has_no_remaining_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $target = User::factory()->create([
            'role' => 'patient',
            'statut' => 'Actif',
        ]);

        $target->createToken('mobile');
        $target->createToken('tablet');

        $this->assertSame(2, $target->tokens()->count());

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/users/'.$target->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertSame(
            0,
            \Laravel\Sanctum\PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $target->id)
                ->count()
        );
    }


    public function test_legacy_hospitalisation_create_cannot_bypass_room_capacity(): void
    {
        $dossier = $this->creerDossierClinique();

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/hospitalisations', [
                'date_entree' => now()->toDateString(),
                'heure_entree' => '13:00',
                'motif_hospitalisation' => 'Test capacité',
                'diagnostic_entree' => 'Test',
                'observations' => null,
                'id_patient' => $dossier['patient']->id_patient,
                'id_chambre' => $dossier['hospitalisation']->id_chambre,
                'id_medecin' => $dossier['idMedecin'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_chambre');
    }

    public function test_active_hospitalisation_cannot_move_into_full_room(): void
    {
        $premier = $this->creerDossierClinique();
        $second = $this->creerDossierClinique();

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/hospitalisations/'.$premier['hospitalisation']->id_hospitalisation, [
                'id_chambre' => $second['hospitalisation']->id_chambre,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_chambre');

        $this->assertSame(
            $premier['hospitalisation']->id_chambre,
            $premier['hospitalisation']->fresh()->id_chambre
        );
    }


    public function test_public_health_check_hides_internal_service_names(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status', 'timestamp']);
        $response->assertJsonMissingPath('services');

        $this->assertStringNotContainsString('database', $response->getContent());
        $this->assertStringNotContainsString('redis', $response->getContent());
    }

}
