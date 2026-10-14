// SCRUM-535 - Acces frontend selon le role, en particulier par URL directe.
//
//   npm run test:acces
//
// Les tickets precedents ont masque le lien (SCRUM-533) puis les boutons
// (SCRUM-534). Ni l'un ni l'autre n'empeche de taper l'adresse a la main :
// c'est ce chemin-la qui est verifie ici, en faisant reellement naviguer un
// routeur construit sur la vraie table de routes.
//
// Ce que ces tests ne prouvent PAS : que les donnees sont protegees. Le
// backend en reste seul garant et repond 403 quoi qu'affiche l'interface.
// Ils verifient qu'on n'ouvre pas un ecran dont chaque requete sera refusee.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { createRouter, createMemoryHistory } from 'vue-router'

const creerStockage = () => {
  const d = new Map()
  return {
    getItem: (k) => (d.has(k) ? d.get(k) : null),
    setItem: (k, v) => d.set(k, String(v)),
    removeItem: (k) => d.delete(k),
    clear: () => d.clear()
  }
}
globalThis.localStorage = creerStockage()
globalThis.sessionStorage = creerStockage()

const { routes } = await import('../src/router/routes.js')
const { resolveNavigation } = await import('../src/router/guards.js')
const { saveSession, clearSession } = await import('../src/auth.js')
const { PERMISSIONS_PAR_ROLE, ROLES } = await import('../src/rbac.js')

const cas = []
const verifier = async (nom, fn) => { await fn(); cas.push(nom) }

const stub = { render: () => null }
const sansComposants = (liste) => liste.map((route) => ({
  ...route,
  ...(route.component ? { component: stub } : {}),
  ...(route.children ? { children: sansComposants(route.children) } : {})
}))
const routesTestables = sansComposants(routes)

/** Ou l'utilisateur atterrit reellement en saisissant ce chemin. */
const aller = async (chemin) => {
  const router = createRouter({ history: createMemoryHistory(), routes: routesTestables })
  router.beforeEach(resolveNavigation)
  await router.push(chemin)
  return router.currentRoute.value
}

const connecterAvecRole = (role) => {
  clearSession()
  saveSession({ token: 'jeton-de-test', user: { id: 1, name: 'Test', role }, remember: true })
}

// Ecrans prives et permission qui les gouverne.
const ECRANS = [
  { chemin: '/utilisateurs', permission: 'roles.manage' },
  { chemin: '/patients', permission: 'patients.read' },
  { chemin: '/rendez-vous', permission: 'rendez-vous.read' },
  { chemin: '/consultations', permission: 'consultations.read' },
  { chemin: '/ordonnances', permission: 'ordonnances.read' },
  { chemin: '/examens', permission: 'examens.read' },
  { chemin: '/hospitalisations', permission: 'hospitalisations.read' },
  { chemin: '/facturation', permission: 'factures.read' },
  { chemin: '/parametres', permission: 'roles.manage' },
  { chemin: '/dashboard', permission: null }
]

const TOUS_LES_ROLES = Object.values(ROLES)

const detient = (role, permission) =>
  permission === null || PERMISSIONS_PAR_ROLE[role].includes(permission)

// ------------------------------------------------- visiteur non authentifie

await verifier('SCRUM-535 non authentifie : tout ecran prive renvoie vers login', async () => {
  clearSession()

  for (const { chemin } of ECRANS) {
    const arrivee = await aller(chemin)
    assert.equal(arrivee.name, 'login', `${chemin} devrait renvoyer vers login`)
    assert.equal(arrivee.query.redirect, chemin, `${chemin} devrait etre memorise`)
  }
})

// ------------------------------------------- authentifie, role non autorise

/**
 * Le defaut corrige par SCRUM-535 : les routes privees n'exigeaient qu'un
 * compte. Un patient authentifie atteignait donc /utilisateurs et /patients
 * en tapant l'URL, malgre le menu filtre et les boutons masques.
 */
await verifier('SCRUM-535 URL directe : chaque role n atteint que ce qu il peut', async () => {
  for (const role of TOUS_LES_ROLES) {
    connecterAvecRole(role)

    for (const { chemin, permission } of ECRANS) {
      const arrivee = await aller(chemin)

      if (detient(role, permission)) {
        assert.equal(arrivee.path, chemin, `${role} devrait atteindre ${chemin}`)
      } else {
        assert.notEqual(
          arrivee.path,
          chemin,
          `${role} ne devrait pas atteindre ${chemin} en saisissant l URL`
        )
      }
    }
  }
})

await verifier('SCRUM-535 le refus mene au tableau de bord, jamais a une impasse', async () => {
  connecterAvecRole(ROLES.PATIENT)

  for (const chemin of ['/utilisateurs', '/patients', '/consultations', '/ordonnances', '/examens', '/hospitalisations', '/facturation', '/parametres']) {
    const arrivee = await aller(chemin)
    assert.equal(arrivee.name, 'dashboard', `${chemin} devrait mener au tableau de bord`)
  }
})

