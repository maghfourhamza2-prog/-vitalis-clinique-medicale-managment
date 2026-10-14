// SCRUM-532 - Verification de la protection des routes sur la VRAIE table.
//
//   npm run test:routes
//
// guards.test.mjs verifie la decision de la garde a partir d'objets de route
// ecrits pour l'occasion. Utile, mais aveugle a la table reelle : une route
// mal declaree, dupliquee ou absente y passait inapercue. Ce fichier importe
// routes.js et fait naviguer un vrai routeur, garde installee, pour observer
// ou l'utilisateur atterrit reellement.
import assert from 'node:assert/strict'
import { createRouter, createMemoryHistory } from 'vue-router'

// Stub minimal des stockages du navigateur (Node ne les fournit pas).
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
const { saveSession, clearSession } = await import('../src/auth.js')
const { resolveNavigation } = await import('../src/router/guards.js')

const cas = []
const verifier = async (nom, fn) => { await fn(); cas.push(nom) }

/**
 * Meme table, composants remplaces par un stub.
 *
 * vue-router charge le composant au moment de naviguer ; Node ne sait pas
 * compiler un fichier .vue. Ce qui est verifie ici - chemins, noms, meta,
 * ordre de declaration, hierarchie - ne depend pas du contenu des
 * composants. Tout le reste de la table est conserve tel quel : c'est bien
 * la vraie table qui est exercee, pas une copie ecrite pour le test.
 */
const stub = { render: () => null }

const sansComposants = (liste) => liste.map((route) => ({
  ...route,
  ...(route.component ? { component: stub } : {}),
  ...(route.children ? { children: sansComposants(route.children) } : {})
}))

const routesTestables = sansComposants(routes)

/**
 * Routeur neuf, garde installee. On en recree un par navigation : vue-router
 * refuse de renavigger vers l'emplacement courant, ce qui fausserait le test.
 */
const routeurNeuf = () => {
  const router = createRouter({ history: createMemoryHistory(), routes: routesTestables })
  router.beforeEach(resolveNavigation)
  return router
}

/** Ou l'utilisateur atterrit reellement apres avoir demande ce chemin. */
const naviguer = async (chemin) => {
  const router = routeurNeuf()
  await router.push(chemin)
  return router.currentRoute.value
}

// SCRUM-535 : "connecte" ne suffit plus. Les routes privees declarent
// desormais une permission (meta.permission), il faut donc un role pour les
// atteindre. On se connecte en administrateur : ce fichier verifie la
// protection par AUTHENTIFICATION, le filtrage par ROLE est verifie dans
// acces.test.mjs.
const connecter = () => {
  clearSession()
  saveSession({
    token: 'jeton-de-test',
    user: { id: 1, name: 'Test', role: 'administrateur' },
    remember: true
  })
}
const deconnecter = () => { localStorage.clear(); sessionStorage.clear() }

// Chemins declares comme accessibles sans authentification.
const CHEMINS_PUBLICS = ['/login', '/forgot-password', '/reset-password']

// Chemins qui doivent exiger une authentification.
const CHEMINS_PRIVES = [
  '/dashboard',
  '/patients',
  '/rendez-vous',
  '/consultations',
  '/ordonnances',
  '/utilisateurs',
  '/examens',
  '/hospitalisations',
  '/facturation',
  '/parametres'
]

// Chemins qui ne correspondent réellement à aucune route.
const CHEMINS_INCONNUS = [
  '/chemin-invente',
  '/patients/42/dossier-secret'
]

// ------------------------------------------------- coherence de la table

await verifier('SCRUM-532 aucun nom de route n est declare deux fois', () => {
  const noms = []
  const collecter = (liste) => {
    for (const route of liste) {
      if (route.name) noms.push(route.name)
      if (route.children) collecter(route.children)
    }
  }
  collecter(routes)

  const doublons = noms.filter((n, i) => noms.indexOf(n) !== i)
  assert.deepEqual(doublons, [], `noms declares plusieurs fois : ${doublons.join(', ')}`)
})

