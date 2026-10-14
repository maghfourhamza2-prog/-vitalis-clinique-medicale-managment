// Verification executable de la garde de navigation (SCRUM-13), du
// comportement de redirection (SCRUM-14) et de la gestion de session (SCRUM-15).
//   docker compose exec frontend npm run test:guards
import assert from 'node:assert/strict'

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

const { resolveNavigation, resolveRedirection } = await import('../src/router/guards.js')
const { isAuthenticated, getToken, getUser, saveSession, clearSession, closeSession } =
  await import('../src/auth.js')

const dashboard = { name: 'dashboard', path: '/dashboard', fullPath: '/dashboard', meta: { requiresAuth: true } }
const login = { name: 'login', path: '/login', fullPath: '/login', meta: { requiresAuth: false } }

const cas = []
const verifier = async (nom, fn) => { await fn(); cas.push(nom) }

// ---------------------------------------------------------------- SCRUM-13
localStorage.clear(); sessionStorage.clear()
await verifier('SCRUM-13 deconnecte : /dashboard est bloque', () => {
  assert.equal(isAuthenticated(), false)
  assert.equal(resolveNavigation(dashboard).name, 'login')
})

await verifier('SCRUM-13 deconnecte : /login reste accessible (aucune boucle)', () => {
  assert.equal(resolveNavigation(login), true)
})

localStorage.setItem('token', 'ancien-jeton-persistant')
await verifier('SECURITY un ancien token localStorage n authentifie plus', () => {
  assert.equal(isAuthenticated(), false)
  assert.equal(resolveNavigation(dashboard).name, 'login')
})

localStorage.clear()
sessionStorage.setItem('token', 'jeton-factice-session')
await verifier('SCRUM-13 connecte via sessionStorage : /dashboard autorise', () => {
  assert.equal(getToken(), 'jeton-factice-session')
  assert.equal(resolveNavigation(dashboard), true)
})

sessionStorage.clear()
await verifier('SCRUM-13 token supprime : /dashboard de nouveau bloque', () => {
  assert.equal(resolveNavigation(dashboard).name, 'login')
})

await verifier('SCRUM-13 route sans meta : autorisee', () => {
  assert.equal(resolveNavigation({ path: '/' }), true)
})

// ---------------------------------------------------------------- SCRUM-14
localStorage.clear(); sessionStorage.clear()
await verifier('SCRUM-14 la destination voulue est memorisee dans ?redirect', () => {
  const decision = resolveNavigation({ ...dashboard, fullPath: '/dashboard?onglet=stats' })
  assert.deepEqual(decision, {
    name: 'login',
    query: { redirect: '/dashboard?onglet=stats' }
  })
})

sessionStorage.setItem('token', 'jeton-factice-session')
await verifier('SCRUM-14 connecte : /login renvoie vers /dashboard', () => {
  assert.deepEqual(resolveNavigation(login), { name: 'dashboard' })
})

await verifier('SCRUM-14 apres connexion : retour sur la page demandee', () => {
  assert.equal(resolveRedirection('/dashboard?onglet=stats'), '/dashboard?onglet=stats')
})

await verifier('SCRUM-14 sans ?redirect : destination par defaut /dashboard', () => {
  assert.equal(resolveRedirection(undefined), '/dashboard')
})

await verifier('SCRUM-14 ?redirect=/login est ignore (pas de boucle)', () => {
  assert.equal(resolveRedirection('/login'), '/dashboard')
  assert.equal(resolveRedirection('/login?redirect=/login'), '/dashboard')
})

await verifier('SCRUM-14 une redirection externe est refusee', () => {
  assert.equal(resolveRedirection('https://site-externe.example'), '/dashboard')
  assert.equal(resolveRedirection('//site-externe.example'), '/dashboard')
})

await verifier('SCRUM-14 une valeur non textuelle est refusee', () => {
  assert.equal(resolveRedirection(['/a', '/b']), '/dashboard')
  assert.equal(resolveRedirection(null), '/dashboard')
})

// ---------------------------------------------------------------- SCRUM-15
const utilisateur = { id: 1, name: 'Sara Bennani', email: 'sara@example.com' }

localStorage.clear(); sessionStorage.clear()
await verifier('SECURITY la connexion reste uniquement dans sessionStorage', () => {
  saveSession({ token: 'jeton-a', user: utilisateur })
  assert.equal(localStorage.getItem('token'), null)
  assert.equal(sessionStorage.getItem('token'), 'jeton-a')
  assert.deepEqual(getUser(), utilisateur)
  assert.equal(isAuthenticated(), true)
})

await verifier('SECURITY le parametre legacy remember ne rend plus le jeton persistant', () => {
  clearSession()
  saveSession({ token: 'jeton-b', user: utilisateur, remember: true })
  assert.equal(sessionStorage.getItem('token'), 'jeton-b')
  assert.equal(localStorage.getItem('token'), null)
  assert.equal(getToken(), 'jeton-b')
})

await verifier('SCRUM-15 une nouvelle connexion ne laisse pas l ancien token', () => {
  clearSession()
  saveSession({ token: 'ancien', user: utilisateur, remember: true })
  saveSession({ token: 'nouveau', user: utilisateur, remember: false })
  assert.equal(localStorage.getItem('token'), null)
  assert.equal(getToken(), 'nouveau')
})

