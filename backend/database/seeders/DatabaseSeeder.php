<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('security.allow_demo_seeding', false)) {
            if ($this->command) {
                $this->command->warn('Demo seeding is disabled for this environment.');
            }

            return;
        }

        $now = now();

        // Ne jamais versionner de mot de passe de demonstration connu.
        // Les valeurs peuvent etre definies localement par environnement.
        // A defaut, des secrets aleatoires sont generes a chaque seed.
        $adminPassword = config('security.seed_passwords.admin') ?: Str::random(32);
        $medecinPassword = config('security.seed_passwords.medecin') ?: Str::random(32);
        $patientPassword = config('security.seed_passwords.patient') ?: Str::random(32);

        if ($this->command && app()->environment(['local', 'testing'])) {
            $this->command->warn('Mots de passe de seed generes pour cet environnement uniquement.');
            $this->command->line('Admin   : '.$adminPassword);
            $this->command->line('Medecin : '.$medecinPassword);
            $this->command->line('Patient : '.$patientPassword);
        }

        /*
        |--------------------------------------------------------------------------
        | Spécialités
        |--------------------------------------------------------------------------
        */

        DB::table('specialites')->insert([
            [
                'id_specialite' => 1,
                'nom_specialite' => 'Médecine générale',
                'description' => 'Consultations générales et suivi médical.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_specialite' => 2,
                'nom_specialite' => 'Cardiologie',
                'description' => 'Diagnostic et traitement des maladies cardiovasculaires.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_specialite' => 3,
                'nom_specialite' => 'Pédiatrie',
                'description' => 'Soins médicaux des nourrissons et des enfants.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_specialite' => 4,
                'nom_specialite' => 'Dermatologie',
                'description' => 'Diagnostic et traitement des maladies de la peau.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_specialite' => 5,
                'nom_specialite' => 'Gynécologie-obstétrique',
                'description' => 'Suivi gynécologique et suivi de grossesse.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_specialite' => 6,
                'nom_specialite' => 'Traumatologie-orthopédie',
                'description' => 'Traitement des traumatismes, os et articulations.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Médecins
        |--------------------------------------------------------------------------
        */

        DB::table('medecins')->insert([
            [
                'id_medecin' => 1,
                'matricule' => 'MED-001',
                'nom' => 'El Amrani',
                'prenom' => 'Youssef',
                'telephone' => '+212661234501',
                'email' => 'youssef.elamrani@vitalis.ma',
                'date_embauche' => '2019-03-11',
                'tarif_consultation' => 250,
                'id_specialite' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medecin' => 2,
                'matricule' => 'MED-002',
                'nom' => 'Berrada',
                'prenom' => 'Salma',
                'telephone' => '+212661234502',
                'email' => 'salma.berrada@vitalis.ma',
                'date_embauche' => '2018-09-17',
                'tarif_consultation' => 400,
                'id_specialite' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medecin' => 3,
                'matricule' => 'MED-003',
                'nom' => 'Alaoui',
                'prenom' => 'Mehdi',
                'telephone' => '+212661234503',
                'email' => 'mehdi.alaoui@vitalis.ma',
                'date_embauche' => '2020-01-06',
                'tarif_consultation' => 300,
                'id_specialite' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medecin' => 4,
                'matricule' => 'MED-004',
                'nom' => 'Benjelloun',
                'prenom' => 'Imane',
                'telephone' => '+212661234504',
                'email' => 'imane.benjelloun@vitalis.ma',
                'date_embauche' => '2021-05-24',
                'tarif_consultation' => 350,
                'id_specialite' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medecin' => 5,
                'matricule' => 'MED-005',
                'nom' => 'Tazi',
                'prenom' => 'Nadia',
                'telephone' => '+212661234505',
                'email' => 'nadia.tazi@vitalis.ma',
                'date_embauche' => '2017-11-13',
                'tarif_consultation' => 400,
                'id_specialite' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medecin' => 6,
                'matricule' => 'MED-006',
                'nom' => 'Chraibi',
                'prenom' => 'Omar',
                'telephone' => '+212661234506',
                'email' => 'omar.chraibi@vitalis.ma',
                'date_embauche' => '2020-08-03',
                'tarif_consultation' => 350,
                'id_specialite' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Patients
        |--------------------------------------------------------------------------
        */

        DB::table('patients')->insert([
            [
                'id_patient' => 1,
                'nom' => 'Stitou',
                'prenom' => 'Mounia',
                'date_naissance' => '2000-04-18',
                'sexe' => 'F',
                'cin' => 'LA145821',
                'telephone' => '+212612340101',
                'email' => 'mounia.stitou@example.ma',
                'groupe_sanguin' => 'A+',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_patient' => 2,
                'nom' => 'El Fassi',
                'prenom' => 'Ayoub',
                'date_naissance' => '1988-07-12',
                'sexe' => 'M',
                'cin' => 'AB274593',
                'telephone' => '+212612340102',
                'email' => 'ayoub.elfassi@example.ma',
                'groupe_sanguin' => 'O+',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_patient' => 3,
                'nom' => 'Bennis',
                'prenom' => 'Khadija',
                'date_naissance' => '1976-02-23',
                'sexe' => 'F',
                'cin' => 'BE193847',
                'telephone' => '+212612340103',
                'email' => 'khadija.bennis@example.ma',
                'groupe_sanguin' => 'B+',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_patient' => 4,
                'nom' => 'Ouazzani',
                'prenom' => 'Anas',
                'date_naissance' => '1994-11-05',
                'sexe' => 'M',
                'cin' => 'K421785',
                'telephone' => '+212612340104',
                'email' => 'anas.ouazzani@example.ma',
                'groupe_sanguin' => 'A-',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_patient' => 5,
                'nom' => 'Amrani',
                'prenom' => 'Ghita',
                'date_naissance' => '2018-06-29',
                'sexe' => 'F',
                'cin' => 'BK302145',
                'telephone' => '+212612340105',
                'email' => 'famille.amrani@example.ma',
                'groupe_sanguin' => 'O+',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_patient' => 6,
                'nom' => 'Naciri',
                'prenom' => 'Zakaria',
                'date_naissance' => '1982-09-14',
                'sexe' => 'M',
                'cin' => 'EE548721',
                'telephone' => '+212612340106',
                'email' => 'zakaria.naciri@example.ma',
                'groupe_sanguin' => 'AB+',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Utilisateurs
        |--------------------------------------------------------------------------
        */

        DB::table('users')->insert([
    [
        'name' => 'Administrateur',
        'prenom' => 'Vitalis',
        'email' => 'admin@vitalis.ma',
        'password' => Hash::make($adminPassword),
        'role' => 'administrateur',
        'telephone' => '+212539000001',
        'adresse' => 'Avenue Mohammed VI, Tanger',
        'date_naissance' => '1990-01-01',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'El Amrani',
        'prenom' => 'Youssef',
        'email' => 'youssef.elamrani@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234501',
        'adresse' => 'Tanger',
        'date_naissance' => '1982-05-09',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 1,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Stitou',
        'prenom' => 'Mounia',
        'email' => 'mounia.stitou@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340101',
        'adresse' => 'Ksar El Kebir',
        'date_naissance' => '2000-04-18',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 1,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

        /*
        |--------------------------------------------------------------------------
        | Rendez-vous
        |--------------------------------------------------------------------------
        */

        DB::table('rendez_vous')->insert([
            [
                'id_rendez_vous' => 1,
                'date_rendez_vous' => '2026-09-08',
                'heure_debut' => '09:00',
                'heure_fin' => '09:30',
                'motif' => 'Migraine récurrente',
                'statut' => 'confirme',
                'id_patient' => 1,
                'id_medecin' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_rendez_vous' => 2,
                'date_rendez_vous' => '2026-09-08',
                'heure_debut' => '10:00',
                'heure_fin' => '10:30',
                'motif' => 'Douleurs thoraciques',
                'statut' => 'termine',
                'id_patient' => 2,
                'id_medecin' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_rendez_vous' => 3,
                'date_rendez_vous' => '2026-09-09',
                'heure_debut' => '11:00',
                'heure_fin' => '11:30',
                'motif' => 'Fièvre et toux',
                'statut' => 'confirme',
                'id_patient' => 5,
                'id_medecin' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_rendez_vous' => 4,
                'date_rendez_vous' => '2026-09-09',
                'heure_debut' => '14:00',
                'heure_fin' => '14:30',
                'motif' => 'Éruption cutanée',
                'statut' => 'en_attente',
                'id_patient' => 3,
                'id_medecin' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_rendez_vous' => 5,
                'date_rendez_vous' => '2026-09-10',
                'heure_debut' => '15:00',
                'heure_fin' => '15:30',
                'motif' => 'Douleur au genou',
                'statut' => 'termine',
                'id_patient' => 6,
                'id_medecin' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Consultations
        |--------------------------------------------------------------------------
        */

        DB::table('consultations')->insert([
            [
                'id_consultation' => 1,
                'motif' => 'Douleurs thoraciques',
                'diagnostic' => 'Hypertension artérielle légère',
                'observations' => 'Réduire le sel et contrôler régulièrement la tension.',
                'poids' => 82.5,
                'taille' => 178,
                'tension_arterielle' => '145/90',
                'temperature' => 36.8,
                'id_rendez_vous' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_consultation' => 2,
                'motif' => 'Douleur au genou',
                'diagnostic' => 'Entorse légère du genou droit',
                'observations' => 'Repos pendant sept jours.',
                'poids' => 76.2,
                'taille' => 174,
                'tension_arterielle' => '125/80',
                'temperature' => 36.7,
                'id_rendez_vous' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Médicaments
        |--------------------------------------------------------------------------
        */

        DB::table('medicaments')->insert([
            [
                'id_medicament' => 1,
                'nom_medicament' => 'Paracétamol',
                'forme' => 'Comprimé',
                'dosage' => '1 g',
                'fabricant' => 'Sothema Maroc',
                'description' => 'Antalgique et antipyrétique.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medicament' => 2,
                'nom_medicament' => 'Amlodipine',
                'forme' => 'Comprimé',
                'dosage' => '5 mg',
                'fabricant' => 'Cooper Pharma',
                'description' => 'Traitement de l’hypertension artérielle.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_medicament' => 3,
                'nom_medicament' => 'Ibuprofène',
                'forme' => 'Comprimé',
                'dosage' => '400 mg',
                'fabricant' => 'Laprophan',
                'description' => 'Médicament anti-inflammatoire.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Ordonnances
        |--------------------------------------------------------------------------
        */

        DB::table('ordonnances')->insert([
            [
                'id_ordonnance' => 1,
                'date_ordonnance' => '2026-09-08',
                'instructions_generales' => 'Contrôler régulièrement la tension.',
                'duree_traitement' => '30 jours',
                'type' => 'Traitement chronique',
                'id_consultation' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_ordonnance' => 2,
                'date_ordonnance' => '2026-09-10',
                'instructions_generales' => 'Prendre le traitement après les repas.',
                'duree_traitement' => '7 jours',
                'type' => 'Traitement ponctuel',
                'id_consultation' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('ligne_ordonnances')->insert([
            [
                'dosologie' => '5 mg',
                'frequence' => 'Une fois par jour',
                'duree' => '30 jours',
                'quantite' => 30,
                'instructions' => 'Le matin à heure fixe.',
                'id_ordonnance' => 1,
                'id_medicament' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'dosologie' => '400 mg',
                'frequence' => 'Deux fois par jour',
                'duree' => '5 jours',
                'quantite' => 10,
                'instructions' => 'Après les repas.',
                'id_ordonnance' => 2,
                'id_medicament' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Demandes d’examen et résultats
        |--------------------------------------------------------------------------
        */

        DB::table('demandes_examen')->insert([
            [
                'id_demande_examen' => 1,
                'date_demande' => '2026-09-08',
                'niveau_urgence' => 'normal',
                'indications_cliniques' => 'Bilan lipidique et fonction rénale.',
                'statut' => 'realise',
                'date_prevue' => '2026-09-09',
                'date_realisation' => '2026-09-09',
                'observation' => 'Patient à jeun.',
                'id_consultation' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_demande_examen' => 2,
                'date_demande' => '2026-09-10',
                'niveau_urgence' => 'normal',
                'indications_cliniques' => 'Radiographie du genou droit.',
                'statut' => 'realise',
                'date_prevue' => '2026-09-10',
                'date_realisation' => '2026-09-10',
                'observation' => 'Radiographie face et profil.',
                'id_consultation' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('resultats')->insert([
            [
                'date_resultat' => '2026-09-09',
                'resultat_detaille' => 'Cholestérol total légèrement élevé.',
                'conclusion' => 'Dyslipidémie modérée.',
                'valeurs_mesurees' => 'Cholestérol total : 2.25 g/L ; LDL : 1.42 g/L',
                'fichier_resultat' => 'bilan_lipidique_001.pdf',
                'image_resultat' => 'bilan_lipidique_001.png',
                'observations' => 'Contrôle conseillé dans trois mois.',
                'id_demande_examen' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'date_resultat' => '2026-09-10',
                'resultat_detaille' => 'Absence de fracture ou de lésion osseuse.',
                'conclusion' => 'Entorse sans complication osseuse.',
                'valeurs_mesurees' => 'Alignement articulaire conservé.',
                'fichier_resultat' => 'radiographie_genou_002.pdf',
                'image_resultat' => 'radiographie_genou_002.png',
                'observations' => 'Contrôle si la douleur persiste.',
                'id_demande_examen' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Chambres et hospitalisations
        |--------------------------------------------------------------------------
        */

        DB::table('chambres')->insert([
            [
                'id_chambre' => 1,
                'numero_chambre' => '101',
                'type_chambre' => 'Individuelle',
                'etage' => '1er étage',
                'capacite' => 1,
                'tarif_journalier' => 650,
                'statut' => 'disponible',
                'description' => 'Chambre individuelle climatisée.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_chambre' => 2,
                'numero_chambre' => '102',
                'type_chambre' => 'Double',
                'etage' => '1er étage',
                'capacite' => 2,
                'tarif_journalier' => 450,
                'statut' => 'occupee',
                'description' => 'Chambre double avec salle de bain.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_chambre' => 3,
                'numero_chambre' => '201',
                'type_chambre' => 'Soins intensifs',
                'etage' => '2e étage',
                'capacite' => 1,
                'tarif_journalier' => 1800,
                'statut' => 'occupee',
                'description' => 'Chambre équipée pour la surveillance intensive.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('hospitalisations')->insert([
            [
                'id_hospitalisation' => 1,
                'date_entree' => '2026-09-06',
                'heure_entree' => '18:30',
                'date_sortie' => null,
                'motif_hospitalisation' => 'Surveillance cardiaque',
                'diagnostic_entree' => 'Crise hypertensive',
                'statut' => 'en_cours',
                'observations' => 'Surveillance de la tension toutes les quatre heures.',
                'id_patient' => 3,
                'id_chambre' => 3,
                'id_medecin' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_hospitalisation' => 2,
                'date_entree' => '2026-09-03',
                'heure_entree' => '11:15',
                'date_sortie' => '2026-09-05',
                'motif_hospitalisation' => 'Observation après intervention',
                'diagnostic_entree' => 'Fracture du poignet traitée',
                'statut' => 'terminee',
                'observations' => 'Évolution favorable.',
                'id_patient' => 4,
                'id_chambre' => 2,
                'id_medecin' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Factures et paiements
        |--------------------------------------------------------------------------
        */

        DB::table('factures')->insert([
            [
                'id_facture' => 1,
                'numero_facture' => 'FAC-2026-0001',
                'date_facture' => '2026-09-08',
                'montant_total' => 650,
                'remise' => 50,
                'montant_net' => 600,
                'statut_paiement' => 'payee',
                'observations' => 'Consultation et bilan biologique.',
                'id_consultation' => 1,
                'id_hospitalisation' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_facture' => 2,
                'numero_facture' => 'FAC-2026-0002',
                'date_facture' => '2026-09-10',
                'montant_total' => 600,
                'remise' => 0,
                'montant_net' => 600,
                'statut_paiement' => 'partielle',
                'observations' => 'Consultation et radiographie.',
                'id_consultation' => 2,
                'id_hospitalisation' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id_facture' => 3,
                'numero_facture' => 'FAC-2026-0003',
                'date_facture' => '2026-09-05',
                'montant_total' => 1700,
                'remise' => 100,
                'montant_net' => 1600,
                'statut_paiement' => 'payee',
                'observations' => 'Hospitalisation en chambre double.',
                'id_consultation' => null,
                'id_hospitalisation' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('paiements')->insert([
            [
                'date_paiement' => '2026-09-08',
                'heure_paiement' => '11:20',
                'montant_paye' => 600,
                'mode_paiement' => 'carte_bancaire',
                'statut' => 'valide',
                'observations' => 'Paiement par carte bancaire.',
                'id_facture' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'date_paiement' => '2026-09-10',
                'heure_paiement' => '16:10',
                'montant_paye' => 300,
                'mode_paiement' => 'especes',
                'statut' => 'valide',
                'observations' => 'Acompte reçu à la caisse.',
                'id_facture' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'date_paiement' => '2026-09-05',
                'heure_paiement' => '14:45',
                'montant_paye' => 1600,
                'mode_paiement' => 'virement',
                'statut' => 'valide',
                'observations' => 'Virement bancaire confirmé.',
                'id_facture' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        /*
|--------------------------------------------------------------------------
| Spécialités supplémentaires
|--------------------------------------------------------------------------
*/

DB::table('specialites')->insert([
    [
        'id_specialite' => 7,
        'nom_specialite' => 'Neurologie',
        'description' => 'Diagnostic et traitement des maladies du système nerveux.',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_specialite' => 8,
        'nom_specialite' => 'Gastro-entérologie',
        'description' => 'Diagnostic et traitement des maladies digestives.',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_specialite' => 9,
        'nom_specialite' => 'Endocrinologie',
        'description' => 'Prise en charge du diabète et des troubles hormonaux.',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_specialite' => 10,
        'nom_specialite' => 'Psychiatrie',
        'description' => 'Diagnostic et traitement des troubles psychiques.',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_specialite' => 11,
        'nom_specialite' => 'Urologie',
        'description' => 'Prise en charge des maladies urinaires et urologiques.',
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

/*
|--------------------------------------------------------------------------
| Médecins supplémentaires
|--------------------------------------------------------------------------
*/

DB::table('medecins')->insert([
    [
        'id_medecin' => 7,
        'matricule' => 'MED-007',
        'nom' => 'Idrissi',
        'prenom' => 'Sara',
        'telephone' => '+212661234507',
        'email' => 'sara.idrissi@vitalis.ma',
        'date_embauche' => '2021-02-15',
        'tarif_consultation' => 450,
        'id_specialite' => 7,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_medecin' => 8,
        'matricule' => 'MED-008',
        'nom' => 'Lahlou',
        'prenom' => 'Hamza',
        'telephone' => '+212661234508',
        'email' => 'hamza.lahlou@vitalis.ma',
        'date_embauche' => '2020-10-05',
        'tarif_consultation' => 400,
        'id_specialite' => 8,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_medecin' => 9,
        'matricule' => 'MED-009',
        'nom' => 'El Mansouri',
        'prenom' => 'Kawtar',
        'telephone' => '+212661234509',
        'email' => 'kawtar.elmansouri@vitalis.ma',
        'date_embauche' => '2022-01-10',
        'tarif_consultation' => 400,
        'id_specialite' => 9,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_medecin' => 10,
        'matricule' => 'MED-010',
        'nom' => 'Ait Lahcen',
        'prenom' => 'Adil',
        'telephone' => '+212661234510',
        'email' => 'adil.aitlahcen@vitalis.ma',
        'date_embauche' => '2019-06-17',
        'tarif_consultation' => 450,
        'id_specialite' => 10,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_medecin' => 11,
        'matricule' => 'MED-011',
        'nom' => 'El Khatib',
        'prenom' => 'Othmane',
        'telephone' => '+212661234511',
        'email' => 'othmane.elkhatib@vitalis.ma',
        'date_embauche' => '2018-04-23',
        'tarif_consultation' => 400,
        'id_specialite' => 11,
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

/*
|--------------------------------------------------------------------------
| Nouveaux patients
|--------------------------------------------------------------------------
*/

DB::table('patients')->insert([
    [
        'id_patient' => 7,
        'nom' => 'Mernissi',
        'prenom' => 'Houda',
        'date_naissance' => '1991-01-30',
        'sexe' => 'F',
        'cin' => 'CD762914',
        'telephone' => '+212612340107',
        'email' => 'houda.mernissi@example.ma',
        'groupe_sanguin' => 'B-',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_patient' => 8,
        'nom' => 'Skalli',
        'prenom' => 'Rachid',
        'date_naissance' => '1965-12-08',
        'sexe' => 'M',
        'cin' => 'J359281',
        'telephone' => '+212612340108',
        'email' => 'rachid.skalli@example.ma',
        'groupe_sanguin' => 'O-',
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_patient' => 9,
        'nom' => 'Belkadi',
        'prenom' => 'Ikram',
        'date_naissance' => '2004-03-19',
        'sexe' => 'F',
        'cin' => 'TA619374',
        'telephone' => '+212612340109',
        'email' => 'ikram.belkadi@example.ma',
        'groupe_sanguin' => 'A+',
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

/*
|--------------------------------------------------------------------------
| Cinq nouveaux comptes utilisateurs
|--------------------------------------------------------------------------
*/

DB::table('users')->insert([
    [
        'name' => 'Idrissi',
        'prenom' => 'Sara',
        'email' => 'sara.idrissi@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234507',
        'adresse' => 'Tanger',
        'date_naissance' => '1986-07-14',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => 7,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Lahlou',
        'prenom' => 'Hamza',
        'email' => 'hamza.lahlou@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234508',
        'adresse' => 'Tétouan',
        'date_naissance' => '1984-11-21',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 8,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Mernissi',
        'prenom' => 'Houda',
        'email' => 'houda.mernissi@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340107',
        'adresse' => 'Larache',
        'date_naissance' => '1991-01-30',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 7,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Skalli',
        'prenom' => 'Rachid',
        'email' => 'rachid.skalli@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340108',
        'adresse' => 'Chefchaouen',
        'date_naissance' => '1965-12-08',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 8,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Belkadi',
        'prenom' => 'Ikram',
        'email' => 'ikram.belkadi@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340109',
        'adresse' => 'Kénitra',
        'date_naissance' => '2004-03-19',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 9,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

/*
|--------------------------------------------------------------------------
| Trois nouveaux rendez-vous
|--------------------------------------------------------------------------
*/

DB::table('rendez_vous')->insert([
    [
        'id_rendez_vous' => 6,
        'date_rendez_vous' => '2026-09-14',
        'heure_debut' => '09:00',
        'heure_fin' => '09:30',
        'motif' => 'Maux de tête et vertiges',
        'statut' => 'confirme',
        'id_patient' => 7,
        'id_medecin' => 7,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_rendez_vous' => 7,
        'date_rendez_vous' => '2026-09-14',
        'heure_debut' => '10:00',
        'heure_fin' => '10:30',
        'motif' => 'Douleurs abdominales',
        'statut' => 'confirme',
        'id_patient' => 8,
        'id_medecin' => 8,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'id_rendez_vous' => 8,
        'date_rendez_vous' => '2026-09-15',
        'heure_debut' => '11:00',
        'heure_fin' => '11:30',
        'motif' => 'Fatigue et contrôle hormonal',
        'statut' => 'en_attente',
        'id_patient' => 9,
        'id_medecin' => 9,
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);
/*
|--------------------------------------------------------------------------
| Comptes des médecins et patients restants
|--------------------------------------------------------------------------
*/

DB::table('users')->insert([
    // Médecins
    [
        'name' => 'Berrada',
        'prenom' => 'Salma',
        'email' => 'salma.berrada@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234502',
        'adresse' => 'Rabat',
        'date_naissance' => '1985-03-12',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => 2,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Alaoui',
        'prenom' => 'Mehdi',
        'email' => 'mehdi.alaoui@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234503',
        'adresse' => 'Casablanca',
        'date_naissance' => '1987-08-22',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 3,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Benjelloun',
        'prenom' => 'Imane',
        'email' => 'imane.benjelloun@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234504',
        'adresse' => 'Fès',
        'date_naissance' => '1988-01-17',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => 4,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Tazi',
        'prenom' => 'Nadia',
        'email' => 'nadia.tazi@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234505',
        'adresse' => 'Tanger',
        'date_naissance' => '1981-06-25',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => 5,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Chraibi',
        'prenom' => 'Omar',
        'email' => 'omar.chraibi@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234506',
        'adresse' => 'Marrakech',
        'date_naissance' => '1984-10-09',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 6,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'El Mansouri',
        'prenom' => 'Kawtar',
        'email' => 'kawtar.elmansouri@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234509',
        'adresse' => 'Agadir',
        'date_naissance' => '1989-04-11',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => 9,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Ait Lahcen',
        'prenom' => 'Adil',
        'email' => 'adil.aitlahcen@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234510',
        'adresse' => 'Béni Mellal',
        'date_naissance' => '1983-12-03',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 10,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'El Khatib',
        'prenom' => 'Othmane',
        'email' => 'othmane.elkhatib@vitalis.ma',
        'password' => Hash::make($medecinPassword),
        'role' => 'medecin',
        'telephone' => '+212661234511',
        'adresse' => 'Meknès',
        'date_naissance' => '1980-09-16',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => 11,
        'id_patient' => null,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],

    // Patients
    [
        'name' => 'El Fassi',
        'prenom' => 'Ayoub',
        'email' => 'ayoub.elfassi@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340102',
        'adresse' => 'Casablanca',
        'date_naissance' => '1988-07-12',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 2,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Bennis',
        'prenom' => 'Khadija',
        'email' => 'khadija.bennis@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340103',
        'adresse' => 'Fès',
        'date_naissance' => '1976-02-23',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 3,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Ouazzani',
        'prenom' => 'Anas',
        'email' => 'anas.ouazzani@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340104',
        'adresse' => 'Rabat',
        'date_naissance' => '1994-11-05',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 4,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Amrani',
        'prenom' => 'Ghita',
        'email' => 'famille.amrani@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340105',
        'adresse' => 'Tanger',
        'date_naissance' => '2018-06-29',
        'sexe' => 'F',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 5,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
    [
        'name' => 'Naciri',
        'prenom' => 'Zakaria',
        'email' => 'zakaria.naciri@example.ma',
        'password' => Hash::make($patientPassword),
        'role' => 'patient',
        'telephone' => '+212612340106',
        'adresse' => 'Kénitra',
        'date_naissance' => '1982-09-14',
        'sexe' => 'M',
        'statut' => 'actif',
        'id_medecin' => null,
        'id_patient' => 6,
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ],
]);

        /*
        |--------------------------------------------------------------------------
        | Correction des compteurs PostgreSQL
        |--------------------------------------------------------------------------
        */

        $sequences = [
            ['specialites', 'id_specialite'],
            ['medecins', 'id_medecin'],
            ['patients', 'id_patient'],
            ['rendez_vous', 'id_rendez_vous'],
            ['consultations', 'id_consultation'],
            ['medicaments', 'id_medicament'],
            ['ordonnances', 'id_ordonnance'],
            ['demandes_examen', 'id_demande_examen'],
            ['chambres', 'id_chambre'],
            ['hospitalisations', 'id_hospitalisation'],
            ['factures', 'id_facture'],
        ];

        foreach ($sequences as [$table, $primaryKey]) {
            DB::statement(
                "SELECT setval(
                    pg_get_serial_sequence('$table', '$primaryKey'),
                    (SELECT MAX($primaryKey) FROM $table)
                )"
            );
        }
    }
}