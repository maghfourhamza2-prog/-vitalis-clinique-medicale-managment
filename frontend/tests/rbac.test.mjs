// SCRUM-533 - Affichage selon le role.
//
//   npm run test:rbac
//
// Deux sujets :
//   1. le miroir RBAC de src/rbac.js ne doit pas deriver de l'enum PHP ;
//   2. la barre laterale ne doit proposer que ce que le role permet et ce que
//      le routeur dessert.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

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

const { PERMISSIONS_PAR_ROLE, ROLES, permissionsDuRole, peut, roleCourant } =
  await import('../src/rbac.js')
const { ENTREES_NAVIGATION, entreesVisibles, cheminsExistants } =
  await import('../src/navigation.js')
const { routes } = await import('../src/router/routes.js')
const { saveSession, clearSession } = await import('../src/auth.js')

const cas = []
const verifier = (nom, fn) => { fn(); cas.push(nom) }

const ici = dirname(fileURLToPath(import.meta.url))
const CHEMIN_ROLE_PHP = resolve(ici, '../../backend/app/Enums/Role.php')
const CHEMIN_PERMISSION_PHP = resolve(ici, '../../backend/app/Enums/Permission.php')

/**
 * Valeur reelle de chaque constante de l'enum Permission.
 *
 * SCRUM-605 - Cette table etait auparavant deduite du NOM de la constante
 * (PATIENTS_READ -> patients.read). La deduction s'est cassee des qu'une
 * valeur a cesse de suivre le nom : RENDEZ_VOUS_READ vaut 'rendez-vous.read',
 * avec un tiret. On lit donc la valeur declaree, seule source fiable.
 *
 * @returns {Record<string, string>}
 */
const lireValeursPermissions = () => {
  const source = readFileSync(CHEMIN_PERMISSION_PHP, 'utf8')
  const valeurs = {}

  for (const ligne of source.split('\n')) {
    const declaration = ligne.match(/case\s+([A-Z_]+)\s*=\s*'([^']+)'\s*;/)
    if (declaration) {
      valeurs[declaration[1]] = declaration[2]
    }
  }

  return valeurs
}

/**
 * Lit la matrice role -> permissions directement dans l'enum PHP.
 *
 * Le but n'est pas d'analyser du PHP en general, mais de detecter une
 * divergence : si le backend gagne ou perd une permission, la copie du
 * frontend doit suivre.
 *
 * @returns {Record<string, string[]>}
 */
