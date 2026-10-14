<template>
  <div class="settings-page">

    <!-- HEADER -->
    <div class="page-header">
      <div class="page-title">
        <div class="page-title-icon">
          <i class="fi fi-rr-settings"></i>
        </div>
        <div>
          <h1>Paramètres</h1>
          <p>
            Gérez votre profil, la clinique, la sécurité
            et les préférences de VITALIS.
          </p>
        </div>
      </div>
    </div>

    <!-- SETTINGS CONTAINER -->
    <div class="settings-container">

      <!-- SIDEBAR SETTINGS -->
      <aside class="settings-sidebar">
        <div class="settings-sidebar-title">
          <span>PARAMÈTRES</span>
          <p>Configuration de VITALIS</p>
        </div>

        <nav class="settings-nav">
          <button
            v-for="item in menuItems"
            :key="item.id"
            class="settings-nav-item"
            :class="{ active: activeSection === item.id }"
            @click="activeSection = item.id"
          >
            <div class="settings-nav-icon">
              <i :class="item.icon"></i>
            </div>

            <div class="settings-nav-text">
              <strong>{{ item.label }}</strong>
              <span>{{ item.description }}</span>
            </div>

            <i class="fi fi-rr-angle-small-right nav-arrow"></i>
          </button>
        </nav>
      </aside>

      <!-- CONTENT -->
      <main class="settings-content">

        <!-- ==========================================
             PROFIL
        =========================================== -->
        <section v-if="activeSection === 'profil'">
          <div class="section-header">
            <div>
              <span class="section-label">COMPTE</span>
              <h2>Informations du profil</h2>
              <p>
                Gérez vos informations personnelles et
                vos coordonnées.
              </p>
            </div>
          </div>

          <div class="profile-card">
            <div class="profile-avatar">
              <img
                v-if="profilePhoto"
                :src="profilePhoto"
                alt="Photo de profil"
              />

              <span v-else>
                {{ userInitials }}
              </span>
            </div>

            <div class="profile-photo-info">
              <h3>Photo de profil</h3>
              <p>
                JPG ou PNG. Taille maximale recommandée : 2 Mo.
              </p>

              <div class="photo-actions">
                <label class="upload-btn">
                  <i class="fi fi-rr-camera"></i>
                  Modifier la photo

                  <input
                    type="file"
                    accept="image/png,image/jpeg"
                    @change="handlePhoto"
                  />
                </label>

                <button
                  v-if="profilePhoto"
                  class="remove-photo-btn"
                  @click="profilePhoto = null"
                >
                  Supprimer
                </button>
              </div>
            </div>
          </div>

          <form
            class="settings-form"
            @submit.prevent="saveProfile"
          >
            <div class="form-grid">

              <div class="form-group">
                <label>Prénom</label>

                <div class="input-icon">
                  <i class="fi fi-rr-user"></i>
                  <input
                    v-model="profile.prenom"
                    type="text"
                    placeholder="Votre prénom"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Nom</label>

                <div class="input-icon">
                  <i class="fi fi-rr-user"></i>
                  <input
                    v-model="profile.nom"
                    type="text"
                    placeholder="Votre nom"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Adresse e-mail</label>

                <div class="input-icon">
                  <i class="fi fi-rr-envelope"></i>
                  <input
                    v-model="profile.email"
                    type="email"
                    placeholder="exemple@email.com"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Téléphone</label>

                <div class="input-icon">
                  <i class="fi fi-rr-phone-call"></i>
                  <input
                    v-model="profile.telephone"
                    type="text"
                    placeholder="+212 6..."
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Fonction</label>

                <div class="input-icon">
                  <i class="fi fi-rr-briefcase"></i>
                  <input
                    v-model="profile.fonction"
                    type="text"
                    placeholder="Administrateur"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Langue principale</label>

                <select v-model="profile.langue">
                  <option value="fr">Français</option>
                  <option value="ar">العربية</option>
                  <option value="en">English</option>
                </select>
              </div>

            </div>

            <div class="form-actions">
              <button
                type="button"
                class="secondary-btn"
                @click="resetProfile"
              >
                Annuler
              </button>

              <button v-if="peutAction('parametres.modifier')" type="submit" class="primary-btn">
                <i class="fi fi-rr-disk"></i>
                Enregistrer les modifications
              </button>
            </div>
          </form>
        </section>

        <!-- ==========================================
             CLINIQUE
        =========================================== -->
        <section v-if="activeSection === 'clinique'">
          <div class="section-header">
            <div>
              <span class="section-label">ÉTABLISSEMENT</span>
              <h2>Informations de la clinique</h2>
              <p>
                Configurez les informations générales
                de votre établissement médical.
              </p>
            </div>
          </div>

          <div class="clinic-banner">
            <div class="clinic-logo">
              <i class="fi fi-rr-hospital"></i>
            </div>

            <div>
              <h3>{{ clinic.nom || 'VITALIS Clinique Médicale' }}</h3>
              <p>Configuration générale de l'établissement</p>
            </div>
          </div>

          <form
            class="settings-form"
            @submit.prevent="saveClinic"
          >
            <div class="form-grid">

              <div class="form-group full">
                <label>Nom de la clinique</label>

                <div class="input-icon">
                  <i class="fi fi-rr-hospital"></i>
                  <input
                    v-model="clinic.nom"
                    type="text"
                    placeholder="VITALIS Clinique Médicale"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Téléphone</label>

                <div class="input-icon">
                  <i class="fi fi-rr-phone-call"></i>
                  <input
                    v-model="clinic.telephone"
                    type="text"
                    placeholder="+212..."
                  />
                </div>
              </div>

              <div class="form-group">
                <label>E-mail</label>

                <div class="input-icon">
                  <i class="fi fi-rr-envelope"></i>
                  <input
                    v-model="clinic.email"
                    type="email"
                    placeholder="contact@vitalis.ma"
                  />
                </div>
              </div>

              <div class="form-group full">
                <label>Adresse</label>

                <div class="input-icon">
                  <i class="fi fi-rr-marker"></i>
                  <input
                    v-model="clinic.adresse"
                    type="text"
                    placeholder="Adresse de la clinique"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Ville</label>

                <input
                  v-model="clinic.ville"
                  type="text"
                  placeholder="Ville"
                />
              </div>

              <div class="form-group">
                <label>Pays</label>

                <select v-model="clinic.pays">
                  <option value="Maroc">Maroc</option>
                  <option value="France">France</option>
                  <option value="Espagne">Espagne</option>
                </select>
              </div>

              <div class="form-group">
                <label>Heure d'ouverture</label>

                <input
                  v-model="clinic.ouverture"
                  type="time"
                />
              </div>

              <div class="form-group">
                <label>Heure de fermeture</label>

                <input
                  v-model="clinic.fermeture"
                  type="time"
                />
              </div>

            </div>

            <div class="form-actions">
              <button v-if="peutAction('parametres.modifier')" type="submit" class="primary-btn">
                <i class="fi fi-rr-disk"></i>
                Enregistrer les modifications
              </button>
            </div>
          </form>
        </section>

        <!-- ==========================================
             SECURITE
        =========================================== -->
        <section v-if="activeSection === 'securite'">
          <div class="section-header">
            <div>
              <span class="section-label">SÉCURITÉ</span>
              <h2>Sécurité du compte</h2>
              <p>
                Gérez votre mot de passe et les paramètres
                de sécurité de votre compte.
              </p>
            </div>
          </div>

          <div class="security-info">
            <div class="security-info-icon">
              <i class="fi fi-rr-shield-check"></i>
            </div>

            <div>
              <strong>Votre compte est protégé</strong>
              <p>
                Utilisez un mot de passe fort et ne le
                partagez avec personne.
              </p>
            </div>

            <span class="secure-badge">
              <i class="fi fi-rr-check"></i>
              Sécurisé
            </span>
          </div>

          <div class="security-block">
            <div class="subsection-title">
              <div>
                <h3>Changer le mot de passe</h3>
                <p>
                  Mettez régulièrement à jour votre mot de passe.
                </p>
              </div>
            </div>

            <form
              class="settings-form no-border"
              @submit.prevent="changePassword"
            >
              <div class="form-group full">
                <label>Mot de passe actuel</label>

                <div class="password-input">
                  <i class="fi fi-rr-lock"></i>

                  <input
                    v-model="password.current"
                    :type="showCurrent ? 'text' : 'password'"
                    placeholder="••••••••"
                  />

                  <button
                    type="button"
                    @click="showCurrent = !showCurrent"
                  >
                    <i
                      :class="
                        showCurrent
                          ? 'fi fi-rr-eye-crossed'
                          : 'fi fi-rr-eye'
                      "
                    ></i>
                  </button>
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label>Nouveau mot de passe</label>

                  <div class="password-input">
                    <i class="fi fi-rr-lock"></i>

                    <input
                      v-model="password.newPassword"
                      :type="showNew ? 'text' : 'password'"
                      placeholder="••••••••"
                    />

                    <button
                      type="button"
                      @click="showNew = !showNew"
                    >
                      <i
                        :class="
                          showNew
                            ? 'fi fi-rr-eye-crossed'
                            : 'fi fi-rr-eye'
                        "
                      ></i>
                    </button>
                  </div>
                </div>

                <div class="form-group">
                  <label>Confirmer le mot de passe</label>

                  <div class="password-input">
                    <i class="fi fi-rr-lock"></i>

                    <input
                      v-model="password.confirm"
                      :type="showConfirm ? 'text' : 'password'"
                      placeholder="••••••••"
                    />

                    <button
                      type="button"
                      @click="showConfirm = !showConfirm"
                    >
                      <i
                        :class="
                          showConfirm
                            ? 'fi fi-rr-eye-crossed'
                            : 'fi fi-rr-eye'
                        "
                      ></i>
                    </button>
                  </div>
                </div>
              </div>

              <div class="password-rules">
                <span>Le mot de passe doit contenir :</span>

                <div class="rules-grid">
                  <p :class="{ valid: passwordLengthValid }">
                    <i class="fi fi-rr-check-circle"></i>
                    Au moins 8 caractères
                  </p>

                  <p :class="{ valid: passwordUppercaseValid }">
                    <i class="fi fi-rr-check-circle"></i>
                    Une lettre majuscule
                  </p>

                  <p :class="{ valid: passwordNumberValid }">
                    <i class="fi fi-rr-check-circle"></i>
                    Un chiffre
                  </p>

                  <p :class="{ valid: passwordMatchValid }">
                    <i class="fi fi-rr-check-circle"></i>
                    Mots de passe identiques
                  </p>
                </div>
              </div>

              <div class="form-actions">
                <button v-if="peutAction('parametres.modifier')" type="submit" class="primary-btn">
                  <i class="fi fi-rr-lock"></i>
                  Modifier le mot de passe
                </button>
              </div>
            </form>
          </div>
        </section>

        <!-- ==========================================
             NOTIFICATIONS
        =========================================== -->
        <section v-if="activeSection === 'notifications'">
          <div class="section-header">
            <div>
              <span class="section-label">NOTIFICATIONS</span>
              <h2>Préférences de notification</h2>
              <p>
                Choisissez les événements pour lesquels
                vous souhaitez être informé.
              </p>
            </div>
          </div>

          <div class="notification-list">

            <div class="notification-setting">
              <div class="setting-info">
                <div class="setting-icon blue">
                  <i class="fi fi-rr-calendar"></i>
                </div>

                <div>
                  <strong>Rendez-vous</strong>
                  <span>
                    Recevoir les notifications liées aux rendez-vous.
                  </span>
                </div>
              </div>

              <label class="switch">
                <input
                  v-model="notifications.rendezVous"
                  type="checkbox"
                />
                <span class="slider"></span>
              </label>
            </div>

            <div class="notification-setting">
              <div class="setting-info">
                <div class="setting-icon purple">
                  <i class="fi fi-rr-hospital"></i>
                </div>

                <div>
                  <strong>Hospitalisations</strong>
                  <span>
                    Alertes concernant les admissions et sorties.
                  </span>
                </div>
              </div>

              <label class="switch">
                <input
                  v-model="notifications.hospitalisations"
                  type="checkbox"
                />
                <span class="slider"></span>
              </label>
            </div>

            <div class="notification-setting">
              <div class="setting-info">
                <div class="setting-icon green">
                  <i class="fi fi-rr-receipt"></i>
                </div>

                <div>
                  <strong>Facturation</strong>
                  <span>
                    Notifications concernant les factures et paiements.
                  </span>
                </div>
              </div>

              <label class="switch">
                <input
                  v-model="notifications.facturation"
                  type="checkbox"
                />
                <span class="slider"></span>
              </label>
            </div>

            <div class="notification-setting">
              <div class="setting-info">
                <div class="setting-icon orange">
                  <i class="fi fi-rr-envelope"></i>
                </div>

                <div>
                  <strong>Notifications par e-mail</strong>
                  <span>
                    Recevoir également les notifications importantes par e-mail.
                  </span>
                </div>
              </div>

              <label class="switch">
                <input
                  v-model="notifications.email"
                  type="checkbox"
                />
                <span class="slider"></span>
              </label>
            </div>

            <div class="notification-setting">
              <div class="setting-info">
                <div class="setting-icon red">
                  <i class="fi fi-rr-bell"></i>
                </div>

                <div>
                  <strong>Alertes importantes</strong>
                  <span>
                    Recevoir les alertes prioritaires de VITALIS.
                  </span>
                </div>
              </div>

              <label class="switch">
                <input
                  v-model="notifications.alertes"
                  type="checkbox"
                />
                <span class="slider"></span>
              </label>
            </div>

          </div>

          <div class="form-actions">
            <button
              v-if="peutAction('parametres.modifier')"
              class="primary-btn"
              @click="saveNotifications"
            >
              <i class="fi fi-rr-disk"></i>
              Enregistrer les préférences
            </button>
          </div>
        </section>

        <!-- ==========================================
             PREFERENCES
        =========================================== -->
        <section v-if="activeSection === 'preferences'">
          <div class="section-header">
            <div>
              <span class="section-label">PRÉFÉRENCES</span>
              <h2>Préférences de l'application</h2>
              <p>
                Personnalisez votre expérience dans VITALIS.
              </p>
            </div>
          </div>

          <div class="preferences-grid">

            <div class="preference-card">
              <div class="preference-card-header">
                <div class="setting-icon blue">
                  <i class="fi fi-rr-language"></i>
                </div>

                <div>
                  <strong>Langue</strong>
                  <span>Langue de l'interface</span>
                </div>
              </div>

              <select v-model="preferences.langue">
                <option value="fr">Français</option>
                <option value="ar">العربية</option>
                <option value="en">English</option>
              </select>
            </div>

            <div class="preference-card">
              <div class="preference-card-header">
                <div class="setting-icon purple">
                  <i class="fi fi-rr-palette"></i>
                </div>

                <div>
                  <strong>Thème</strong>
                  <span>Apparence de l'application</span>
                </div>
              </div>

              <div class="theme-options">
                <button
                  :class="{ active: preferences.theme === 'light' }"
                  @click="preferences.theme = 'light'"
                >
                  <i class="fi fi-rr-sun"></i>
                  Clair
                </button>

                <button
                  :class="{ active: preferences.theme === 'dark' }"
                  @click="preferences.theme = 'dark'"
                >
                  <i class="fi fi-rr-moon"></i>
                  Sombre
                </button>
              </div>
            </div>

            <div class="preference-card">
              <div class="preference-card-header">
                <div class="setting-icon green">
                  <i class="fi fi-rr-calendar-clock"></i>
                </div>

                <div>
                  <strong>Format de date</strong>
                  <span>Affichage des dates</span>
                </div>
              </div>

              <select v-model="preferences.dateFormat">
                <option value="dd/mm/yyyy">JJ/MM/AAAA</option>
                <option value="yyyy-mm-dd">AAAA-MM-JJ</option>
                <option value="mm/dd/yyyy">MM/JJ/AAAA</option>
              </select>
            </div>

            <div class="preference-card">
              <div class="preference-card-header">
                <div class="setting-icon orange">
                  <i class="fi fi-rr-clock"></i>
                </div>

                <div>
                  <strong>Fuseau horaire</strong>
                  <span>Heure utilisée par VITALIS</span>
                </div>
              </div>

              <select v-model="preferences.timezone">
                <option value="Africa/Casablanca">
                  Casablanca (GMT+1)
                </option>

                <option value="Europe/Paris">
                  Paris
                </option>

                <option value="UTC">
                  UTC
                </option>
              </select>
            </div>

          </div>

          <div class="form-actions">
            <button
              v-if="peutAction('parametres.modifier')"
              class="primary-btn"
              @click="savePreferences"
            >
              <i class="fi fi-rr-disk"></i>
              Enregistrer les préférences
            </button>
          </div>
        </section>

      </main>
    </div>

    <!-- TOAST -->
    <transition name="toast">
      <div
        v-if="notification.show"
        class="toast-notification"
        :class="notification.type"
      >
        <div class="toast-icon">
          <i
            :class="
              notification.type === 'success'
                ? 'fi fi-rr-check-circle'
                : 'fi fi-rr-exclamation'
            "
          ></i>
        </div>

        <div>
          <strong>
            {{ notification.type === 'success' ? 'Succès' : 'Erreur' }}
          </strong>

          <span>{{ notification.message }}</span>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { peutAction } from '../../actions.js'

