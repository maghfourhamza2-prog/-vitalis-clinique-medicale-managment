// SCRUM-533 - Entrees de navigation, et qui les voit.
//
// La barre laterale listait neuf liens en dur, montres a tout le monde. Deux
// consequences : un patient se voyait proposer "Patients" et "Utilisateurs",
// qui lui repondent 403 ; et six liens sur neuf pointaient vers des chemins
// sans route, qui menaient donc a une page vide - depuis SCRUM-532, au
// tableau de bord.
//
// Chaque entree declare desormais la permission qui la justifie. La liste est
// filtree deux fois : par ce que le role permet, et par ce qui existe
// reellement dans le routeur. Le second filtre evite de recreer des modules
// fictifs juste pour avoir un menu complet - une entree reapparaitra d'elle
// meme le jour ou sa route sera ecrite.

import { routes } from './router/routes.js'
import { peut } from './rbac.js'

/**
 * Entrees possibles de la barre laterale.
 *
 * `permission` reprend la permission backend qui gouverne le module. Aucune
 * n'est inventee : voir src/rbac.js et backend/app/Enums/Role.php.
 *
 * `permission: null` signifie "aucune permission particuliere" : le tableau
 * de bord s'adapte lui-meme au role (DashboardController choisit son contenu
 * cote serveur), tout compte authentifie peut donc l'ouvrir.
 */
export const ENTREES_NAVIGATION = [
  { libelle: 'Tableau de bord', to: '/dashboard', icone: 'fi-rr-home', permission: null },
  { libelle: 'Patients', to: '/patients', icone: 'fi-rr-users-medical', permission: 'patients.read' },
  { libelle: 'Rendez-vous', to: '/rendez-vous', icone: 'fi-rr-calendar', permission: 'rendez-vous.read' },
  { libelle: 'Consultations', to: '/consultations', icone: 'fi-rr-stethoscope', permission: 'consultations.read' },
  { libelle: 'Examens', to: '/examens', icone: 'fi-rr-document', permission: 'examens.read' },
  { libelle: 'Hospitalisations', to: '/hospitalisations', icone: 'fi-rr-bed', permission: 'hospitalisations.read' },
  { libelle: 'Facturation', to: '/facturation', icone: 'fi-rr-receipt', permission: 'factures.read' },
  { libelle: 'Utilisateurs', to: '/utilisateurs', icone: 'fi-rr-user-gear', permission: 'roles.manage' },
  { libelle: 'Paramètres', to: '/parametres', icone: 'fi-rr-settings', permission: 'roles.manage' }
]

/**
 * Chemins reellement desservis par le routeur.
 *
 * L'attrape-tout de SCRUM-532 est exclu : il capte tout et ferait passer
 * n'importe quel chemin pour existant.
 *
 * @returns {Set<string>}
 */
export function cheminsExistants(table = routes) {
  const chemins = new Set()

  const parcourir = (liste, prefixe = '') => {
    for (const route of liste) {
      if (route.path.includes(':pathMatch')) {
        continue
      }

      const complet = route.path.startsWith('/')
        ? route.path
        : `${prefixe.replace(/\/$/, '')}/${route.path}`

      // Une route sans composant ni enfants ne fait que rediriger : elle n'a
      // pas de page a elle, mais elle mene bien quelque part.
      if (route.component || route.redirect) {
        chemins.add(complet)
      }

      if (route.children) {
        parcourir(route.children, complet)
      }
    }
  }

  parcourir(table)

  return chemins
}

/**
 * Entrees a afficher pour l'utilisateur connecte.
 *
 * @param {(permission: string) => boolean} autorise Test de permission.
 * @param {Set<string>} disponibles Chemins desservis par le routeur.
 */
export function entreesVisibles(autorise = peut, disponibles = cheminsExistants()) {
  return ENTREES_NAVIGATION.filter((entree) => {
    if (!disponibles.has(entree.to)) {
      return false
    }

    return entree.permission === null || autorise(entree.permission)
  })
}
