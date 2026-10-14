// SCRUM-534 - Masquage des actions non autorisees.
//
//   npm run test:actions
//
// On verifie la matrice action -> permission et son resultat par role. Le
// rendu des composants n'est pas monte (pas de framework de test de
// composants dans le projet) : les templates se contentent d'appeler
// peutAction(), c'est donc cette decision qui est verifiee ici.
import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve, join } from 'node:path'

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

const { PERMISSION_PAR_ACTION, peutAction, peutAuMoinsUneAction } =
  await import('../src/actions.js')
const { PERMISSIONS_PAR_ROLE, ROLES } = await import('../src/rbac.js')
const { saveSession, clearSession } = await import('../src/auth.js')

const cas = []
const verifier = (nom, fn) => { fn(); cas.push(nom) }

const connecterAvecRole = (role) => {
  clearSession()
  saveSession({ token: 'jeton-de-test', user: { id: 1, name: 'Test', role }, remember: true })
}

const ici = dirname(fileURLToPath(import.meta.url))
const DOSSIER_VUES = resolve(ici, '../src/views')

const ACTIONS_RAPIDES = [
  'tableauBord.nouveauRendezVous',
  'tableauBord.nouveauPatient',
  'tableauBord.consultationRapide',
  'tableauBord.demandeExamen'
]

// ------------------------------------------------ coherence de la matrice

verifier('SCRUM-534 aucune action ne reference une permission inventee', () => {
  const connues = new Set(Object.values(PERMISSIONS_PAR_ROLE).flat())

  for (const [action, permission] of Object.entries(PERMISSION_PAR_ACTION)) {
    assert.ok(
      connues.has(permission),
      `l action ${action} reference ${permission}, absente de la matrice backend`
    )
  }
})

verifier('SCRUM-534 une action inconnue est masquee par defaut', () => {
  connecterAvecRole(ROLES.ADMINISTRATEUR)

  assert.equal(peutAction('action.inexistante'), false)
  assert.equal(peutAuMoinsUneAction(['action.inexistante']), false)
})

// ------------------------------------------------------- par role

verifier('SCRUM-534 administrateur : toutes les actions sont proposees', () => {
  connecterAvecRole(ROLES.ADMINISTRATEUR)

  for (const action of Object.keys(PERMISSION_PAR_ACTION)) {
    assert.equal(peutAction(action), true, `l administrateur devrait voir ${action}`)
  }
})

/**
 * Le cas le plus visible du ticket : un patient se voyait proposer "Nouveau
 * patient", "Consultation rapide", "Ajouter un utilisateur", "Modifier",
 * "Supprimer" - autant d'operations qui lui repondent 403.
 */
verifier('SCRUM-605 patient : seule la prise de rendez-vous lui est proposee', () => {
  connecterAvecRole(ROLES.PATIENT)

  // SCRUM-49 accorde RENDEZ_VOUS_CREATE au patient : la prise de rendez-vous
  // en ligne est la seule action qu'il puisse declencher. Le backend la
  // restreint a son propre dossier (RendezVousAccesTest).
  const actionsAutorisees = new Set([
    'tableauBord.nouveauRendezVous',
    'rendezVous.creer'
  ])

  for (const action of actionsAutorisees) {
    assert.equal(peutAction(action), true, `le patient devrait voir ${action}`)
  }

  for (const action of Object.keys(PERMISSION_PAR_ACTION)) {
    if (actionsAutorisees.has(action)) continue

    assert.equal(peutAction(action), false, `le patient ne devrait pas voir ${action}`)
  }

  // Le patient ne cree, ne modifie ni ne supprime aucun dossier medical.
  for (const action of ['patients.creer', 'patients.modifier', 'patients.supprimer',
    'tableauBord.consultationRapide', 'tableauBord.demandeExamen',
    'utilisateurs.ajouter', 'utilisateurs.modifier', 'utilisateurs.supprimer']) {
    assert.equal(peutAction(action), false, action)
  }
})

verifier('SCRUM-534 seul l administrateur gere les comptes utilisateurs', () => {
  const actionsComptes = [
    'utilisateurs.ajouter',
    'utilisateurs.consulter',
    'utilisateurs.modifier',
    'utilisateurs.supprimer'
  ]

  for (const role of [ROLES.MEDECIN, ROLES.SECRETAIRE, ROLES.INFIRMIER, ROLES.PATIENT]) {
    connecterAvecRole(role)

    for (const action of actionsComptes) {
      assert.equal(peutAction(action), false, `${role} ne doit pas voir ${action}`)
    }
  }

  connecterAvecRole(ROLES.ADMINISTRATEUR)
  for (const action of actionsComptes) {
    assert.equal(peutAction(action), true)
  }
})

