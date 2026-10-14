<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SCRUM-702 - Tests End-to-End du module IA.
 *
 * Verifie l'integration entre l'API Laravel, le service IA
 * et le systeme de recommandation de specialites et medecins.
 */
class AiPredictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_prediction_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/predict', [
            'symptoms' => ['headache', 'nausea', 'dizziness'],
        ]);

        $response->assertStatus(401);
    }

    public function test_symptoms_are_required(): void
    {
        $user = User::factory()->create([
            'role' => 'medecin',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/predict', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symptoms']);
    }

    public function test_prediction_returns_disease_confidence_and_recommendation(): void
    {
        $user = User::factory()->create([
            'role' => 'medecin',
        ]);

        DB::table('specialites')->insert([
            'id_specialite' => 1,
            'nom_specialite' => 'Neurologie',
            'description' => 'Diagnostic et traitement neurologique.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('medecins')->insert([
    'id_medecin' => 1,
    'matricule' => 'MED-TEST-001',
    'nom' => 'Idrissi',
    'prenom' => 'Sara',
    'telephone' => '0600000000',
    'email' => 'sara.idrissi@test.ma',
    'date_embauche' => '2025-01-01',
    'tarif_consultation' => 300,
    'id_specialite' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

        Http::fake([
            '*/predict' => Http::response([
                'disease' => 'Migraine',
                'confidence' => 99.95,
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/predict', [
                'symptoms' => [
                    'headache',
                    'nausea',
                    'dizziness',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('disease', 'Migraine')
            ->assertJsonPath('confidence', 99.95)
            ->assertJsonPath(
                'recommendation.speciality',
                'Neurologie'
            )
            ->assertJsonPath(
                'recommendation.doctors.0.nom',
                'Idrissi'
            )
            ->assertJsonPath(
                'recommendation.doctors.0.prenom',
                'Sara'
            );
    }

    public function test_ai_validation_error_is_not_proxied_to_the_client(): void
    {
        $user = User::factory()->create([
            'role' => 'medecin',
        ]);

        Http::fake([
            '*/predict' => Http::response([
                'detail' => [
                    'message' => 'Internal model validation detail.',
                    'unknown_symptoms' => ['invalid_symptom'],
                ],
            ], 400),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/predict', [
                'symptoms' => ['invalid_symptom'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Les symptômes fournis ne peuvent pas être analysés.'
            );

        $this->assertStringNotContainsString(
            'Internal model validation detail.',
            $response->getContent()
        );
    }

    public function test_ai_service_error_returns_bad_gateway(): void
    {
        $user = User::factory()->create([
            'role' => 'medecin',
        ]);

        Http::fake([
            '*/predict' => Http::response([
                'message' => 'Internal AI error',
            ], 500),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/predict', [
                'symptoms' => ['headache'],
            ]);

        $response->assertStatus(502)
            ->assertJsonPath(
                'message',
                'AI service returned an error.'
            );
    }
    public function test_ai_service_unavailable_returns_503(): void
{
    $user = User::factory()->create([
        'role' => 'medecin',
    ]);

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException(
            'AI service unavailable'
        );
    });

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/ai/predict', [
            'symptoms' => ['headache'],
        ]);

    $response->assertStatus(503)
        ->assertJsonPath(
            'message',
            'AI service is currently unavailable.'
        );
}
}