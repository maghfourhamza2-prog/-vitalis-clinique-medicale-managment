<?php

namespace App\Enums;

/**
 * SCRUM-518 - Permissions de l'application.
 *
 * Un ROLE dit qui est l'utilisateur (medecin, secretaire...).
 * Une PERMISSION dit ce qu'il a le droit de faire (lire un patient, en
 * supprimer un...). Les routes s'appuient sur les permissions : le jour ou un
 * role change de perimetre, seule la table de correspondance de l'enum Role
 * est a modifier, pas les routes.
 *
 * Volontairement sans table en base ni package externe : la liste est courte
 * et connue a l'avance, un enum suffit et reste lisible.
 *
 * SCRUM-524 - Ajout des permissions sur les donnees medicales (consultations,
 * ordonnances, examens, hospitalisations). La suppression reste partout
 * reservee a l'administrateur : ce sont des dossiers medicaux, jamais
 * supprimes par le personnel soignant ou administratif.
 *
 * Ces permissions ne distinguent pas encore "toutes les donnees" de
 * "seulement les siennes" (voir maquette RBAC) : cette restriction par
 * propriete sera appliquee au niveau des Policies quand les Controllers
 * seront ecrits, pas dans cet enum.
 */
enum Permission: string
{
    case PATIENTS_READ = 'patients.read';
    case PATIENTS_CREATE = 'patients.create';
    case PATIENTS_UPDATE = 'patients.update';
    case PATIENTS_DELETE = 'patients.delete';
    case ROLES_MANAGE = 'roles.manage';

    case CONSULTATIONS_READ = 'consultations.read';
    case CONSULTATIONS_CREATE = 'consultations.create';
    case CONSULTATIONS_UPDATE = 'consultations.update';
    case CONSULTATIONS_DELETE = 'consultations.delete';

    case ORDONNANCES_READ = 'ordonnances.read';
    case ORDONNANCES_CREATE = 'ordonnances.create';
    case ORDONNANCES_UPDATE = 'ordonnances.update';
    case ORDONNANCES_DELETE = 'ordonnances.delete';

    case EXAMENS_READ = 'examens.read';
    case EXAMENS_CREATE = 'examens.create';
    case EXAMENS_UPDATE = 'examens.update';
    case EXAMENS_DELETE = 'examens.delete';

    case HOSPITALISATIONS_READ = 'hospitalisations.read';
    case HOSPITALISATIONS_CREATE = 'hospitalisations.create';
    case HOSPITALISATIONS_UPDATE = 'hospitalisations.update';
    case HOSPITALISATIONS_DELETE = 'hospitalisations.delete';

    // Facturation et encaissements : donnees financieres sensibles.
    case FACTURES_READ = 'factures.read';
    case FACTURES_CREATE = 'factures.create';
    case FACTURES_UPDATE = 'factures.update';
    case FACTURES_DELETE = 'factures.delete';

    case PAIEMENTS_READ = 'paiements.read';
    case PAIEMENTS_CREATE = 'paiements.create';
    case PAIEMENTS_UPDATE = 'paiements.update';
    case PAIEMENTS_DELETE = 'paiements.delete';

    // Referentiel des chambres. La consultation est utile au personnel,
    // tandis que la modification du referentiel reste administrative.
    case CHAMBRES_READ = 'chambres.read';
    case CHAMBRES_CREATE = 'chambres.create';
    case CHAMBRES_UPDATE = 'chambres.update';
    case CHAMBRES_DELETE = 'chambres.delete';

    // Assistant medical : permission explicite afin qu'un nouveau role
    // n'obtienne jamais l'acces automatiquement par le seul fait d'etre connecte.
    case AI_USE = 'ai.use';

    // Journal de securite : lecture reservee a l'administration.
    case AUDIT_LOGS_READ = 'audit-logs.read';
 // SCRUM-49 - Gestion des rendez-vous '( bax n3adlo les acces Gestion des rendez-vous  )
    //
    // La suppression reste reservee a l'administrateur et au secretariat :
    // annuler un rendez-vous engage un patient et un medecin, ce n'est pas
    // un geste que tout le personnel doit pouvoir faire seul. Le medecin
    // gere ses rendez-vous (lecture, creation, mise a jour) mais ne les
    // supprime pas ; le patient peut consulter et prendre rendez-vous, sans
    // pouvoir le modifier ou l'annuler lui-meme (voir accueil/secretariat).
    case RENDEZ_VOUS_READ = 'rendez-vous.read';
    case RENDEZ_VOUS_CREATE = 'rendez-vous.create';
    case RENDEZ_VOUS_UPDATE = 'rendez-vous.update';
    case RENDEZ_VOUS_DELETE = 'rendez-vous.delete';
    // SCRUM-526 - Referentiel medecins et specialites.
    //
    // Ces routes etaient protegees par 'roles.manage', une permission qui ne
    // decrit pas la ressource : gerer le referentiel des medecins n'est pas
    // gerer les roles des utilisateurs. Elles ont desormais leurs propres
    // permissions, ce qui permet au personnel de consulter l'annuaire (utile
    // pour la prise de rendez-vous) sans lui ouvrir la gestion des comptes.
    case MEDECINS_READ = 'medecins.read';
    case MEDECINS_CREATE = 'medecins.create';
    case MEDECINS_UPDATE = 'medecins.update';
    case MEDECINS_DELETE = 'medecins.delete';

    case SPECIALITES_READ = 'specialites.read';
    case SPECIALITES_CREATE = 'specialites.create';
    case SPECIALITES_UPDATE = 'specialites.update';
    case SPECIALITES_DELETE = 'specialites.delete';

    // SCRUM-527 - Donnees agregees du tableau de bord.
    //
    // Les routes /api/dashboard/* n'exigeaient qu'un compte valide, sans
    // aucune permission. Elles sont ici separees en deux niveaux, parce
    // qu'elles n'ont pas la meme sensibilite.
    //
    // INDICATEURS_READ : compteurs d'activite de la clinique et agenda du
    // jour. C'est la matiere du tableau de bord du personnel.
    //
    // STATISTIQUES_READ : chiffre d'affaires, tendances sur six mois et
    // export CSV. Ce sont des donnees de gestion, pas des donnees de soin :
    // un soignant n'a pas a connaitre le revenu global de la clinique.
    //
    // Attention : ces permissions portent sur des donnees AGREGEES couvrant
    // toute la clinique. Elles ne se confondent pas avec CONSULTATIONS_READ
    // ou EXAMENS_READ, qu'un patient detient pour SES propres dossiers.
    case INDICATEURS_READ = 'indicateurs.read';
    case STATISTIQUES_READ = 'statistiques.read';



        // SCRUM-739 - Gestion des comptes utilisateurs.
    //
    // Distincte de ROLES_MANAGE : gerer un compte (creer, modifier ses
    // informations, desactiver) n'est pas la meme operation que modifier son
    // role. Reservee a l'administrateur, seul habilite a gerer les comptes.
    case USERS_READ = 'users.read';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';
}