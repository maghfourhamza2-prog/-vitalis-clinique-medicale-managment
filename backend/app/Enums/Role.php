<?php

namespace App\Enums;

enum Role: string
{
    case ADMINISTRATEUR = 'administrateur';
    case MEDECIN = 'medecin';
    case SECRETAIRE = 'secretaire';
    case INFIRMIER = 'infirmier';
    case PATIENT = 'patient';

    /**
     * SCRUM-518 - Permissions accordees a ce role.
     *
     * C'est l'unique endroit ou se decide "qui a le droit de faire quoi".
     *
     * La suppression d'un patient est reservee a l'administrateur : c'est une
     * action irreversible sur un dossier medical. La lecture, la creation et la
     * mise a jour restent ouvertes a l'ensemble du personnel, comme avant.
     *
     * SCRUM-524 - Meme logique etendue aux consultations, ordonnances,
     * examens et hospitalisations : la suppression reste reservee a
     * l'administrateur pour tous les dossiers medicaux. Le perimetre de
     * chaque role suit la maquette RBAC (Tous / Les siennes / Acces limite /
     * Lecture / Non) ; la distinction "toutes les donnees" vs "les siennes"
     * sera affinee au niveau des Policies quand les Controllers existeront.
     *
     * SCRUM-526 - Referentiel medecins/specialites : l'ensemble du personnel
     * le consulte en lecture (l'annuaire est necessaire pour orienter un
     * patient), mais seul l'administrateur le modifie. Le role patient n'y a
     * pas acces : aucune route ne le lui expose aujourd'hui.
     *
     * SCRUM-527 - Tableau de bord : tout le personnel lit les indicateurs
     * d'activite, mais le chiffre d'affaires et les tendances restent a
     * l'administrateur. Le role patient n'a ni l'un ni l'autre : ces donnees
     * couvrent toute la clinique, alors qu'un patient ne doit voir que son
     * propre dossier.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATEUR => [
                Permission::PATIENTS_READ,
                Permission::PATIENTS_CREATE,
                Permission::PATIENTS_UPDATE,
                Permission::PATIENTS_DELETE,
                Permission::ROLES_MANAGE,
                Permission::CONSULTATIONS_READ,
                Permission::CONSULTATIONS_CREATE,
                Permission::CONSULTATIONS_UPDATE,
                Permission::CONSULTATIONS_DELETE,
                Permission::ORDONNANCES_READ,
                Permission::ORDONNANCES_CREATE,
                Permission::ORDONNANCES_UPDATE,
                Permission::ORDONNANCES_DELETE,
                Permission::EXAMENS_READ,
                Permission::EXAMENS_CREATE,
                Permission::EXAMENS_UPDATE,
                Permission::EXAMENS_DELETE,
                Permission::HOSPITALISATIONS_READ,
                Permission::HOSPITALISATIONS_CREATE,
                Permission::HOSPITALISATIONS_UPDATE,
                Permission::HOSPITALISATIONS_DELETE,
                Permission::FACTURES_READ,
                Permission::FACTURES_CREATE,
                Permission::FACTURES_UPDATE,
                Permission::FACTURES_DELETE,
                Permission::PAIEMENTS_READ,
                Permission::PAIEMENTS_CREATE,
                Permission::PAIEMENTS_UPDATE,
                Permission::PAIEMENTS_DELETE,
                Permission::CHAMBRES_READ,
                Permission::CHAMBRES_CREATE,
                Permission::CHAMBRES_UPDATE,
                Permission::CHAMBRES_DELETE,
                Permission::AI_USE,
                Permission::AUDIT_LOGS_READ,
                ////Gestion des rendez-vous
                Permission::RENDEZ_VOUS_READ,
                Permission::RENDEZ_VOUS_CREATE,
                Permission::RENDEZ_VOUS_UPDATE,
                Permission::RENDEZ_VOUS_DELETE,
                
                /////////////////////
                Permission::MEDECINS_READ,
                Permission::MEDECINS_CREATE,
                Permission::MEDECINS_UPDATE,
                Permission::MEDECINS_DELETE,
                Permission::SPECIALITES_READ,
                Permission::SPECIALITES_CREATE,
                Permission::SPECIALITES_UPDATE,
                Permission::SPECIALITES_DELETE,
                Permission::INDICATEURS_READ,
                Permission::STATISTIQUES_READ,
//// Gestion des utilisateurs
                Permission::USERS_READ,
                Permission::USERS_CREATE,
                Permission::USERS_UPDATE,
                Permission::USERS_DELETE,
            ],

            // Consultations/ordonnances/hospitalisations "les siennes" :
            // gere ses propres dossiers (lecture, creation, mise a jour),
            // jamais de suppression. Examens "les siens" : le medecin
            // consulte les resultats mais ne les saisit pas lui-meme.
            self::MEDECIN => [
                Permission::PATIENTS_READ,
                Permission::PATIENTS_CREATE,
                Permission::PATIENTS_UPDATE,
                Permission::CONSULTATIONS_READ,
                Permission::CONSULTATIONS_CREATE,
                Permission::CONSULTATIONS_UPDATE,
                Permission::ORDONNANCES_READ,
                Permission::ORDONNANCES_CREATE,
                Permission::ORDONNANCES_UPDATE,
                Permission::EXAMENS_READ,
                Permission::HOSPITALISATIONS_READ,
                Permission::HOSPITALISATIONS_CREATE,
                Permission::HOSPITALISATIONS_UPDATE,
                Permission::CHAMBRES_READ,
                Permission::AI_USE,
                ////////// Gestion des rendez-vous
                Permission::RENDEZ_VOUS_READ,
                Permission::RENDEZ_VOUS_CREATE,
                Permission::RENDEZ_VOUS_UPDATE,
                ////////////////
                Permission::MEDECINS_READ,
                Permission::SPECIALITES_READ,
                Permission::INDICATEURS_READ,
            ],

            // Pas d'acces aux consultations ni ordonnances (donnees
            // cliniques). Acces limite en lecture aux examens et
            // hospitalisations pour la coordination administrative.
            self::SECRETAIRE => [
                Permission::PATIENTS_READ,
                Permission::PATIENTS_CREATE,
                Permission::PATIENTS_UPDATE,
                Permission::EXAMENS_READ,
                Permission::HOSPITALISATIONS_READ,
                Permission::FACTURES_READ,
                Permission::FACTURES_CREATE,
                Permission::FACTURES_UPDATE,
                Permission::PAIEMENTS_READ,
                Permission::PAIEMENTS_CREATE,
                Permission::PAIEMENTS_UPDATE,
                Permission::CHAMBRES_READ,
                //// Gestion des rendez-vous
                Permission::RENDEZ_VOUS_READ,
                Permission::RENDEZ_VOUS_CREATE,
                Permission::RENDEZ_VOUS_UPDATE,
                Permission::RENDEZ_VOUS_DELETE,
                /////////
                Permission::MEDECINS_READ,
                Permission::SPECIALITES_READ,
                Permission::INDICATEURS_READ,
            ],

            // Acces limite en lecture aux consultations, lecture seule des
            // ordonnances. Gere pleinement les examens et hospitalisations
            // (saisie des resultats, suivi des sejours), sans suppression.
            self::INFIRMIER => [
                Permission::PATIENTS_READ,
                Permission::PATIENTS_CREATE,
                Permission::PATIENTS_UPDATE,
                Permission::CONSULTATIONS_READ,
                Permission::ORDONNANCES_READ,
                Permission::EXAMENS_READ,
                Permission::EXAMENS_CREATE,
                Permission::EXAMENS_UPDATE,
                Permission::HOSPITALISATIONS_READ,
                Permission::HOSPITALISATIONS_CREATE,
                Permission::HOSPITALISATIONS_UPDATE,
                Permission::CHAMBRES_READ,
                Permission::AI_USE,
                //// Gestion des rendez-vous
                Permission::RENDEZ_VOUS_READ,
                //////////
                Permission::MEDECINS_READ,
                Permission::SPECIALITES_READ,
                Permission::INDICATEURS_READ,
            ],

            // Un patient n'accede pas aux dossiers des autres patients.
            // Lecture seule de ses propres donnees medicales (le perimetre
            // "les siennes" est applique via Policy, pas ici).
            self::PATIENT => [
                Permission::CONSULTATIONS_READ,
                Permission::ORDONNANCES_READ,
                Permission::EXAMENS_READ,
                //// Gestion des rendez-vous
                Permission::RENDEZ_VOUS_READ,
                Permission::RENDEZ_VOUS_CREATE,
                /////////////
                Permission::HOSPITALISATIONS_READ,
                Permission::AI_USE,
            ],
        };
    }

    /**
     * SCRUM-518 - Ce role accorde-t-il la permission demandee ?
     */
    public function accorde(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * SCRUM-528 - Ce role voit-il TOUS les dossiers, ou seulement les siens ?
     *
     * Une permission dit ce que l'utilisateur a le droit de faire
     * (CONSULTATIONS_READ). Elle ne dit pas SUR QUELS dossiers. Les enums
     * decrivaient depuis SCRUM-524 un perimetre "les siennes" pour le medecin
     * et le patient, mais rien ne l'appliquait : les Gates repondaient
     * "autorise" sans jamais regarder le dossier vise. Un patient muni de
     * CONSULTATIONS_READ aurait donc lu les consultations de tout le monde
     * des qu'une route aurait consomme cette permission.
     *
     * Cette methode est le pendant manquant de accorde() : la premiere
     * repond "a-t-il le droit ?", celle-ci "sur quels dossiers ?".
     *
     * - Administrateur : responsable de l'etablissement.
     * - Secretaire : accueille et oriente indifferemment tous les patients.
     * - Infirmier : les soins d'un service ne suivent pas le decoupage par
     *   medecin traitant.
     * - Medecin : ses propres patients (secret medical entre confreres).
     * - Patient : son seul dossier.
     */
    public function accedeATousLesDossiers(): bool
    {
        return match ($this) {
            self::ADMINISTRATEUR, self::SECRETAIRE, self::INFIRMIER => true,
            self::MEDECIN, self::PATIENT => false,
        };
    }
}