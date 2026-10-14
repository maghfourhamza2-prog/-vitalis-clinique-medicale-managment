<template>
  <div class="patients-page">

    <!-- ===================== HEADER ===================== -->
    <div class="page-header">

      <div class="title-section">

        <div class="title-icon">
          <i class="fi fi-rr-user"></i>
        </div>

        <div>
          <h1>Patients</h1>
          <p>
            Gérez les patients et leurs informations personnelles
          </p>
        </div>

      </div>


      <button
        v-if="peutAction('patients.creer')"
        class="btn-primary"
        @click="openCreateModal"
      >
        <i class="fi fi-rr-plus"></i>
        Nouveau patient
      </button>

    </div>


    <!-- ===================== QUICK ACTIONS ===================== -->
    <div class="quick-actions">

      <!-- NOUVEAU PATIENT -->
      <button v-if="peutAction('patients.creer')" class="quick-card" @click="openCreateModal">

        <div class="quick-icon blue">
          <i class="fi fi-rr-user-add"></i>
        </div>

        <div>
          <strong>Nouveau patient</strong>
          <span>Enregistrer un nouveau patient</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>


      <!-- LISTE PATIENTS -->
      <button
        class="quick-card"
        @click="showAllPatients"
      >

        <div class="quick-icon orange">
          <i class="fi fi-rr-users"></i>
        </div>

        <div>
          <strong>Liste des patients</strong>
          <span>
            Consulter tous les patients enregistrés
          </span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>


      <!-- GROUPES SANGUINS -->
      <button
        class="quick-card"
        @click="focusBloodFilter"
      >

        <div class="quick-icon green">
          <i class="fi fi-rr-droplet"></i>
        </div>

        <div>
          <strong>Groupes sanguins</strong>
          <span>
            Filtrer les patients par groupe sanguin
          </span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>

    </div>


    <!-- ===================== MAIN CARD ===================== -->
    <div class="patients-card">

      <!-- ================= SEARCH ================= -->
      <div class="search-section">

        <div class="search-box">

          <i class="fi fi-rr-search"></i>

          <input
            v-model="search"
            type="text"
            placeholder="Rechercher par nom, prénom, CIN, téléphone..."
          />

        </div>


        <div class="filters">

          <!-- SEXE -->
          <select v-model="sexeFilter">
            <option value="">
              Tous les sexes
            </option>

            <option value="Homme">
              Homme
            </option>

            <option value="Femme">
              Femme
            </option>
          </select>


          <!-- GROUPE SANGUIN -->
          <select
            ref="bloodSelect"
            v-model="bloodFilter"
          >
            <option value="">
              Tous les groupes sanguins
            </option>

            <option
              v-for="groupe in groupesSanguins"
              :key="groupe"
              :value="groupe"
            >
              {{ groupe }}
            </option>
          </select>


          <button class="btn-search">
            <i class="fi fi-rr-search"></i>
            Rechercher
          </button>


          <button
            class="btn-reset"
            @click="resetFilters"
          >
            <i class="fi fi-rr-refresh"></i>
            Réinitialiser
          </button>

        </div>

      </div>


      <!-- ===================== TABLE ===================== -->
      <div class="table-responsive">

        <table class="patients-table">

          <thead>

            <tr>
              <th>#</th>
              <th>Patient</th>
              <th>CIN</th>
              <th>Téléphone</th>
              <th>Email</th>
              <th>Date naissance</th>
              <th>Sexe</th>
              <th>Groupe sanguin</th>
              <th>Actions</th>
            </tr>

          </thead>


          <tbody>

            <tr
              v-for="(patient, index) in paginatedPatients"
              :key="patient.id_patient"
            >

              <!-- ID -->
              <td>
                {{ (currentPage - 1) * perPage + index + 1 }}
              </td>


              <!-- PATIENT -->
              <td>

                <div class="patient-info">

                  <div class="avatar">
                    {{ getInitials(patient) }}
                  </div>

                  <div>

                    <strong>
                      {{ patient.prenom }}
                      {{ patient.nom }}
                    </strong>

                    <small>
                      PAT-{{
                        String(patient.id_patient)
                          .padStart(3, '0')
                      }}
                    </small>

                  </div>

                </div>

              </td>


              <!-- CIN -->
              <td>
                <strong class="cin">
                  {{ patient.cin }}
                </strong>
              </td>


              <!-- TELEPHONE -->
              <td>
                {{ patient.telephone }}
              </td>


              <!-- EMAIL -->
              <td>
                <span class="email">
                  {{ patient.email }}
                </span>
              </td>


              <!-- DATE NAISSANCE -->
              <td>
                {{ formatDate(patient.date_naissance) }}
              </td>


              <!-- SEXE -->
              <td>

                <span
                  class="sexe-badge"
                  :class="
                    patient.sexe === 'Femme'
                      ? 'female'
                      : 'male'
                  "
                >
                  <span class="badge-dot"></span>

                  {{ patient.sexe }}
                </span>

              </td>


              <!-- GROUPE SANGUIN -->
              <td>

                <span class="blood-badge">
                  {{ patient.groupe_sanguin }}
                </span>

              </td>


              <!-- ACTIONS -->
              <td>

                <div class="actions">

                  <!-- VOIR -->
                  <button
                    class="action-btn view"
                    title="Consulter"
                    @click="openViewModal(patient)"
                  >
                    <i class="fi fi-rr-eye"></i>
                  </button>


                  <!-- MODIFIER -->
                  <button
                    v-if="peutAction('patients.modifier')"
                    class="action-btn edit"
                    title="Modifier"
                    @click="openEditModal(patient)"
                  >
                    <i class="fi fi-rr-pencil"></i>
                  </button>


                  <!-- SUPPRIMER -->
                  <button
                    v-if="peutAction('patients.supprimer')"
                    class="action-btn delete"
                    title="Supprimer"
                    @click="openDeleteModal(patient)"
                  >
                    <i class="fi fi-rr-trash"></i>
                  </button>

                </div>

              </td>

            </tr>


            <!-- AUCUN PATIENT -->
            <tr v-if="filteredPatients.length === 0">

              <td colspan="9">

                <div class="empty-state">

                  <div class="empty-icon">
                    <i class="fi fi-rr-users"></i>
                  </div>

                  <h3>
                    Aucun patient trouvé
                  </h3>

                  <p>
                    Aucun patient ne correspond
                    aux critères sélectionnés.
                  </p>

                  <button v-if="peutAction('patients.creer')" class="btn-primary" @click="openCreateModal">
                    <i class="fi fi-rr-plus"></i>
                    Nouveau patient
                  </button>

                </div>

              </td>

            </tr>

          </tbody>

        </table>

      </div>


      <!-- ===================== PAGINATION ===================== -->
      <div
        v-if="filteredPatients.length"
        class="table-footer"
      >

        <span>
          Affichage de {{ firstItem }}
          à {{ lastItem }}
          sur {{ filteredPatients.length }}
          patients
        </span>


        <div class="pagination">

          <button
            :disabled="currentPage === 1"
            @click="currentPage--"
          >
            ‹
          </button>


          <button
            v-for="page in totalPages"
            :key="page"
            :class="{ active: currentPage === page }"
            @click="currentPage = page"
          >
            {{ page }}
          </button>


          <button
            :disabled="currentPage === totalPages"
            @click="currentPage++"
          >
            ›
          </button>

        </div>

      </div>

    </div>


    <!-- ======================================================
         MODAL AJOUT / MODIFICATION PATIENT
    ======================================================= -->
    <div
      v-if="formModalOpen"
      class="modal-overlay"
      @click.self="closeFormModal"
    >

      <div class="modal">

        <!-- HEADER MODAL -->
        <div class="modal-header">

          <div>

            <h2>
              {{
                editingPatient
                  ? 'Modifier le patient'
                  : 'Nouveau patient'
              }}
            </h2>

            <p>
              {{
                editingPatient
                  ? 'Modifiez les informations du patient'
                  : 'Renseignez les informations du nouveau patient'
              }}
            </p>

          </div>


          <button
            type="button"
            class="modal-close"
            @click="closeFormModal"
          >
            ×
          </button>

        </div>


        <!-- FORMULAIRE -->
        <form @submit.prevent="savePatient">

          <div class="form-grid">

            <!-- NOM -->
            <div class="form-group">

              <label>
                Nom *
              </label>

              <input
                v-model="form.nom"
                type="text"
                placeholder="Nom du patient"
                required
              />

            </div>


            <!-- PRENOM -->
            <div class="form-group">

              <label>
                Prénom *
              </label>

              <input
                v-model="form.prenom"
                type="text"
                placeholder="Prénom du patient"
                required
              />

            </div>


            <!-- CIN -->
            <div class="form-group">

              <label>
                CIN *
              </label>

              <input
                v-model="form.cin"
                type="text"
                placeholder="Ex : AB123456"
                required
              />

            </div>


            <!-- DATE NAISSANCE -->
            <div class="form-group">

              <label>
                Date de naissance *
              </label>

              <input
                v-model="form.date_naissance"
                type="date"
                required
              />

            </div>


            <!-- SEXE -->
            <div class="form-group">

              <label>
                Sexe *
              </label>

              <select
                v-model="form.sexe"
                required
              >

                <option value="">
                  Sélectionner
                </option>

                <option value="Homme">
                  Homme
                </option>

                <option value="Femme">
                  Femme
                </option>

              </select>

            </div>


            <!-- GROUPE SANGUIN -->
            <div class="form-group">

              <label>
                Groupe sanguin *
              </label>

              <select
                v-model="form.groupe_sanguin"
                required
              >

                <option value="">
                  Sélectionner
                </option>

                <option
                  v-for="groupe in groupesSanguins"
                  :key="groupe"
                  :value="groupe"
                >
                  {{ groupe }}
                </option>

              </select>

            </div>


            <!-- TELEPHONE -->
            <div class="form-group">

              <label>
                Téléphone *
              </label>

              <input
                v-model="form.telephone"
                type="tel"
                placeholder="Ex : 0612345678"
                required
              />

            </div>


            <!-- EMAIL -->
            <div class="form-group">

              <label>
                Email *
              </label>

              <input
                v-model="form.email"
                type="email"
                placeholder="patient@email.com"
                required
              />

            </div>

          </div>


          <!-- FOOTER MODAL -->
          <div class="modal-footer">

            <button
              type="button"
              class="btn-cancel"
              @click="closeFormModal"
            >
              Annuler
            </button>


            <button
              v-if="peutAction(editingPatient ? 'patients.modifier' : 'patients.creer')"
              type="submit"
              class="btn-save"
            >
              <i class="fi fi-rr-check"></i>

              {{
                editingPatient
                  ? 'Enregistrer les modifications'
                  : 'Ajouter le patient'
              }}
            </button>

          </div>

        </form>

      </div>

    </div>


    <!-- ======================================================
         MODAL DETAILS PATIENT
    ======================================================= -->
    <div
      v-if="selectedPatient"
      class="modal-overlay"
      @click.self="selectedPatient = null"
    >

      <div class="modal view-modal">

        <div class="modal-header">

          <div>
            <h2>Détails du patient</h2>
            <p>
              Informations personnelles du patient
            </p>
          </div>

          <button
            class="modal-close"
            @click="selectedPatient = null"
          >
            ×
          </button>

        </div>


        <!-- PROFILE -->
        <div class="patient-profile">

          <div class="large-avatar">
            {{ getInitials(selectedPatient) }}
          </div>

          <div>

            <h3>
              {{ selectedPatient.prenom }}
              {{ selectedPatient.nom }}
            </h3>

            <span>
              PAT-{{
                String(selectedPatient.id_patient)
                  .padStart(3, '0')
              }}
            </span>

          </div>

        </div>


        <!-- DETAILS -->
        <div class="details-grid">

          <div class="detail-item">
            <span>CIN</span>
            <strong>
              {{ selectedPatient.cin }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Date de naissance</span>
            <strong>
              {{
                formatDate(
                  selectedPatient.date_naissance
                )
              }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Sexe</span>
            <strong>
              {{ selectedPatient.sexe }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Groupe sanguin</span>
            <strong>
              {{ selectedPatient.groupe_sanguin }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Téléphone</span>
            <strong>
              {{ selectedPatient.telephone }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Email</span>
            <strong>
              {{ selectedPatient.email }}
            </strong>
          </div>

        </div>

      </div>

    </div>


    <!-- ======================================================
         DELETE MODAL
    ======================================================= -->
    <div
      v-if="patientToDelete"
      class="modal-overlay"
      @click.self="patientToDelete = null"
    >

      <div class="delete-modal">

        <div class="delete-icon">
          <i class="fi fi-rr-trash"></i>
        </div>

        <h2>
          Supprimer le patient ?
        </h2>

        <p>
          Voulez-vous vraiment supprimer

          <strong>
            {{ patientToDelete.prenom }}
            {{ patientToDelete.nom }}
          </strong>

          ?
        </p>

        <span>
          Cette action est irréversible.
        </span>


        <div class="modal-actions">

          <button
            class="btn-cancel"
            @click="patientToDelete = null"
          >
            Annuler
          </button>


          <button v-if="peutAction('patients.supprimer')" class="btn-delete" @click="deletePatient">
            Supprimer
          </button>

        </div>

      </div>

    </div>


    <!-- ===================== NOTIFICATION ===================== -->
    <transition name="notification">

      <div
        v-if="notification.show"
        class="notification"
        :class="notification.type"
      >

        <i
          :class="
            notification.type === 'success'
              ? 'fi fi-rr-check-circle'
              : 'fi fi-rr-exclamation'
          "
        ></i>

        {{ notification.message }}

      </div>

    </transition>

  </div>
</template>


<script setup>

 import {
  ref,
  computed,
  watch,
  nextTick,
  onMounted
} from 'vue'

import api, { messageErreur } from '../../api.js'
import { peutAction } from '../../actions.js'


/*
|--------------------------------------------------------------------------
| DONNÉES TEMPORAIRES
|--------------------------------------------------------------------------
| Pour le moment aucune API Laravel.
| Ces patients seront remplacés ensuite par GET /api/patients.
|--------------------------------------------------------------------------
*/

  const patients = ref([])
  const loading = ref(false)

  const chargerPatients = async () => {
  loading.value = true

  try {
    const response = await api.get('/patients')

    patients.value = response.data?.patients?.data ?? []
  } catch (error) {
    showNotification(
      messageErreur(error, 'Impossible de charger les patients.'),
      'error'
    )
  } finally {
    loading.value = false
  }
}


/* ===================== GROUPES SANGUINS ===================== */

const groupesSanguins = [
  'A+',
  'A-',
  'B+',
  'B-',
  'AB+',
  'AB-',
  'O+',
  'O-'
]


/* ===================== FILTRES ===================== */

const search = ref('')

const sexeFilter = ref('')

const bloodFilter = ref('')

const bloodSelect = ref(null)


/* ===================== PAGINATION ===================== */

const currentPage = ref(1)

const perPage = 5


/* ===================== MODALS ===================== */

const formModalOpen = ref(false)

const editingPatient = ref(null)

const selectedPatient = ref(null)

const patientToDelete = ref(null)


/* ===================== FORM ===================== */

const emptyForm = () => ({
  nom: '',
  prenom: '',
  date_naissance: '',
  sexe: '',
  cin: '',
  telephone: '',
  email: '',
  groupe_sanguin: ''
})


const form = ref(
  emptyForm()
)


/* ===================== NOTIFICATION ===================== */

const notification = ref({
  show: false,
  type: 'success',
  message: ''
})


let notificationTimer = null


const showNotification = (
  message,
  type = 'success'
) => {

  clearTimeout(notificationTimer)

  notification.value = {
    show: true,
    type,
    message
  }


  notificationTimer = setTimeout(() => {

    notification.value.show = false

  }, 3000)

}


/* =========================================================
   FILTRAGE
========================================================= */

const filteredPatients = computed(() => {

  const value =
    search.value
      .trim()
      .toLowerCase()


  return patients.value.filter(patient => {

    const searchable = [

      patient.nom,
      patient.prenom,
      patient.cin,
      patient.telephone,
      patient.email,
      patient.groupe_sanguin

    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()


    const matchesSearch =
      !value ||
      searchable.includes(value)


    const matchesSexe =
      !sexeFilter.value ||
      patient.sexe === sexeFilter.value


    const matchesBlood =
      !bloodFilter.value ||
      patient.groupe_sanguin ===
        bloodFilter.value


    return (
      matchesSearch &&
      matchesSexe &&
      matchesBlood
    )

  })

})


/* =========================================================
   PAGINATION
========================================================= */

const totalPages = computed(() =>

  Math.max(
    1,
    Math.ceil(
      filteredPatients.value.length /
      perPage
    )
  )

)


const paginatedPatients = computed(() => {

  const start =
    (currentPage.value - 1) *
    perPage


  return filteredPatients.value.slice(
    start,
    start + perPage
  )

})


const firstItem = computed(() => {

  if (!filteredPatients.value.length) {
    return 0
  }

  return (
    (currentPage.value - 1) *
      perPage +
    1
  )

})


const lastItem = computed(() =>

  Math.min(
    currentPage.value * perPage,
    filteredPatients.value.length
  )

)


watch(
  [
    search,
    sexeFilter,
    bloodFilter
  ],
  () => {

    currentPage.value = 1

  }
)


/* =========================================================
   RESET
========================================================= */

const resetFilters = () => {

  search.value = ''

  sexeFilter.value = ''

  bloodFilter.value = ''

  currentPage.value = 1

}


const showAllPatients = () => {

  resetFilters()

}


const focusBloodFilter = async () => {

  bloodFilter.value = ''

  await nextTick()

  bloodSelect.value?.focus()

}


/* =========================================================
   CREATE
========================================================= */

const openCreateModal = () => {

  editingPatient.value = null

  form.value = emptyForm()

  formModalOpen.value = true

}


/* =========================================================
   EDIT
========================================================= */

const openEditModal = patient => {

  editingPatient.value = patient


  form.value = {
    nom: patient.nom,
    prenom: patient.prenom,
    date_naissance:
      patient.date_naissance,
    sexe: patient.sexe,
    cin: patient.cin,
    telephone: patient.telephone,
    email: patient.email,
    groupe_sanguin:
      patient.groupe_sanguin
  }


  formModalOpen.value = true

}


/* =========================================================
   CLOSE FORM
========================================================= */

const closeFormModal = () => {

  formModalOpen.value = false

  editingPatient.value = null

  form.value = emptyForm()

}


/* =========================================================
   SAVE TEMPORAIRE
========================================================= */

  const savePatient = async () => {

  try {

    /*
    |----------------------------------------------------------
    | MODIFICATION
    |----------------------------------------------------------
    */

    if (editingPatient.value) {

      await api.put(
        `/patients/${editingPatient.value.id_patient}`,
        form.value
      )

      showNotification(
        'Patient modifié avec succès.'
      )

    }

    /*
    |----------------------------------------------------------
    | AJOUT
    |----------------------------------------------------------
    */

    else {

      await api.post(
        '/patients',
        form.value
      )

      showNotification(
        'Patient ajouté avec succès.'
      )

    }

    closeFormModal()

    await chargerPatients()

  } catch (error) {

    showNotification(
      messageErreur(
        error,
        'Impossible d’enregistrer le patient.'
      ),
      'error'
    )

  }

}

/* =========================================================
   VIEW
========================================================= */

 const openViewModal = async patient => {

  try {

    const response = await api.get(
      `/patients/${patient.id_patient}`
    )

    selectedPatient.value =
      response.data?.patient ?? null

  } catch (error) {

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les informations du patient.'
      ),
      'error'
    )

  }

}


/* =========================================================
   DELETE
========================================================= */

const openDeleteModal = patient => {

  patientToDelete.value = patient

}


  const deletePatient = async () => {

  if (!patientToDelete.value) {
    return
  }

  try {

    await api.delete(
      `/patients/${patientToDelete.value.id_patient}`
    )

    patientToDelete.value = null

    showNotification(
      'Patient supprimé avec succès.'
    )

    await chargerPatients()

  } catch (error) {

    showNotification(
      messageErreur(
        error,
        'Impossible de supprimer le patient.'
      ),
      'error'
    )

  }

}


/* =========================================================
   HELPERS
========================================================= */

const getInitials = patient => {

  const prenom =
    patient.prenom?.charAt(0) || ''

  const nom =
    patient.nom?.charAt(0) || ''


  return (
    prenom + nom
  ).toUpperCase()

}


const formatDate = date => {

  if (!date) {
    return '—'
  }


  const value =
    new Date(`${date}T00:00:00`)


  return value.toLocaleDateString(
    'fr-FR'
  )

}
onMounted(() => {
  chargerPatients()
})

</script>


<style src="../../styles/patients.css"></style>