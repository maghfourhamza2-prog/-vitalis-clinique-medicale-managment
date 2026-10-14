<?php
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MedecinController;
use App\Http\Controllers\SpecialiteController;
use App\Http\Controllers\OrdonnanceController;
use App\Http\Controllers\LigneOrdonnanceController;
use App\Http\Controllers\RendezVousController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HospitalisationController;
use App\Http\Controllers\ChambreController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\DemandeExamenController;
use App\Http\Controllers\ResultatController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// Routes publiques d'authentification
// SCRUM-510 : ces routes sont accessibles sans jeton, elles sont donc limitees
// en debit pour empecher la force brute et les envois en masse. Les limiteurs
// 'login' et 'auth-public' sont definis dans AppServiceProvider.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
Route::post('/login/mfa', [AuthController::class, 'verifyMfa'])
    ->middleware('throttle:auth-public');

Route::middleware('throttle:auth-public')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-reset-request');


// Routes accessibles uniquement aux utilisateurs authentifiés
Route::middleware(['auth:sanctum', 'audit.api', 'account.active'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/sessions', [AuthController::class, 'sessions']);
    Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:audit-logs.read');
    // Assistant médical IA : permission explicite, en plus de l'authentification.
    Route::post('/ai/predict', [AiController::class, 'predict'])
        ->middleware('can:ai.use');
    Route::post('/ai/chat', [AiController::class, 'chat'])
        ->middleware('can:ai.use');
    // Tableau de bord principal.
    //
    // Seule route /dashboard/* sans permission, et c'est volontaire : le
    // controller choisit lui-meme le contenu selon le role de l'appelant, et
    // chaque role n'y recoit que son propre perimetre (le medecin ses
    // rendez-vous, le patient son dossier). Un compte valide suffit donc.
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // SCRUM-527 : les routes ci-dessous renvoient des donnees AGREGEES sur
    // toute la clinique, sans tenir compte du role de l'appelant. Elles
    // n'exigeaient jusqu'ici qu'un compte valide : n'importe quel utilisateur
    // authentifie - y compris un compte cree librement via /api/register, qui
    // recoit le role 'patient' - pouvait lire le chiffre d'affaires de la
    // clinique et la liste nominative des rendez-vous du jour.
    //
    // Indicateurs d'activite : matiere du tableau de bord du personnel.
    Route::middleware('can:indicateurs.read')->group(function () {
        Route::get('/dashboard/patients-count', [DashboardController::class, 'nombrePatients']);
        Route::get('/dashboard/rendez-vous-count', [DashboardController::class, 'nombreRendezVous']);
        Route::get('/dashboard/consultations-count', [DashboardController::class, 'nombreConsultations']);
        Route::get('/dashboard/examens-en-attente', [DashboardController::class, 'examensEnAttente']);
        // Agenda du jour : contient le nom des patients et le MOTIF de leur
        // venue. Le controller restreint en plus le medecin a ses propres
        // rendez-vous.
        Route::get('/dashboard/rendez-vous-du-jour', [DashboardController::class, 'rendezVousDuJour']);
    });


// Gestion des utilisateurs (SCRUM-739)
Route::get('/users', [UserController::class, 'index'])
    ->middleware('can:users.read');
Route::get('/users/{id}', [UserController::class, 'show'])
    ->middleware('can:users.read');
Route::post('/users', [UserController::class, 'store'])
    ->middleware('can:users.create');
Route::put('/users/{id}', [UserController::class, 'update'])
    ->middleware('can:users.update');
Route::delete('/users/{id}', [UserController::class, 'destroy'])
    ->middleware('can:users.delete');

    // Donnees de gestion (chiffre d'affaires, tendances, export) : reservees
    // a l'administrateur. Un soignant n'a pas a connaitre le revenu global de
    // la clinique pour exercer.
    Route::middleware('can:statistiques.read')->group(function () {
        Route::get('/dashboard/chiffre-affaires', [DashboardController::class, 'chiffreAffaires']);
        Route::get('/dashboard/statistiques-mensuelles', [DashboardController::class, 'statistiquesMensuelles']);
        Route::get('/dashboard/export-statistiques', [DashboardController::class, 'exportStatistiques']);
    });


    // Gestion des rendez-vous (SCRUM-49)
    Route::get('/rendez-vous', [RendezVousController::class, 'index'])
        ->middleware('can:rendez-vous.read');

    Route::get('/rendez-vous/disponibilite', [RendezVousController::class, 'disponibilite'])
        ->middleware('can:rendez-vous.read');
    Route::get('/patients/{idPatient}/rendez-vous/historique', [RendezVousController::class, 'historiquePatient'])
        ->middleware('can:rendez-vous.read');
    Route::get('/rendez-vous/{id}', [RendezVousController::class, 'show'])
        ->middleware('can:rendez-vous.read');
    Route::post('/rendez-vous', [RendezVousController::class, 'store'])
        ->middleware('can:rendez-vous.create');
    Route::put('/rendez-vous/{id}', [RendezVousController::class, 'update'])
        ->middleware('can:rendez-vous.update');
    Route::delete('/rendez-vous/{id}', [RendezVousController::class, 'destroy'])
        ->middleware('can:rendez-vous.delete');
    Route::patch('/rendez-vous/{id}/annuler', [RendezVousController::class, 'annuler'])
      ->middleware('can:rendez-vous.update');
        // Gestion des patients - reservee au personnel medical/administratif
   
   
   // SCRUM-518 : chaque route exige une PERMISSION plutot qu'une liste de
   // roles ecrite en dur. La correspondance role -> permissions est definie
   // une seule fois dans App\Enums\Role, et les Gates correspondantes dans
   // AppServiceProvider. Ajouter ou retirer un role a une operation ne
   // demande donc plus de modifier ce fichier.
   Route::get('/patients', [PatientController::class, 'index'])
       ->middleware('can:patients.read');
   Route::get('/patients/search', [PatientController::class, 'search'])
       ->middleware('can:patients.read');
   Route::get('/patients/{id}', [PatientController::class, 'show'])
       ->middleware('can:patients.read');

   Route::post('/patients', [PatientController::class, 'store'])
       ->middleware('can:patients.create');

   Route::put('/patients/{id}', [PatientController::class, 'update'])
       ->middleware('can:patients.update');

   // Suppression d'un dossier medical : reservee a l'administrateur.
   Route::delete('/patients/{id}', [PatientController::class, 'destroy'])
       ->middleware('can:patients.delete');

   
    // Gestion des roles : permission 'roles.manage', accordee au seul
    // administrateur (SCRUM-518).
    Route::middleware('can:roles.manage')->group(function () {

        // Consulter les roles disponibles
        Route::get('/roles', [RoleController::class, 'index']);

        // Attribuer ou modifier le role d'un utilisateur
        Route::put('/users/{user}/role', [RoleController::class, 'update']);
    });

    // Gestion des medecins
    //
    // SCRUM-526 : ces routes etaient dans le groupe 'can:roles.manage'. Cette
    // permission decrit la gestion des comptes utilisateurs, pas le
    // referentiel medical : le controle etait donc correct par accident
    // (seul l'administrateur passait) mais impossible a faire evoluer sans
    // ouvrir la gestion des roles. Chaque route porte desormais la
    // permission de sa propre ressource.
    Route::get('/medecins', [MedecinController::class, 'index'])
        ->middleware('can:medecins.read');
    Route::get('/medecins/{id}', [MedecinController::class, 'show'])
        ->middleware('can:medecins.read');
    Route::post('/medecins', [MedecinController::class, 'store'])
        ->middleware('can:medecins.create');
    Route::put('/medecins/{id}', [MedecinController::class, 'update'])
        ->middleware('can:medecins.update');
    Route::delete('/medecins/{id}', [MedecinController::class, 'destroy'])
        ->middleware('can:medecins.delete');

    // Gestion des specialites
    Route::get('/specialites', [SpecialiteController::class, 'index'])
        ->middleware('can:specialites.read');
    Route::get('/specialites/{id}', [SpecialiteController::class, 'show'])
        ->middleware('can:specialites.read');
    Route::post('/specialites', [SpecialiteController::class, 'store'])
        ->middleware('can:specialites.create');
    Route::put('/specialites/{id}', [SpecialiteController::class, 'update'])
        ->middleware('can:specialites.update');
    Route::delete('/specialites/{id}', [SpecialiteController::class, 'destroy'])
        ->middleware('can:specialites.delete');

    // Gestion des ordonnances
    //
    // SCRUM-564 : ces routes n'exigeaient qu'un compte valide. Une ordonnance
    // est une donnee de sante, et /api/register est publique : n'importe qui
    // pouvait s'inscrire puis lire, creer, modifier ou SUPPRIMER l'ordonnance
    // de n'importe quel patient de la clinique.
    //
    // Les permissions utilisees existent depuis SCRUM-524 ; elles n'avaient
    // simplement jamais ete appliquees. La suppression reste reservee a
    // l'administrateur, comme pour tous les dossiers medicaux.
    //
    // Le cloisonnement "les siennes" (un medecin ses patients, un patient son
    // dossier) est applique dans le controller, ou l'ordonnance visee est
    // connue : le middleware can: ne voit que la permission, pas le dossier.
    Route::get('/ordonnances', [OrdonnanceController::class, 'index'])
        ->middleware('can:ordonnances.read');
        Route::get(
    '/ordonnances/consultations-disponibles',
    [OrdonnanceController::class, 'consultationsDisponibles']
)->middleware('can:ordonnances.create');
    Route::get('/ordonnances/{id}', [OrdonnanceController::class, 'show'])
        ->middleware('can:ordonnances.read');
    Route::get('/ordonnances/{id}/imprimer', [OrdonnanceController::class, 'imprimer'])
        ->middleware('can:ordonnances.read');
    Route::get('/ordonnances/{id}/telecharger', [OrdonnanceController::class, 'telecharger'])
    ->middleware('can:ordonnances.read');
    Route::post('/ordonnances', [OrdonnanceController::class, 'store'])
        ->middleware('can:ordonnances.create');
    Route::put('/ordonnances/{id}', [OrdonnanceController::class, 'update'])
        ->middleware('can:ordonnances.update');
    Route::delete('/ordonnances/{id}', [OrdonnanceController::class, 'destroy'])
        ->middleware('can:ordonnances.delete');

    // Gestion du contenu des ordonnances
    //
    // Les lignes portent le medicament, la posologie et la duree : elles
    // suivent exactement le regime de l'ordonnance qui les contient.
    Route::get('/ordonnances/{idOrdonnance}/lignes', [LigneOrdonnanceController::class, 'index'])
        ->middleware('can:ordonnances.read');
    Route::post('/ordonnances/{idOrdonnance}/lignes', [LigneOrdonnanceController::class, 'store'])
        ->middleware('can:ordonnances.update');
    Route::put('/lignes-ordonnance/{id}', [LigneOrdonnanceController::class, 'update'])
        ->middleware('can:ordonnances.update');
    Route::delete('/lignes-ordonnance/{id}', [LigneOrdonnanceController::class, 'destroy'])
        ->middleware('can:ordonnances.update');

    // Historique des ordonnances d'un patient
    //
    // SCRUM-564 : l'identifiant du patient vient de l'URL. Sans cloisonnement,
    // il suffisait de le changer pour lire l'historique medicamenteux de
    // n'importe qui. Le controller restreint le resultat au perimetre du role.
    Route::get(
        '/patients/{idPatient}/ordonnances/historique',
        [OrdonnanceController::class, 'historiquePatient']
    )->middleware('can:ordonnances.read');


    // Gestion des factures
    Route::get('/factures', [FactureController::class, 'index'])
        ->middleware('can:factures.read');
    Route::get('/factures/{id}', [FactureController::class, 'show'])
        ->middleware('can:factures.read');
    Route::post('/factures', [FactureController::class, 'store'])
        ->middleware('can:factures.create');
    Route::put('/factures/{id}', [FactureController::class, 'update'])
        ->middleware('can:factures.update');
    Route::patch('/factures/{id}/annuler', [FactureController::class, 'annuler'])
        ->middleware('can:factures.update');
    Route::get('/patients/{idPatient}/factures', [FactureController::class, 'historiquePatient'])
        ->middleware('can:factures.read');
    Route::get('/factures/{id}/imprimer', [FactureController::class, 'imprimer'])
        ->middleware('can:factures.read');
    Route::get('/factures/{id}/telecharger', [FactureController::class, 'telecharger'])
        ->middleware('can:factures.read');
    Route::delete('/factures/{id}', [FactureController::class, 'destroy'])
        ->middleware('can:factures.delete');
// Gestion des consultations (SCRUM-37)
Route::get('/consultations/stats', [ConsultationController::class, 'stats'])
    ->middleware('can:consultations.read');
Route::get('/consultations', [ConsultationController::class, 'index'])
    ->middleware('can:consultations.read');
Route::get('/consultations/{id}', [ConsultationController::class, 'show'])
    ->middleware('can:consultations.read');
Route::post('/consultations', [ConsultationController::class, 'store'])
    ->middleware('can:consultations.create');
Route::put('/consultations/{id}', [ConsultationController::class, 'update'])
    ->middleware('can:consultations.update');
Route::delete('/consultations/{id}', [ConsultationController::class, 'destroy'])
    ->middleware('can:consultations.delete');
    // Gestion des paiements
    Route::get('/paiements', [PaiementController::class, 'index'])
        ->middleware('can:paiements.read');
    Route::get('/paiements/{id}', [PaiementController::class, 'show'])
        ->middleware('can:paiements.read');
    Route::post('/paiements', [PaiementController::class, 'store'])
        ->middleware('can:paiements.create');
    Route::put('/paiements/{id}', [PaiementController::class, 'update'])
        ->middleware('can:paiements.update');
    Route::delete('/paiements/{id}', [PaiementController::class, 'destroy'])
        ->middleware('can:paiements.delete');

    // Gestion des hospitalisations
    Route::get('/hospitalisations', [HospitalisationController::class, 'index'])
        ->middleware('can:hospitalisations.read');
    Route::post('/hospitalisations', [HospitalisationController::class, 'store'])
        ->middleware('can:hospitalisations.create');
    Route::post('/hospitalisations/admettre', [HospitalisationController::class, 'admettre'])
        ->middleware('can:hospitalisations.create');
    Route::patch('/hospitalisations/{id}/sortir', [HospitalisationController::class, 'sortir'])
        ->middleware('can:hospitalisations.update');
    Route::get('/hospitalisations/{id}', [HospitalisationController::class, 'show'])
        ->middleware('can:hospitalisations.read');
    Route::put('/hospitalisations/{id}', [HospitalisationController::class, 'update'])
        ->middleware('can:hospitalisations.update');
    Route::delete('/hospitalisations/{id}', [HospitalisationController::class, 'destroy'])
        ->middleware('can:hospitalisations.delete');

    // Gestion des chambres
    Route::get('/chambres', [ChambreController::class, 'index'])
        ->middleware('can:chambres.read');
    Route::post('/chambres', [ChambreController::class, 'store'])
        ->middleware('can:chambres.create');
    Route::get('/chambres/disponibles', [ChambreController::class, 'disponibles'])
        ->middleware('can:chambres.read');
    Route::get('/chambres/{id}', [ChambreController::class, 'show'])
        ->middleware('can:chambres.read');
    Route::put('/chambres/{id}', [ChambreController::class, 'update'])
        ->middleware('can:chambres.update');
    Route::delete('/chambres/{id}', [ChambreController::class, 'destroy'])
        ->middleware('can:chambres.delete');

    // Gestion des demandes d'examen
    Route::get('/demandes-examen', [DemandeExamenController::class, 'index'])
        ->middleware('can:examens.read');
    Route::post('/demandes-examen', [DemandeExamenController::class, 'store'])
        ->middleware('can:examens.create');
    Route::get('/demandes-examen/{id}', [DemandeExamenController::class, 'show'])
        ->middleware('can:examens.read');
    Route::put('/demandes-examen/{id}', [DemandeExamenController::class, 'update'])
        ->middleware('can:examens.update');
    Route::delete('/demandes-examen/{id}', [DemandeExamenController::class, 'destroy'])
        ->middleware('can:examens.delete');

    // Gestion des résultats d'examen
    Route::get('/resultats', [ResultatController::class, 'index'])
        ->middleware('can:resultats.read');
    Route::post('/resultats', [ResultatController::class, 'store'])
        ->middleware('can:resultats.create');
    Route::get('/resultats/{id}', [ResultatController::class, 'show'])
        ->middleware('can:resultats.read');
    Route::put('/resultats/{id}', [ResultatController::class, 'update'])
        ->middleware('can:resultats.update');
    Route::delete('/resultats/{id}', [ResultatController::class, 'destroy'])
        ->middleware('can:resultats.delete');

});

// Health check
//
// SCRUM-526 : route volontairement publique (sondes Docker, workflow de
// deploiement, script de smoke test). Elle ne renvoie donc QUE 'ok' ou
// 'failed' par service. Auparavant elle recopiait le message de l'exception,
// qui contient l'hote, le port, le nom de la base et l'utilisateur : ces
// informations d'infrastructure etaient lisibles par n'importe quel visiteur
// non authentifie des que la base tombait. Le detail part maintenant dans les
// logs applicatifs, ou seule l'equipe y a acces.
Route::get('/health', function () {
    $healthy = true;

    foreach ([
        'database' => fn () => DB::connection()->getPdo(),
        'redis' => fn () => Redis::connection()->ping(),
    ] as $service => $sonde) {
        try {
            $sonde();
        } catch (\Throwable $e) {
            $healthy = false;

            Log::error("Health check: service {$service} indisponible", [
                'service' => $service,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // La sonde publique ne revele pas quels composants internes utilisent
    // PostgreSQL/Redis ni lequel est en panne. Les details restent dans les logs.
    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
});