verifier('SCRUM-534 seul l administrateur supprime un dossier patient', () => {
  for (const role of [ROLES.MEDECIN, ROLES.SECRETAIRE, ROLES.INFIRMIER, ROLES.PATIENT]) {
    connecterAvecRole(role)
    assert.equal(peutAction('patients.supprimer'), false, `role ${role}`)
  }

  connecterAvecRole(ROLES.ADMINISTRATEUR)
  assert.equal(peutAction('patients.supprimer'), true)
})

verifier('SCRUM-534 le personnel cree et modifie un dossier patient', () => {
  for (const role of [ROLES.MEDECIN, ROLES.SECRETAIRE, ROLES.INFIRMIER]) {
    connecterAvecRole(role)
    assert.equal(peutAction('patients.creer'), true, `role ${role}`)
    assert.equal(peutAction('patients.modifier'), true, `role ${role}`)
  }
})

verifier('SCRUM-605 le panneau disparait pour un role sans aucune action', () => {
  // Sans session, aucune action : le panneau ne doit pas s'afficher vide.
  clearSession()
  assert.equal(peutAuMoinsUneAction(ACTIONS_RAPIDES), false)
})

verifier('SCRUM-534 activite rapide : chaque role ne voit que ce qui le concerne', () => {
  // La secretaire n'a acces ni aux consultations ni aux examens (SCRUM-524).
  connecterAvecRole(ROLES.SECRETAIRE)
  assert.equal(peutAction('tableauBord.nouveauPatient'), true)
  assert.equal(peutAction('tableauBord.consultationRapide'), false)
  assert.equal(peutAction('tableauBord.demandeExamen'), false)

  // L'infirmier saisit les examens mais ne cree pas de consultation.
  connecterAvecRole(ROLES.INFIRMIER)
  assert.equal(peutAction('tableauBord.demandeExamen'), true)
  assert.equal(peutAction('tableauBord.consultationRapide'), false)

  // Le medecin cree des consultations, mais ne saisit pas les examens.
  connecterAvecRole(ROLES.MEDECIN)
  assert.equal(peutAction('tableauBord.consultationRapide'), true)
  assert.equal(peutAction('tableauBord.demandeExamen'), false)
})

verifier('SCRUM-534 session absente : aucune action n est proposee', () => {
  clearSession()

  for (const action of Object.keys(PERMISSION_PAR_ACTION)) {
    assert.equal(peutAction(action), false, `sans session, ${action} doit rester masquee`)
  }
})

// ------------------------------- aucun bouton d action laisse sans controle

/**
 * Garde-fou : un bouton d'action ajoute plus tard sans v-if reapparaitrait
 * pour tout le monde. On verifie que les boutons des ecrans concernes
 * portent bien une condition.
 */
verifier('SCRUM-534 les boutons d action des ecrans portent une condition', () => {
  const fichiers = []
  const parcourir = (dossier) => {
    for (const entree of readdirSync(dossier)) {
      const chemin = join(dossier, entree)
      if (statSync(chemin).isDirectory()) parcourir(chemin)
      else if (entree.endsWith('.vue')) fichiers.push(chemin)
    }
  }
  parcourir(DOSSIER_VUES)

  // Ecrans hors session : la connexion et la reinitialisation de mot de passe
  // s'adressent par definition a un visiteur sans role.
  const horsSession = ['LoginView', 'ForgotPasswordView', 'ResetPasswordView']

  const libellesAction = /Ajouter|Supprimer|Modifier|Nouveau|Nouvelle|Enregistrer/i

  for (const fichier of fichiers) {
    if (horsSession.some((nom) => fichier.includes(nom))) continue

    const source = readFileSync(fichier, 'utf8')
    const template = source.slice(0, source.indexOf('<script'))

    for (const bouton of template.match(/<button[\s\S]*?<\/button>/g) ?? []) {
      if (!libellesAction.test(bouton)) continue

      assert.ok(
        bouton.includes('v-if') || bouton.includes('peutAction'),
        `bouton d action sans condition dans ${fichier} :\n${bouton.trim().slice(0, 120)}`
      )
    }
  }
})

console.log(cas.map((c, i) => `  OK ${String(i + 1).padStart(2)}. ${c}`).join('\n'))
console.log(`\n${cas.length}/${cas.length} verifications passees`)
