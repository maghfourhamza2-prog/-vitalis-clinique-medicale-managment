// SCRUM-13 - Etat d'authentification centralise.
// SCRUM-15 - Gestion complete de la session utilisateur.
//
// Le jeton Bearer n'est jamais persiste dans localStorage : un stockage
// persistant augmente la fenetre d'exploitation en cas de XSS ou de poste
// partage. La session disparait donc a la fermeture de l'onglet/navigateur.

const CLE_TOKEN = 'token'
const CLE_USER = 'user'

const stockages = () => [sessionStorage]

/**
 * Retourne le token de l'utilisateur connecte, ou null.
 */
export function getToken() {
  for (const stockage of stockages()) {
    const token = stockage.getItem(CLE_TOKEN)
    if (token) {
      return token
    }
  }
  return null
}

/**
 * Retourne l'utilisateur connecte, ou null.
 *
 * Une donnee illisible (stockage corrompu, format modifie entre deux versions)
 * est traitee comme une absence d'utilisateur plutot que de faire planter la
 * page : la session est alors effacee pour ne pas garder un etat incoherent.
 */
export function getUser() {
  for (const stockage of stockages()) {
    const brut = stockage.getItem(CLE_USER)

    if (brut) {
      try {
        return JSON.parse(brut)
      } catch {
        clearSession()
        return null
      }
    }
  }
  return null
}

/**
 * Indique si un utilisateur est actuellement connecte.
 */
export function isAuthenticated() {
  return getToken() !== null
}

/**
 * Enregistre la session apres une connexion reussie.
 *
 * @param {{ token: string, user: object }} session
 */
export function saveSession({ token, user }) {
  clearSession()

  sessionStorage.setItem(CLE_TOKEN, token)
  sessionStorage.setItem(CLE_USER, JSON.stringify(user))
}

/**
 * Efface la session de l'onglet et nettoie aussi les anciennes versions
 * eventuellement restees dans localStorage.
 *
 * Utilise a la deconnexion et lorsque l'API repond 401 (session expiree ou
 * token revoque). Nettoyer les deux evite de laisser des donnees utilisateur
 * obsoletes derriere soi.
 */
export function clearSession() {
  sessionStorage.removeItem(CLE_TOKEN)
  sessionStorage.removeItem(CLE_USER)

  // Migration de securite : purge les jetons persistants crees par les
  // versions precedentes de VITALIS.
  localStorage.removeItem(CLE_TOKEN)
  localStorage.removeItem(CLE_USER)
}

/**
 * SCRUM-531 - Ferme la session : revocation cote serveur PUIS effacement local.
 *
 * Se deconnecter en vidant seulement le stockage ne suffit pas. Le jeton
 * Sanctum reste valide cote serveur jusqu'a son expiration (12 h, SCRUM-526) :
 * un jeton recupere apres coup sur un poste partage de la clinique - console
 * du navigateur, sauvegarde de profil, extension - continuerait d'ouvrir
 * l'API. C'est justement ce que fait POST /api/logout, qui supprime le jeton
 * courant.
 *
 * L'effacement local a lieu dans tous les cas, y compris si l'appel echoue
 * (reseau coupe, jeton deja expire). Un serveur injoignable ne doit pas
 * laisser l'utilisateur connecte sur la machine : rester connecte localement
 * serait le pire des deux mondes.
 *
 * La revocation est passee en parametre plutot qu'importee : auth.js est
 * importe par api.js, l'inverse creerait un cycle. Cela rend aussi la
 * sequence verifiable sans navigateur.
 *
 * @param {() => Promise<unknown>} revoquer Appel HTTP de revocation.
 */
export async function closeSession(revoquer) {
  try {
    if (typeof revoquer === 'function') {
      await revoquer()
    }
  } catch {
    // Le jeton est peut-etre deja invalide, ou le serveur injoignable : il
    // n'y a rien a faire de plus, et surtout rien qui justifie de conserver
    // la session locale.
  } finally {
    clearSession()
  }
}