/* =========================================================
   MENU
========================================================= */

const activeSection = ref('profil')

const menuItems = [
  {
    id: 'profil',
    label: 'Profil',
    description: 'Informations personnelles',
    icon: 'fi fi-rr-user'
  },
  {
    id: 'clinique',
    label: 'Clinique',
    description: 'Informations établissement',
    icon: 'fi fi-rr-hospital'
  },
  {
    id: 'securite',
    label: 'Sécurité',
    description: 'Mot de passe et accès',
    icon: 'fi fi-rr-shield-check'
  },
  {
    id: 'notifications',
    label: 'Notifications',
    description: 'Alertes et e-mails',
    icon: 'fi fi-rr-bell'
  },
  {
    id: 'preferences',
    label: 'Préférences',
    description: 'Langue et apparence',
    icon: 'fi fi-rr-settings'
  }
]

/* =========================================================
   PROFIL
========================================================= */

const originalProfile = {
  prenom: 'Ouassima',
  nom: 'Bnyaiche',
  email: '',
  telephone: '',
  fonction: 'Administrateur',
  langue: 'fr'
}

const profile = ref({ ...originalProfile })

const profilePhoto = ref(null)

const userInitials = computed(() => {
  const first = profile.value.prenom?.charAt(0) || ''
  const last = profile.value.nom?.charAt(0) || ''

  return `${first}${last}`.toUpperCase() || 'VT'
})