await verifier('SCRUM-532 la table declare une route attrape-tout', () => {
  const attrapeTout = routes.filter((r) => r.path.includes(':pathMatch'))
  assert.equal(attrapeTout.length, 1, 'il doit exister exactement une route attrape-tout')
})

await verifier('SCRUM-532 l attrape-tout est declare en dernier', () => {
  // Declare avant les autres, il les masquerait toutes.
  const dernier = routes[routes.length - 1]
  assert.ok(dernier.path.includes(':pathMatch'), 'l attrape-tout doit etre la derniere route')
})

// ------------------------------------------- visiteur non authentifie

await verifier('SCRUM-532 non authentifie : les routes privees renvoient vers login', async () => {
  deconnecter()

  for (const chemin of CHEMINS_PRIVES) {
    const arrivee = await naviguer(chemin)
    assert.equal(arrivee.name, 'login', `${chemin} devrait renvoyer vers login`)
    assert.equal(
      arrivee.query.redirect,
      chemin,
      `${chemin} devrait etre memorise dans ?redirect`
    )
  }
})

await verifier('SCRUM-532 non authentifie : les routes publiques restent accessibles', async () => {
  deconnecter()

  for (const chemin of CHEMINS_PUBLICS) {
    const arrivee = await naviguer(chemin)
    assert.equal(arrivee.path, chemin, `${chemin} devrait rester accessible`)
  }
})

/**
 * Le trou corrige par SCRUM-532 : sans route attrape-tout, une URL inconnue
 * ne correspondait a aucun enregistrement, requiresAuth valait undefined et
 * la garde laissait passer. Le visiteur restait dans l'application.
 */
await verifier('SCRUM-532 non authentifie : un chemin inconnu renvoie vers login', async () => {
  deconnecter()

  for (const chemin of CHEMINS_INCONNUS) {
    const arrivee = await naviguer(chemin)
    assert.equal(
      arrivee.name,
      'login',
      `${chemin} ne doit pas rester accessible a un visiteur non authentifie (arrive sur ${arrivee.fullPath})`
    )
  }
})

await verifier('SCRUM-532 non authentifie : la racine mene a login', async () => {
  deconnecter()
  const arrivee = await naviguer('/')
  assert.equal(arrivee.name, 'login')
})

// ------------------------------------------------ utilisateur connecte

await verifier('SCRUM-532 connecte : les routes privees sont accessibles', async () => {
  connecter()

  for (const chemin of CHEMINS_PRIVES) {
    const arrivee = await naviguer(chemin)
    assert.equal(arrivee.path, chemin, `${chemin} devrait etre accessible une fois connecte`)
  }
})

await verifier('SCRUM-532 connecte : /login renvoie vers le tableau de bord', async () => {
  connecter()
  const arrivee = await naviguer('/login')
  assert.equal(arrivee.name, 'dashboard')
})

await verifier('SCRUM-532 connecte : un chemin inconnu mene au tableau de bord', async () => {
  connecter()

  for (const chemin of CHEMINS_INCONNUS) {
    const arrivee = await naviguer(chemin)
    assert.equal(
      arrivee.name,
      'dashboard',
      `${chemin} devrait mener au tableau de bord plutot qu'a une page vide`
    )
  }
})

// -------------------------------------------------- rendu attendu

await verifier('SCRUM-532 les pages privees sont rendues dans MainLayout', async () => {
  connecter()

  for (const chemin of CHEMINS_PRIVES) {
    const arrivee = await naviguer(chemin)
    assert.ok(
      arrivee.matched.length >= 2,
      `${chemin} devrait etre rendu dans le layout (records : ${arrivee.matched.length})`
    )
  }
})

await verifier('SCRUM-532 toute route privee porte bien requiresAuth', async () => {
  connecter()

  for (const chemin of CHEMINS_PRIVES) {
    const arrivee = await naviguer(chemin)
    assert.equal(
      arrivee.meta.requiresAuth,
      true,
      `${chemin} devrait heriter de requiresAuth`
    )
  }
})

console.log(cas.map((c, i) => `  OK ${String(i + 1).padStart(2)}. ${c}`).join('\n'))
console.log(`\n${cas.length}/${cas.length} verifications passees`)
