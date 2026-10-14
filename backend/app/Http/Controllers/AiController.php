<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class AiController extends Controller
{
    /**
     * Envoie les symptomes au service IA FastAPI,
     * puis recommande une specialite et les medecins correspondants.
     */
    public function predict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symptoms' => ['required', 'array', 'min:1', 'max:30'],
            'symptoms.*' => ['required', 'string', 'max:100'],
        ]);

        $aiServiceUrl = rtrim(
            (string) config('services.vitalis_ai.url', 'http://ai:8000'),
            '/'
        );

        try {
            $response = Http::timeout(10)
                ->post($aiServiceUrl . '/predict', [
                    'symptoms' => $validated['symptoms'],
                ]);

            if ($response->status() === 400) {
                // Ne pas transformer Laravel en proxy d'erreurs internes du
                // service ML : le client recoit un message stable et borne.
                return response()->json([
                    'message' => 'Les symptômes fournis ne peuvent pas être analysés.',
                ], 422);
            }

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'AI service returned an error.',
                ], 502);
            }

            $prediction = $response->json();

            $disease = $prediction['disease'] ?? null;
            $confidence = $prediction['confidence'] ?? null;

            if (! is_string($disease) || ! is_numeric($confidence)) {
                return response()->json([
                    'message' => 'AI service returned an invalid response.',
                ], 502);
            }

            $confidence = max(0.0, min(1.0, (float) $confidence));
            $specialityName = $this->getRecommendedSpeciality($disease);

            $speciality = DB::table('specialites')
                ->where('nom_specialite', $specialityName)
                ->first();

            $doctors = collect();

            if ($speciality) {
                $doctors = DB::table('medecins')
                    ->where('id_specialite', $speciality->id_specialite)
                    ->select(
                        'id_medecin',
                        'nom',
                        'prenom'
                    )
                    ->get();
            }

            return response()->json([
                'disease' => $disease,
                'confidence' => $confidence,
                'recommendation' => [
                    'speciality' => $specialityName,
                    'doctors' => $doctors,
                ],
            ]);

        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'AI service is currently unavailable.',
            ], 503);
        }
    }
    /**
 * AI SMART - Assistant conversationnel pour les patients.
 */
public function chat(Request $request): JsonResponse
{
    $validated = $request->validate([
        'message' => ['required', 'string', 'max:1000'],
    ]);

    $apiKey = config('services.gemini.key');

    if (!$apiKey) {
        return response()->json([
            'message' => 'AI SMART is not configured.',
        ], 503);
    }

    try {
        $messageMinimise = $this->minimiserMessageExterne($validated['message']);

        $prompt = <<<PROMPT
Tu es AI SMART, l'assistant virtuel de la clinique Vitalis.

Ton rôle :
- répondre simplement et poliment aux questions des patients ;
- donner uniquement des informations générales sur la santé ;
- aider à comprendre les fonctionnalités de Vitalis ;
- répondre en français ou en arabe selon la langue du patient ;
- ne jamais présenter une réponse comme un diagnostic médical certain ;
- ne pas prescrire de médicaments ;
- conseiller de consulter un professionnel de santé lorsque nécessaire ;
- en cas de symptômes potentiellement urgents, recommander de contacter
  rapidement les services médicaux appropriés.

Question du patient (les identifiants directs ont été masqués avant l'envoi) :
{$messageMinimise}
PROMPT;
    $response = Http::timeout(20)
    ->withHeaders([
        'x-goog-api-key' => $apiKey,
    ])
    ->post(
        'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode((string) config('services.gemini.model', 'gemini-3.6-flash')).':generateContent',
        [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {
            return response()->json([
                'message' => 'AI SMART service returned an error.',
            ], 502);
        }

        $parts = data_get(
    $response->json(),
    'candidates.0.content.parts',
    []
);

$reply = collect($parts)
    ->pluck('text')
    ->filter()
    ->implode('');

        if (!$reply) {
            return response()->json([
                'message' => 'AI SMART could not generate a response.',
            ], 502);
        }

        return response()->json([
            'reply' => $reply,
        ]);

    } catch (ConnectionException $e) {
        return response()->json([
            'message' => 'AI SMART is currently unavailable.',
        ], 503);
    }
}

    /**
     * Retire les identifiants directs les plus courants avant tout envoi a un
     * fournisseur IA externe. Le texte medical utile est conserve, mais les
     * emails, numeros de telephone et identifiants de type CIN sont masques.
     */
    private function minimiserMessageExterne(string $message): string
    {
        $message = preg_replace(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/iu',
            '[EMAIL_MASQUE]',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<!\\d)(?:\\+?212|0)[5-7]\\d(?:[ .-]?\\d{2}){4}(?!\\d)/u',
            '[TELEPHONE_MASQUE]',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<![A-Z0-9])[A-Z]{1,2}\\d{5,8}(?![A-Z0-9])/iu',
            '[IDENTIFIANT_MASQUE]',
            $message
        ) ?? $message;

        return trim($message);
    }

    /**
     * Associe la prediction du modele a une specialite
     * disponible dans Vitalis.
     */
    private function getRecommendedSpeciality(?string $disease): string
    {
        return match ($disease) {
            'Migraine' => 'Neurologie',

            'Influenza',
            'Common_Cold',
            'Asthma',
            'Allergy' => 'Médecine générale',

            default => 'Médecine générale',
        };
    }
}