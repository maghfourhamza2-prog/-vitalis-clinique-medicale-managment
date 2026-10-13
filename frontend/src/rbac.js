// SCRUM-533 - Miroir cote interface de la matrice RBAC du backend.
//
// A QUOI CECI SERT, ET SURTOUT A QUOI CECI NE SERT PAS
//
// Ce module ne protege rien. L'autorite reste entierement cote serveur :
// chaque route d'API porte son middleware `can:` et refuse un acces
// interdit par un 403, que l'interface ait affiche le lien ou non. Un
// utilisateur qui modifierait son role dans le stockage de son navigateur
// verrait apparaitre des entrees de menu, et recevrait un 403 en cliquant.
//
// Ce module sert a ne pas proposer a quelqu'un ce qu'il ne peut pas faire :
// une secretaire n'a pas a voir "Utilisateurs" pour decouvrir en cliquant
// qu'elle n'y a pas droit.
//
// SOURCE DE VERITE
//
// La correspondance ci-dessous recopie backend/app/Enums/Role.php. Aucune
// permission n'est inventee ici : toute divergence est un bug de ce fichier,
// pas du backend. tests/rbac.test.mjs relit l'enum PHP et compare les deux,
// pour que la copie ne prenne pas silencieusement du retard.

import { getUser } from './auth.js'

/** Roles metier, valeurs identiques a la colonne users.role. */
export const ROLES = {
  ADMINISTRATEUR: 'administrateur',
  MEDECIN: 'medecin',
  SECRETAIRE: 'secretaire',
  INFIRMIER: 'infirmier',
  PATIENT: 'patient'
}

/**
 * Permissions par role, recopiees de App\Enums\Role::permissions().
 *
 * @type {Record<string, string[]>}
 */
export const PERMISSIONS_PAR_ROLE = {
  [ROLES.ADMINISTRATEUR]: [
    'patients.read', 'patients.create', 'patients.update', 'patients.delete',
    'roles.manage',
    'consultations.read', 'consultations.create', 'consultations.update', 'consultations.delete',
    'ordonnances.read', 'ordonnances.create', 'ordonnances.update', 'ordonnances.delete',
    'examens.read', 'examens.create', 'examens.update', 'examens.delete',
    'hospitalisations.read', 'hospitalisations.create', 'hospitalisations.update', 'hospitalisations.delete',
    'factures.read', 'factures.create', 'factures.update', 'factures.delete',
    'paiements.read', 'paiements.create', 'paiements.update', 'paiements.delete',
    'chambres.read', 'chambres.create', 'chambres.update', 'chambres.delete',
    'ai.use', 'audit-logs.read',
    'medecins.read', 'medecins.create', 'medecins.update', 'medecins.delete',
    'specialites.read', 'specialites.create', 'specialites.update', 'specialites.delete',
    'indicateurs.read', 'statistiques.read',
    'users.read', 'users.create', 'users.update', 'users.delete',
    'rendez-vous.read', 'rendez-vous.create', 'rendez-vous.update', 'rendez-vous.delete'
  ],

  [ROLES.MEDECIN]: [
    'patients.read', 'patients.create', 'patients.update',
    'consultations.read', 'consultations.create', 'consultations.update',
    'ordonnances.read', 'ordonnances.create', 'ordonnances.update',
    'examens.read',
    'hospitalisations.read', 'hospitalisations.create', 'hospitalisations.update',
    'chambres.read', 'ai.use',
    'medecins.read', 'specialites.read',
    'indicateurs.read',
    'rendez-vous.read', 'rendez-vous.create', 'rendez-vous.update'
  ],

  [ROLES.SECRETAIRE]: [
    'patients.read', 'patients.create', 'patients.update',
    'examens.read',
    'hospitalisations.read',
    'factures.read', 'factures.create', 'factures.update',
    'paiements.read', 'paiements.create', 'paiements.update',
    'chambres.read',
    'medecins.read', 'specialites.read',
    'indicateurs.read',
    'rendez-vous.read', 'rendez-vous.create', 'rendez-vous.update', 'rendez-vous.delete'
  ],

  [ROLES.INFIRMIER]: [
    'patients.read', 'patients.create', 'patients.update',
    'consultations.read',
    'ordonnances.read',
    'examens.read', 'examens.create', 'examens.update',
    'hospitalisations.read', 'hospitalisations.create', 'hospitalisations.update',
    'chambres.read', 'ai.use',
    'medecins.read', 'specialites.read',
    'indicateurs.read',
    'rendez-vous.read'
  ],

  [ROLES.PATIENT]: [
    'consultations.read',
    'ordonnances.read',
    'examens.read',
    'hospitalisations.read',
    'ai.use',
    'rendez-vous.read', 'rendez-vous.create'
  ]
}

/**
 * Permissions du role donne.
 *
 * Un role inconnu (valeur inattendue en base, session bricolee) ne donne
 * rien : on refuse par defaut plutot que d'afficher au hasard, comme le fait
 * la Gate cote backend.
 *
 * @param {string|null|undefined} role
 * @returns {string[]}
 */
export function permissionsDuRole(role) {
  return PERMISSIONS_PAR_ROLE[role] ?? []
}

/** Role de l'utilisateur connecte, ou null. */
export function roleCourant() {
  return getUser()?.role ?? null
}

/**
 * L'utilisateur connecte detient-il cette permission ?
 *
 * A n'utiliser que pour decider ce qui est AFFICHE. La verification qui
 * compte a lieu cote serveur.
 *
 * @param {string} permission
 */
export function peut(permission) {
  return permissionsDuRole(roleCourant()).includes(permission)
}

/**
 * L'utilisateur connecte detient-il au moins une de ces permissions ?
 *
 * @param {string[]} permissions
 */
export function peutAuMoinsUne(permissions) {
  return permissions.some((permission) => peut(permission))
}
