// SCRUM-534 - Quelle permission gouverne quelle action d'ecran.
//
// Les ecrans affichaient leurs boutons a tout le monde : "Nouveau patient"
// et "Consultation rapide" sur le tableau de bord, "Ajouter un utilisateur",
// "Modifier" et "Supprimer" sur la gestion des comptes. Un patient voyait
// donc des actions qui lui repondent 403.
//
// Chaque action est rattachee ici a la permission backend qui la gouverne
// vraiment. Rien n'est invente : ces valeurs viennent de src/rbac.js, lui-meme
// miroir verifie de backend/app/Enums/Role.php.
//
// Rappel, comme pour la navigation de SCRUM-533 : masquer n'est pas
// proteger. Le backend refuse l'operation par un 403 que le bouton ait ete
// affiche ou non. On evite seulement de proposer ce qui sera refuse.

import { peut, peutAuMoinsUne } from './rbac.js'

/**
 * Action d'ecran -> permission requise.
 *
 * Cle de la forme "ecran.action", lisible dans les templates.
 */
export const PERMISSION_PAR_ACTION = {
  // Tableau de bord - panneau "Activite rapide"
  //
  // SCRUM-605 : 'rendez-vous.create' existe depuis SCRUM-49 et remplace le
  // pis-aller 'indicateurs.read' utilise tant que le module n'avait pas de
  // controller. La permission decrit desormais reellement l'action.
  'tableauBord.nouveauRendezVous': 'rendez-vous.create',
  'tableauBord.nouveauPatient': 'patients.create',
  'tableauBord.consultationRapide': 'consultations.create',
  'tableauBord.demandeExamen': 'examens.create',

  // Dossiers patients
  'patients.creer': 'patients.create',
  'patients.modifier': 'patients.update',
  'patients.supprimer': 'patients.delete',

  // SCRUM-614 - Liaison frontend/backend du module Rendez-vous.
  'rendezVous.creer': 'rendez-vous.create',
  'rendezVous.modifier': 'rendez-vous.update',
  'rendezVous.supprimer': 'rendez-vous.delete',
  'rendezVous.annuler': 'rendez-vous.update',

    // SCRUM-37/38 - Liaison frontend/backend du module Consultations.
  'consultations.creer': 'consultations.create',
  'consultations.modifier': 'consultations.update',
  'consultations.supprimer': 'consultations.delete',

  // Examens
  'examens.creer': 'examens.create',
  'examens.modifier': 'examens.update',
  'examens.supprimer': 'examens.delete',

  // Hospitalisations
  'hospitalisations.creer': 'hospitalisations.create',
  'hospitalisations.modifier': 'hospitalisations.update',
  'hospitalisations.sortir': 'hospitalisations.update',
  'hospitalisations.supprimer': 'hospitalisations.delete',

  // Facturation et paiements
  'factures.creer': 'factures.create',
  'factures.modifier': 'factures.update',
  'factures.annuler': 'factures.update',
  'factures.supprimer': 'factures.delete',
  'paiements.creer': 'paiements.create',

  // Parametres administratifs
  'parametres.modifier': 'roles.manage',

  // Comptes utilisateurs : le backend ne connait qu'une permission pour tout
  // ce module, 'roles.manage', accordee au seul administrateur.
  'utilisateurs.ajouter': 'roles.manage',
  'utilisateurs.consulter': 'roles.manage',
  'utilisateurs.modifier': 'roles.manage',
  'utilisateurs.supprimer': 'roles.manage'
  
}

/**
 * L'utilisateur connecte peut-il voir cette action ?
 *
 * Une cle inconnue renvoie false : mieux vaut masquer une action mal
 * declaree que la montrer a tout le monde.
 *
 * @param {string} action
 */
export function peutAction(action) {
  const permission = PERMISSION_PAR_ACTION[action]

  return permission === undefined ? false : peut(permission)
}

/**
 * Au moins une de ces actions est-elle visible ?
 *
 * Sert a masquer le conteneur entier - un panneau "Activite rapide" vide n'a
 * pas de raison de s'afficher.
 *
 * @param {string[]} actions
 */
export function peutAuMoinsUneAction(actions) {
  const permissions = actions
    .map((action) => PERMISSION_PAR_ACTION[action])
    .filter((permission) => permission !== undefined)

  return peutAuMoinsUne(permissions)
}