const handlePhoto = event => {
  const file = event.target.files?.[0]

  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    showNotification(
      'La photo ne doit pas dépasser 2 Mo.',
      'error'
    )
    return
  }

  profilePhoto.value = URL.createObjectURL(file)

  showNotification('Photo de profil mise à jour.')
}

const resetProfile = () => {
  profile.value = { ...originalProfile }
}

const saveProfile = () => {
  if (!profile.value.prenom || !profile.value.nom) {
    showNotification(
      'Veuillez renseigner le prénom et le nom.',
      'error'
    )
    return
  }

  showNotification(
    'Informations du profil enregistrées.'
  )
}

/* =========================================================
   CLINIQUE
========================================================= */

const clinic = ref({
  nom: 'VITALIS Clinique Médicale',
  telephone: '',
  email: '',
  adresse: '',
  ville: '',
  pays: 'Maroc',
  ouverture: '08:00',
  fermeture: '20:00'
})

const saveClinic = () => {
  if (!clinic.value.nom) {
    showNotification(
      'Veuillez renseigner le nom de la clinique.',
      'error'
    )
    return
  }

  showNotification(
    'Informations de la clinique enregistrées.'
  )
}

/* =========================================================
   SECURITE
========================================================= */

const password = ref({
  current: '',
  newPassword: '',
  confirm: ''
})

