// SCRUM-532 - Table des routes, isolee de la creation du routeur.
//
// Elle vivait dans index.js, aux cotes de createRouter() et de createWebHistory().
// Ces deux appels exigent un navigateur : la table n'etait donc verifiable
// qu'a la main, et les tests se rabattaient sur des objets de route
// fabriques pour l'occasion. Ils validaient la garde, jamais la table qu'elle
// protege - c'est exactement la ou se trouvait le trou corrige par ce ticket.
//
// Les composants sont charges paresseusement : la table reste ainsi
// importable sans compiler de fichier .vue, donc verifiable hors navigateur.

// SCRUM-13 - Chaque route declare si elle est protegee via meta.requiresAuth.
export const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { requiresAuth: false, hideAiDrawer: true }
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('../views/ResetPasswordView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('../views/ForgotPasswordView.vue'),
    meta: { requiresAuth: false }
  },

  // Espace authentifie. requiresAuth est porte par le parent : vue-router
  // fusionne le meta de tous les enregistrements traverses, chaque enfant en
  // herite donc sans avoir a le repeter.
  //
  // SCRUM-535 - meta.permission complete requiresAuth. Jusqu'ici toute route
  // privee se contentait d'exiger un compte : n'importe quel role atteignait
  // donc /utilisateurs ou /patients en tapant l'URL. Masquer le lien
  // (SCRUM-533) et les boutons (SCRUM-534) ne change rien a une URL saisie a
  // la main.
  //
  // Le tableau de bord n'en porte pas volontairement : DashboardController
  // choisit son contenu selon le role, tout compte authentifie peut l'ouvrir.
  //
  // Rappel : ceci reste de l'affichage. Le backend refuse l'operation par un
  // 403 quoi qu'il arrive ; on evite seulement d'ouvrir un ecran vide ou
  // trompeur.
  //
  // SCRUM-532 : /dashboard etait declare deux fois, ici et une seconde fois
  // hors de ce layout, sous le meme nom. C'est l'enfant qui gagnait, la
  // declaration isolee ne servait donc a rien - mais elle laissait croire
  // qu'il existait un tableau de bord accessible sans le layout.
  {
    path: '/',
    component: () => import('../layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../views/Dashboard/DashboardView.vue')
      },
      {
        path: 'patients',
        name: 'patients',
        component: () => import('../views/Patients/PatientsView.vue'),
        meta: { permission: 'patients.read' }
      },{
        path: 'rendez-vous',
        name: 'rendez-vous',
        component: () => import('../views/RendezVous/RendezVous.vue'),
        meta: { permission: 'rendez-vous.read' }
      },
      {
        path: 'consultations',
        name: 'consultations',
        component: () => import('../views/Consultations/ConsultationsView.vue'),
        meta: { permission: 'consultations.read' }
      },
      {
        path: 'ordonnances',
        name: 'ordonnances',
        component: () => import('../views/Ordonnances/OrdonnancesView.vue'),
        meta: { permission: 'ordonnances.read' }
      },
      
      {
        path: 'utilisateurs',
        name: 'utilisateurs',
        component: () => import('../views/Utilisateur/UtilisateursView.vue'),
        meta: { permission: 'roles.manage' }
      },
      {
        path: 'examens',
        name: 'examens',
        component: () => import('../views/Examens/ExamensView.vue'),
        meta: { permission: 'examens.read' }
      },
      {
        path: 'hospitalisations',
        name: 'hospitalisations',
        component: () => import('../views/Hospitalisations/HospitalisationsView.vue'),
        meta: { permission: 'hospitalisations.read' }
      },
      {
        path: 'facturation',
        name: 'facturation',
        component: () => import('../views/Facturation/FacturationView.vue'),
        meta: { permission: 'factures.read' }
      },
      {
        path: 'parametres',
        name: 'parametres',
        component: () => import('../views/Parametres/ParametresView.vue'),
        meta: { permission: 'roles.manage' }
      }
    ]
  },

  // SCRUM-532 - Tout chemin non declare.
  //
  // Sans cette route, une URL inconnue ne correspondait a aucun
  // enregistrement : to.meta etait vide, requiresAuth valait undefined et la
  // garde laissait passer. Un visiteur non authentifie qui saisissait
  // n'importe quelle adresse restait donc dans l'application au lieu d'etre
  // renvoye vers la connexion.
  //
  // Le cas n'avait rien de theorique : la barre laterale de MainLayout pointe
  // vers /rendez-vous, /consultations, /examens, /hospitalisations et
  // /facturation, cinq chemins qui ne correspondent a aucune route.
  //
  // La redirection suffit a fermer le trou : la garde s'applique ensuite a
  // /dashboard, qui exige une authentification. Un visiteur non authentifie
  // part vers /login, un utilisateur connecte atterrit sur son tableau de
  // bord plutot que sur une page vide.
  {
    path: '/:pathMatch(.*)*',
    name: 'non-trouve',
    redirect: '/dashboard'
  }
]

export default routes