const lireMatricePhp = () => {
  const valeurs = lireValeursPermissions()
  const source = readFileSync(CHEMIN_ROLE_PHP, 'utf8')
  const corps = source.slice(source.indexOf('public function permissions'))

  const matrice = {}
  let roleCourantPhp = null

  for (const ligne of corps.split('\n')) {
    const debutRole = ligne.match(/self::([A-Z]+)\s*=>\s*\[/)
    if (debutRole) {
      roleCourantPhp = debutRole[1].toLowerCase()
      matrice[roleCourantPhp] = []
      continue
    }

    const reference = ligne.match(/Permission::([A-Z_]+)\s*,/)
    if (reference && roleCourantPhp) {
      const valeur = valeurs[reference[1]]

      if (valeur === undefined) {
        throw new Error(`Permission::${reference[1]} n'existe pas dans Permission.php`)
      }

      matrice[roleCourantPhp].push(valeur)
    }

    if (ligne.includes('};')) break
  }

  return matrice
}

const trier = (liste) => [...liste].sort()

// ------------------------------------------ le miroir ne doit pas deriver

verifier('SCRUM-533 la matrice PHP est lisible (le test lui-meme est valide)', () => {
  const php = lireMatricePhp()

  assert.equal(Object.keys(php).length, 5, `5 roles attendus, lus : ${Object.keys(php).join(', ')}`)
  assert.ok(php.administrateur.length > 25, 'l administrateur doit avoir toutes les permissions')
  assert.ok(php.patient.includes('consultations.read'))
})

verifier('SCRUM-533 le miroir frontend declare exactement les memes roles', () => {
  assert.deepEqual(
    trier(Object.keys(PERMISSIONS_PAR_ROLE)),
    trier(Object.keys(lireMatricePhp()))
  )
})

verifier('SCRUM-533 le miroir frontend correspond a l enum PHP, role par role', () => {
  const php = lireMatricePhp()

  for (const [role, attendues] of Object.entries(php)) {
    assert.deepEqual(
      trier(PERMISSIONS_PAR_ROLE[role]),
      trier(attendues),
      `divergence pour le role ${role} : corrigez src/rbac.js, pas le backend`
    )
  }
})

verifier('SCRUM-533 aucune permission inventee cote frontend', () => {
  const php = lireMatricePhp()
  const connues = new Set(Object.values(php).flat())

  for (const [role, liste] of Object.entries(PERMISSIONS_PAR_ROLE)) {
    for (const permission of liste) {
      assert.ok(
        connues.has(permission),
        `${permission} (role ${role}) n existe pas cote backend`
      )
    }
  }
})

verifier('SCRUM-533 les entrees de navigation n utilisent que des permissions connues', () => {
  const connues = new Set(Object.values(lireMatricePhp()).flat())

  for (const entree of ENTREES_NAVIGATION) {
    if (entree.permission === null) continue
    assert.ok(
      connues.has(entree.permission),
      `l entree "${entree.libelle}" reference ${entree.permission}, inconnue du backend`
    )
  }
})

verifier('SCRUM-533 un role inconnu ne donne aucune permission', () => {
  assert.deepEqual(permissionsDuRole('role-invente'), [])
  assert.deepEqual(permissionsDuRole(null), [])
  assert.deepEqual(permissionsDuRole(undefined), [])
})

// --------------------------------------------- navigation selon le role

const connecterAvecRole = (role) => {
  clearSession()
  saveSession({ token: 'jeton-de-test', user: { id: 1, name: 'Test', role }, remember: true })
}

const libelles = () => entreesVisibles().map((e) => e.libelle)

verifier('SCRUM-533 toutes les entrees de navigation pointent vers une route existante', () => {
  const disponibles = cheminsExistants(routes)

  for (const entree of ENTREES_NAVIGATION) {
    assert.ok(disponibles.has(entree.to), `${entree.to} est propose mais n a pas de route`)
  }
})

verifier('SCRUM-533 la navigation suit exactement les permissions de chaque role', () => {
  const disponibles = cheminsExistants(routes)

  for (const role of Object.values(ROLES)) {
    connecterAvecRole(role)

    const attendues = ENTREES_NAVIGATION
      .filter((entree) =>
        disponibles.has(entree.to) &&
        (entree.permission === null || PERMISSIONS_PAR_ROLE[role].includes(entree.permission))
      )
      .map((entree) => entree.libelle)

    assert.deepEqual(libelles(), attendues, `role ${role}`)
  }
})

verifier('SCRUM-533 seuls les administrateurs voient la gestion des utilisateurs', () => {
  connecterAvecRole(ROLES.ADMINISTRATEUR)
  assert.ok(libelles().includes('Utilisateurs'))

  for (const role of [ROLES.MEDECIN, ROLES.SECRETAIRE, ROLES.INFIRMIER, ROLES.PATIENT]) {
    connecterAvecRole(role)
    assert.equal(libelles().includes('Utilisateurs'), false, `role ${role}`)
  }
})

verifier('SCRUM-533 patient : aucun acces au registre des patients ni aux utilisateurs', () => {
  connecterAvecRole(ROLES.PATIENT)
  assert.equal(libelles().includes('Patients'), false)
  assert.equal(libelles().includes('Utilisateurs'), false)
  assert.equal(peut('patients.read'), false)
  assert.equal(peut('roles.manage'), false)
})

verifier('SCRUM-533 session absente : aucune entree privilegiee', () => {
  clearSession()

  assert.equal(roleCourant(), null)
  assert.deepEqual(libelles(), ['Tableau de bord'])
})

verifier('SCRUM-533 role bricole dans le stockage : aucune entree privilegiee', () => {
  // Le frontend ne protege rien : on verifie seulement qu'un role inconnu ne
  // fait pas apparaitre de menu au hasard. Un role valide force par
  // l'utilisateur ferait apparaitre des liens, et le backend repondrait 403.
  connecterAvecRole('super-admin')

  assert.deepEqual(libelles(), ['Tableau de bord'])
})

console.log(cas.map((c, i) => `  OK ${String(i + 1).padStart(2)}. ${c}`).join('\n'))
console.log(`\n${cas.length}/${cas.length} verifications passees`)