await verifier('SCRUM-15 etat restaure apres rafraichissement', () => {
  clearSession()
  saveSession({ token: 'jeton-c', user: utilisateur, remember: true })
  // Un rafraichissement recharge les modules mais pas le stockage : on relit.
  assert.equal(isAuthenticated(), true)
  assert.equal(getUser().name, 'Sara Bennani')
  assert.equal(resolveNavigation(dashboard), true)
})

await verifier('SCRUM-15 deconnexion : les deux stockages sont vides', () => {
  clearSession()
  saveSession({ token: 'jeton-d', user: utilisateur, remember: true })
  sessionStorage.setItem('user', JSON.stringify(utilisateur)) // residu eventuel
  clearSession()
  assert.equal(getToken(), null)
  assert.equal(getUser(), null)
  assert.equal(localStorage.getItem('user'), null)
  assert.equal(sessionStorage.getItem('user'), null)
})

await verifier('SCRUM-15 apres deconnexion : /dashboard est de nouveau bloque', () => {
  assert.equal(resolveNavigation(dashboard).name, 'login')
})

await verifier('SCRUM-15 donnee utilisateur illisible : session effacee, pas de plantage', () => {
  clearSession()
  sessionStorage.setItem('token', 'jeton-e')
  sessionStorage.setItem('user', '{ceci-n-est-pas-du-json')
  assert.equal(getUser(), null)
  assert.equal(getToken(), null)
})

// ---------------------------------------------------------------- SCRUM-531
// Redirection : contournement du controle par antislash.
//
// Le navigateur traite l'antislash comme un slash dans une URL. Une valeur
// commencant par "/" suivi d'antislashs passait donc l'ancien controle (elle
// commence par "/" et pas par "//") tout en etant resolue vers un autre site.
await verifier('SCRUM-531 redirection : l antislash ne contourne plus le controle', () => {
  assert.equal(resolveRedirection('/\\site-externe.example'), '/dashboard')
  assert.equal(resolveRedirection('/\\\\site-externe.example'), '/dashboard')
  assert.equal(resolveRedirection('/\\/site-externe.example'), '/dashboard')
  assert.equal(resolveRedirection('\\\\site-externe.example'), '/dashboard')
})

await verifier('SCRUM-531 redirection : un chemin interne reste accepte', () => {
  assert.equal(resolveRedirection('/patients'), '/patients')
  assert.equal(resolveRedirection('/dashboard?onglet=stats'), '/dashboard?onglet=stats')
})

// Invariant plutot que liste de cas : quelle que soit l'entree, la valeur
// renvoyee doit rester sur le site, verifie avec l'analyseur d'URL du
// navigateur lui-meme.
await verifier('SCRUM-531 redirection : aucune valeur renvoyee ne sort du site', () => {
  const hostile = [
    '/\\site-externe.example',
    '/\\\\site-externe.example',
    '/\\/site-externe.example',
    '\\\\site-externe.example',
    '//site-externe.example',
    'https://site-externe.example',
    'http:/' + '/site-externe.example',
    '/dashboard',
    '/patients'
  ]

  for (const valeur of hostile) {
    const resultat = resolveRedirection(valeur)
    const resolue = new URL(resultat, 'https://vitalis.example')

    assert.equal(
      resolue.origin,
      'https://vitalis.example',
      `resolveRedirection(${JSON.stringify(valeur)}) sort du site : ${resolue.href}`
    )
  }
})

// Fermeture de session : revocation cote serveur PUIS effacement local.
localStorage.clear(); sessionStorage.clear()

await verifier('SCRUM-531 deconnexion : le jeton est revoque cote serveur', async () => {
  saveSession({ token: 'jeton-a-revoquer', user: utilisateur, remember: true })

  let appele = false
  await closeSession(() => { appele = true; return Promise.resolve() })

  assert.equal(appele, true, 'la revocation cote serveur doit etre appelee')
  assert.equal(getToken(), null)
  assert.equal(getUser(), null)
})

await verifier('SCRUM-531 deconnexion : session effacee meme si la revocation echoue', async () => {
  saveSession({ token: 'jeton-b', user: utilisateur, remember: true })

  // Serveur injoignable ou jeton deja expire : rester connecte localement
  // serait le pire des deux mondes.
  await closeSession(() => Promise.reject(new Error('reseau indisponible')))

  assert.equal(getToken(), null)
  assert.equal(getUser(), null)
  assert.equal(isAuthenticated(), false)
})

await verifier('SCRUM-531 deconnexion : la revocation precede l effacement', async () => {
  saveSession({ token: 'jeton-c', user: utilisateur, remember: true })

  let jetonAuMomentDeLAppel = null
  await closeSession(() => {
    // L'intercepteur d'api.js lit le jeton pour construire l'en-tete
    // Authorization : efface trop tot, la requete partirait sans jeton et le
    // serveur ne revoquerait rien.
    jetonAuMomentDeLAppel = getToken()
    return Promise.resolve()
  })

  assert.equal(jetonAuMomentDeLAppel, 'jeton-c')
  assert.equal(getToken(), null)
})

await verifier('SCRUM-531 deconnexion : /dashboard est de nouveau bloque', () => {
  assert.equal(resolveNavigation(dashboard).name, 'login')
})

console.log(cas.map((c, i) => `  OK ${String(i + 1).padStart(2)}. ${c}`).join('\n'))
console.log(`\n${cas.length}/${cas.length} verifications passees`)