const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const passwordLengthValid = computed(() =>
  password.value.newPassword.length >= 8
)

const passwordUppercaseValid = computed(() =>
  /[A-Z]/.test(password.value.newPassword)
)

const passwordNumberValid = computed(() =>
  /\d/.test(password.value.newPassword)
)

const passwordMatchValid = computed(() =>
  password.value.newPassword.length > 0 &&
  password.value.newPassword === password.value.confirm
)

const changePassword = () => {
  if (!password.value.current) {
    showNotification(
      'Veuillez saisir votre mot de passe actuel.',
      'error'
    )
    return
  }

  if (
    !passwordLengthValid.value ||
    !passwordUppercaseValid.value ||
    !passwordNumberValid.value
  ) {
    showNotification(
      'Le nouveau mot de passe ne respecte pas les critères.',
      'error'
    )
    return
  }

  if (!passwordMatchValid.value) {
    showNotification(
      'Les deux mots de passe ne correspondent pas.',
      'error'
    )
    return
  }

  password.value = {
    current: '',
    newPassword: '',
    confirm: ''
  }

  showNotification(
    'Mot de passe modifié avec succès.'
  )
}

/* =========================================================
   NOTIFICATIONS
========================================================= */

const notifications = ref({
  rendezVous: true,
  hospitalisations: true,
  facturation: true,
  email: true,
  alertes: true
})

const saveNotifications = () => {
  showNotification(
    'Préférences de notification enregistrées.'
  )
}

/* =========================================================
   PREFERENCES
========================================================= */

const preferences = ref({
  langue: 'fr',
  theme: 'light',
  dateFormat: 'dd/mm/yyyy',
  timezone: 'Africa/Casablanca'
})

const savePreferences = () => {
  showNotification(
    'Préférences de l’application enregistrées.'
  )
}

/* =========================================================
   TOAST
========================================================= */

const notification = ref({
  show: false,
  type: 'success',
  message: ''
})

let toastTimer = null

const showNotification = (
  message,
  type = 'success'
) => {
  clearTimeout(toastTimer)

  notification.value = {
    show: true,
    type,
    message
  }

  toastTimer = setTimeout(() => {
    notification.value.show = false
  }, 3000)
}
</script>

<style
  scoped
  src="../../styles/parametres.css"
></style>