await verifier('SCRUM-535 un refus de role ne deconnecte pas', async () => {
  connecterAvecRole(ROLES.PATIENT)
  await aller('/utilisateurs')

  // Etre refuse sur un ecran n'est pas une session expiree : le jeton reste.
  const { isAuthenticated } = await import('../src/auth.js')
  assert.equal(isAuthenticated(), true)
})

// ----------------------------------------------- authentifie et autorise

await verifier('SCRUM-535 administrateur : tous les ecrans sont accessibles', async () => {
  connecterAvecRole(ROLES.ADMINISTRATEUR)

  for (const { chemin } of ECRANS) {
    const arrivee = await aller(chemin)
    assert.equal(arrivee.path, chemin)
  }
})

await verifier('SCRUM-535 le personnel garde acces aux dossiers patients', async () => {
  for (const role of [ROLES.MEDECIN, ROLES.SECRETAIRE, ROLES.INFIRMIER]) {
    connecterAvecRole(role)

    assert.equal((await aller('/patients')).path, '/patients', `role ${role}`)

    // ...mais pas a la gestion des comptes.
    assert.equal((await aller('/utilisateurs')).name, 'dashboard', `role ${role}`)
  }
})

await verifier('SCRUM-535 le tableau de bord reste ouvert a tous les roles', async () => {
  for (const role of TOUS_LES_ROLES) {
    connecterAvecRole(role)
    assert.equal((await aller('/dashboard')).path, '/dashboard', `role ${role}`)
  }
})

await verifier('SCRUM-535 role inconnu : aucun ecran privilegie', async () => {
  connecterAvecRole('super-admin')

  for (const chemin of ['/utilisateurs', '/patients', '/consultations', '/ordonnances', '/examens', '/hospitalisations', '/facturation', '/parametres']) {
    assert.equal((await aller(chemin)).name, 'dashboard', chemin)
  }
})

// ------------------------------------------- coherence de la declaration

/**
 * Garde-fou : une route privee ajoutee plus tard sans meta.permission
 * redeviendrait accessible a tous les roles authentifies.
 */
await verifier('SCRUM-535 toute route privee declare une permission', () => {
  // Seule exception assumee : le tableau de bord, dont le contenu est choisi
  // par le backend selon le role.
  const SANS_PERMISSION_ASSUMEE = ['/dashboard']

  const manquantes = []
  const parcourir = (liste, prefixe = '') => {
    for (const route of liste) {
      const complet = route.path.startsWith('/')
        ? route.path
        : `${prefixe.replace(/\/$/, '')}/${route.path}`

      if (route.component && !route.path.includes(':pathMatch')) {
        const publique = route.meta?.requiresAuth === false
        const declaree = route.meta?.permission !== undefined

        if (!publique && !declaree && !SANS_PERMISSION_ASSUMEE.includes(complet)) {
          manquantes.push(complet)
        }
      }

      if (route.children) parcourir(route.children, complet)
    }
  }

  // On ne parcourt que les enfants de l'espace authentifie : le parent porte
  // le layout, pas d'ecran a lui.
  for (const route of routes) {
    if (route.children) parcourir(route.children, route.path)
  }

  assert.deepEqual(
    manquantes,
    [],
    `routes privees sans meta.permission : ${manquantes.join(', ')}`
  )
})

await verifier('SCRUM-535 les permissions des routes existent cote backend', () => {
  const connues = new Set(Object.values(PERMISSIONS_PAR_ROLE).flat())

  const parcourir = (liste) => {
    for (const route of liste) {
      if (route.meta?.permission) {
        assert.ok(
          connues.has(route.meta.permission),
          `la route ${route.path} exige ${route.meta.permission}, inconnue du backend`
        )
      }
      if (route.children) parcourir(route.children)
    }
  }

  parcourir(routes)
})

// ------------------------------------------------- traitement des 403

const ici = dirname(fileURLToPath(import.meta.url))

/**
 * api.js n'est pas importable par Node (import.meta.env est injecte par
 * Vite). On verifie donc le contrat sur la source : un 403 signifie "vous
 * etes bien connecte, mais cette operation vous est refusee". Elargir
 * l'intercepteur aux 403 deconnecterait l'utilisateur a chaque refus de
 * permission et le ferait tourner en boucle sur la page de connexion.
 */
await verifier('SCRUM-535 un 403 de l API ne declenche pas de deconnexion', () => {
  const source = readFileSync(resolve(ici, '../src/api.js'), 'utf8')

  assert.ok(source.includes('status === 401'), "l intercepteur doit cibler le 401")
  assert.ok(
    !source.includes('status === 403') && !source.includes('status >= 401'),
    "l intercepteur ne doit pas traiter le 403 comme une session expiree"
  )

  // clearSession() ne doit apparaitre que dans la branche 401.
  const apresCondition = source.slice(source.indexOf('status === 401'))
  assert.ok(apresCondition.includes('clearSession()'), 'le 401 doit effacer la session')
  assert.equal(
    source.slice(0, source.indexOf('status === 401')).includes('clearSession()'),
    false,
    'clearSession() ne doit pas etre appele hors de la branche 401'
  )
})

console.log(cas.map((c, i) => `  OK ${String(i + 1).padStart(2)}. ${c}`).join('\n'))
console.log(`\n${cas.length}/${cas.length} verifications passees`)
