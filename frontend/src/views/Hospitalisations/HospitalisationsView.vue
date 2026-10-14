<template>
  <div class="hospitalisations-page">

    <!-- ===================== HEADER ===================== -->
    <div class="page-header">
      <div>
        <div class="title-section">
          <div class="title-icon">
            <i class="fi fi-rr-hospital"></i>
          </div>

          <div>
            <h1>Hospitalisations</h1>
            <p>Gérez les hospitalisations, les chambres et les sorties des patients</p>
          </div>
        </div>
      </div>

      <button v-if="peutAction('hospitalisations.creer')" class="btn-primary" @click="openCreateModal">
        <i class="fi fi-rr-plus"></i>
        Nouvelle hospitalisation
      </button>
    </div>


    <!-- ===================== QUICK ACTIONS ===================== -->
    <div class="quick-actions">

      <button v-if="peutAction('hospitalisations.creer')" class="quick-card" @click="openCreateModal">
        <div class="quick-icon blue">
          <i class="fi fi-rr-hospital"></i>
        </div>

        <div>
          <strong>Nouvelle hospitalisation</strong>
          <span>Hospitaliser un patient</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>
      </button>


      <button class="quick-card" @click="statusFilter = 'En cours'">
        <div class="quick-icon orange">
          <i class="fi fi-rr-bed"></i>
        </div>

        <div>
          <strong>Patients hospitalisés</strong>
          <span>Consulter les hospitalisations en cours</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>
      </button>


      <button class="quick-card" @click="statusFilter = 'Sortie'">
        <div class="quick-icon green">
          <i class="fi fi-rr-exit"></i>
        </div>

        <div>
          <strong>Sorties patients</strong>
          <span>Consulter les patients sortis</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>
      </button>

    </div>


    <!-- ===================== MAIN CARD ===================== -->
    <div class="hospitalisations-card">

      <div class="search-section">

        <div class="search-box">
          <i class="fi fi-rr-search"></i>

          <input
            v-model="search"
            type="text"
            placeholder="Rechercher par patient, médecin, chambre..."
          />
        </div>


        <div class="filters">

          <select v-model="statusFilter">
            <option value="">Tous les statuts</option>
            <option value="En cours">En cours</option>
            <option value="Sortie prévue">Sortie prévue</option>
            <option value="Sortie">Sortie</option>
          </select>


          <select v-model="roomFilter">
            <option value="">Toutes les chambres</option>

            <option
              v-for="room in rooms"
              :key="room"
              :value="room"
            >
              Chambre {{ room }}
            </option>
          </select>


          <button class="btn-search">
            <i class="fi fi-rr-search"></i>
            Rechercher
          </button>


          <button class="btn-reset" @click="resetFilters">
            <i class="fi fi-rr-refresh"></i>
            Réinitialiser
          </button>

        </div>
      </div>


      <!-- ===================== TABLE ===================== -->
      <div class="table-responsive">

        <table class="hospitalisations-table">

          <thead>
            <tr>
              <th>#</th>
              <th>Patient</th>
              <th>Chambre</th>
              <th>Médecin</th>
              <th>Date d'entrée</th>
              <th>Sortie prévue</th>
              <th>Motif</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>


          <tbody>

            <tr
              v-for="(hospitalisation, index) in paginatedHospitalisations"
              :key="hospitalisation.id"
            >

              <td>
                {{ (currentPage - 1) * perPage + index + 1 }}
              </td>


              <td>
                <div class="patient-info">

                  <div class="avatar">
                    {{ getInitials(hospitalisation.patient) }}
                  </div>

                  <div>
                    <strong>{{ hospitalisation.patient }}</strong>
                    <small>{{ hospitalisation.patientReference }}</small>
                  </div>

                </div>
              </td>


              <td>
                <div class="room-info">
                  <i class="fi fi-rr-bed"></i>

                  <div>
                    <strong>Chambre {{ hospitalisation.chambre }}</strong>
                    <small v-if="hospitalisation.lit">
                      Lit {{ hospitalisation.lit }}
                    </small>
                  </div>
                </div>
              </td>


              <td>{{ hospitalisation.medecin }}</td>


              <td>{{ formatDate(hospitalisation.dateEntree) }}</td>


              <td>
                {{
                  hospitalisation.dateSortiePrevue
                    ? formatDate(hospitalisation.dateSortiePrevue)
                    : '—'
                }}
              </td>


              <td>
                <span class="reason">
                  {{ hospitalisation.motif }}
                </span>
              </td>


              <td>
                <span
                  class="status-badge"
                  :class="statusClass(hospitalisation.statut)"
                >
                  <span class="status-dot"></span>
                  {{ hospitalisation.statut }}
                </span>
              </td>


              <td>
                <div class="actions">

                  <button
                    class="action-btn view"
                    title="Consulter"
                    @click="openViewModal(hospitalisation)"
                  >
                    <i class="fi fi-rr-eye"></i>
                  </button>


                  <button
                    v-if="peutAction('hospitalisations.modifier')"
                    class="action-btn edit"
                    title="Modifier"
                    @click="openEditModal(hospitalisation)"
                  >
                    <i class="fi fi-rr-pencil"></i>
                  </button>


                  <button
                    v-if="hospitalisation.statut !== 'Sortie' && peutAction('hospitalisations.sortir')"
                    class="action-btn exit"
                    title="Sortie patient"
                    @click="openExitModal(hospitalisation)"
                  >
                    <i class="fi fi-rr-exit"></i>
                  </button>


                  <button
                    v-if="peutAction('hospitalisations.supprimer')"
                    class="action-btn delete"
                    title="Supprimer"
                    @click="openDeleteModal(hospitalisation)"
                  >
                    <i class="fi fi-rr-trash"></i>
                  </button>

                </div>
              </td>

            </tr>


            <tr v-if="filteredHospitalisations.length === 0">
              <td colspan="9">

                <div class="empty-state">

                  <div class="empty-icon">
                    <i class="fi fi-rr-hospital"></i>
                  </div>

                  <h3>Aucune hospitalisation trouvée</h3>

                  <p>
                    Aucune hospitalisation ne correspond aux critères sélectionnés.
                  </p>

                  <button v-if="peutAction('hospitalisations.creer')" class="btn-primary" @click="openCreateModal">
                    <i class="fi fi-rr-plus"></i>
                    Nouvelle hospitalisation
                  </button>

                </div>

              </td>
            </tr>

          </tbody>
        </table>
      </div>


      <!-- ===================== PAGINATION ===================== -->
      <div
        v-if="filteredHospitalisations.length"
        class="table-footer"
      >

        <span>
          Affichage de {{ firstItem }} à {{ lastItem }}
          sur {{ filteredHospitalisations.length }} hospitalisations
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


    <!-- ===================== BACKEND INFO ===================== -->

    <!-- ======================================================
         CREATE / EDIT MODAL
    ======================================================= -->
    <div
      v-if="formModalOpen"
      class="modal-overlay"
      @click.self="closeFormModal"
    >

      <div class="modal">

        <div class="modal-header">

          <div>
            <h2>
              {{
                editingHospitalisation
                  ? 'Modifier l’hospitalisation'
                  : 'Nouvelle hospitalisation'
              }}
            </h2>

            <p>
              {{
                editingHospitalisation
                  ? 'Modifiez les informations de l’hospitalisation'
                  : 'Renseignez les informations du patient à hospitaliser'
              }}
            </p>
          </div>


          <button class="modal-close" @click="closeFormModal">
            ×
          </button>

        </div>


        <form @submit.prevent="saveHospitalisation">

          <div class="form-grid">

            <!-- PATIENT -->
            <div class="form-group">
              <label>Patient *</label>

              <select v-model="form.id_patient" required>
                <option value="">Sélectionner un patient</option>

                <option
                  v-for="patient in patients"
                  :key="patient.id_patient"
                  :value="patient.id_patient"
                >
                  {{ patient.nom }} {{ patient.prenom }}
                </option>
              </select>
            </div>


            <!-- CHAMBRE -->
            <div class="form-group">
              <label>Chambre *</label>

              <select v-model="form.id_chambre" required>
                <option value="">Sélectionner une chambre</option>

                <option
                  v-for="chambre in chambres"
                  :key="chambre.id_chambre"
                  :value="chambre.id_chambre"
                >
                  Chambre {{ chambre.numero_chambre }}
                  - {{ chambre.type_chambre }}
                </option>
              </select>
            </div>


            <!-- MEDECIN -->
            <div class="form-group">
              <label>Médecin responsable *</label>

              <select v-model="form.id_medecin" required>
                <option value="">Sélectionner un médecin</option>

                <option
                  v-for="medecin in medecins"
                  :key="medecin.id_medecin"
                  :value="medecin.id_medecin"
                >
                  Dr. {{ medecin.nom }} {{ medecin.prenom }}
                </option>
              </select>
            </div>


            <!-- DATE ENTREE -->
            <div class="form-group">
              <label>Date d'entrée *</label>

              <input
                v-model="form.date_entree"
                type="date"
                required
              />
            </div>


            <!-- HEURE ENTREE -->
            <div class="form-group">
              <label>Heure d'entrée *</label>

              <input
                v-model="form.heure_entree"
                type="time"
                required
              />
            </div>


            <!-- MOTIF -->
            <div class="form-group full">
              <label>Motif d'hospitalisation *</label>

              <input
                v-model="form.motif_hospitalisation"
                type="text"
                placeholder="Motif de l'hospitalisation"
                required
              />
            </div>


            <!-- DIAGNOSTIC -->
            <div class="form-group full">
              <label>Diagnostic d'entrée *</label>

              <textarea
                v-model="form.diagnostic_entree"
                rows="3"
                placeholder="Diagnostic établi lors de l'admission..."
                required
              ></textarea>
            </div>


            <!-- OBSERVATIONS -->
            <div class="form-group full">
              <label>Observations médicales</label>

              <textarea
                v-model="form.observations"
                rows="4"
                placeholder="Observations complémentaires..."
              ></textarea>
            </div>

          </div>


          <div class="modal-footer">

            <button
              type="button"
              class="btn-cancel"
              @click="closeFormModal"
            >
              Annuler
            </button>


            <button
              v-if="peutAction(editingHospitalisation ? 'hospitalisations.modifier' : 'hospitalisations.creer')"
              type="submit"
              class="btn-save"
            >
              <i class="fi fi-rr-check"></i>

              {{
                editingHospitalisation
                  ? 'Enregistrer les modifications'
                  : 'Hospitaliser le patient'
              }}
            </button>

          </div>

        </form>

      </div>
    </div>


    <!-- ======================================================
         VIEW MODAL
    ======================================================= -->
    <div
      v-if="selectedHospitalisation"
      class="modal-overlay"
      @click.self="selectedHospitalisation = null"
    >

      <div class="modal view-modal">

        <div class="modal-header">

          <div>
            <h2>Détails de l'hospitalisation</h2>
            <p>Informations du patient hospitalisé</p>
          </div>

          <button
            class="modal-close"
            @click="selectedHospitalisation = null"
          >
            ×
          </button>

        </div>


        <div class="patient-profile">

          <div class="large-avatar">
            {{ getInitials(selectedHospitalisation.patient) }}
          </div>

          <div>
            <h3>{{ selectedHospitalisation.patient }}</h3>
            <span>{{ selectedHospitalisation.patientReference }}</span>
          </div>

        </div>


        <div class="details-grid">

          <div class="detail-item">
            <span>Chambre</span>
            <strong>
              Chambre {{ selectedHospitalisation.chambre }}
              <template v-if="selectedHospitalisation.lit">
                — Lit {{ selectedHospitalisation.lit }}
              </template>
            </strong>
          </div>


          <div class="detail-item">
            <span>Médecin</span>
            <strong>{{ selectedHospitalisation.medecin }}</strong>
          </div>


          <div class="detail-item">
            <span>Date d'entrée</span>
            <strong>
              {{ formatDate(selectedHospitalisation.dateEntree) }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Sortie prévue</span>
            <strong>
              {{
                selectedHospitalisation.dateSortiePrevue
                  ? formatDate(selectedHospitalisation.dateSortiePrevue)
                  : 'Non définie'
              }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Statut</span>
            <strong>{{ selectedHospitalisation.statut }}</strong>
          </div>


          <div class="detail-item">
            <span>Motif</span>
            <strong>{{ selectedHospitalisation.motif }}</strong>
          </div>

        </div>


        <div class="observations-section">
          <span>Observations médicales</span>

          <p>
            {{
              selectedHospitalisation.observations ||
              'Aucune observation enregistrée.'
            }}
          </p>
        </div>

      </div>
    </div>


    <!-- ======================================================
         SORTIE PATIENT
    ======================================================= -->
    <div
      v-if="hospitalisationToExit"
      class="modal-overlay"
      @click.self="hospitalisationToExit = null"
    >

      <div class="exit-modal">

        <div class="exit-icon">
          <i class="fi fi-rr-exit"></i>
        </div>

        <h2>Sortie du patient</h2>

        <p>
          Confirmer la sortie de
          <strong>{{ hospitalisationToExit.patient }}</strong>
          de la chambre
          <strong>{{ hospitalisationToExit.chambre }}</strong> ?
        </p>


        <div class="exit-form">

          <label>Date de sortie</label>

          <input
            v-model="exitDate"
            type="date"
          />

        </div>


        <div class="modal-actions">

          <button
            class="btn-cancel"
            @click="hospitalisationToExit = null"
          >
            Annuler
          </button>


          <button v-if="peutAction('hospitalisations.sortir')" class="btn-exit" @click="confirmExit">
            <i class="fi fi-rr-check"></i>
            Confirmer la sortie
          </button>

        </div>

      </div>
    </div>


    <!-- ======================================================
         DELETE MODAL
    ======================================================= -->
    <div
      v-if="hospitalisationToDelete"
      class="modal-overlay"
      @click.self="hospitalisationToDelete = null"
    >

      <div class="delete-modal">

        <div class="delete-icon">
          <i class="fi fi-rr-trash"></i>
        </div>

        <h2>Supprimer l'hospitalisation ?</h2>

        <p>
          Voulez-vous vraiment supprimer l'hospitalisation de
          <strong>{{ hospitalisationToDelete.patient }}</strong> ?
        </p>

        <span>Cette action est irréversible.</span>


        <div class="modal-actions">

          <button
            class="btn-cancel"
            @click="hospitalisationToDelete = null"
          >
            Annuler
          </button>


          <button v-if="peutAction('hospitalisations.supprimer')" class="btn-delete" @click="deleteHospitalisation">
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
  onMounted
} from 'vue'

import api, { messageErreur } from '../../api.js'
import { peutAction } from '../../actions.js'


/*
|--------------------------------------------------------------------------
| SCRUM-760 - Connexion Frontend / Backend
|--------------------------------------------------------------------------
| Cette page utilise maintenant les vraies API Laravel pour :
|
| - récupérer les patients
| - récupérer les médecins
| - récupérer les chambres disponibles
| - récupérer les hospitalisations
| - admettre un patient
| - modifier une hospitalisation
| - enregistrer la sortie d'un patient
| - supprimer une hospitalisation
|--------------------------------------------------------------------------
*/


/* ===================== DONNEES API ===================== */

const patients = ref([])

const chambres = ref([])

const medecins = ref([])

const hospitalisations = ref([])

const toutesLesChambres = ref([])

const loading = ref(false)


/* ===================== FILTERS ===================== */

const search = ref('')

const statusFilter = ref('')

const roomFilter = ref('')


/* ===================== PAGINATION ===================== */

const currentPage = ref(1)

const perPage = 6


/* ===================== MODALS ===================== */

const formModalOpen = ref(false)

const editingHospitalisation = ref(null)

const selectedHospitalisation = ref(null)

const hospitalisationToDelete = ref(null)

const hospitalisationToExit = ref(null)

const exitDate = ref('')


/* ===================== ADMISSION FORM ===================== */

const emptyForm = () => ({
  id_patient: '',
  id_chambre: '',
  id_medecin: '',
  date_entree: '',
  heure_entree: '',
  motif_hospitalisation: '',
  diagnostic_entree: '',
  observations: ''
})

const form = ref(emptyForm())


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
   NORMALISATION DES DONNEES BACKEND
========================================================= */

const normaliserHospitalisation = item => {

  const patientNom = item.patient
    ? `${item.patient.prenom ?? ''} ${item.patient.nom ?? ''}`.trim()
    : `Patient #${item.id_patient}`

  const patientReference =
    item.patient?.cin ||
    `PAT-${String(item.id_patient).padStart(3, '0')}`

  const chambreNumero =
    item.chambre?.numero_chambre ||
    String(item.id_chambre ?? '')

  const medecinNom = item.medecin
    ? `Dr. ${item.medecin.prenom ?? ''} ${item.medecin.nom ?? ''}`.trim()
    : `Médecin #${item.id_medecin}`

  let statut = 'En cours'

  if (item.statut === 'terminee') {
    statut = 'Sortie'
  }

  return {

    /*
     * Champs utilisés actuellement par le template Vue.
     */

    id: item.id_hospitalisation,

    patient: patientNom,

    patientReference,

    chambre: chambreNumero,

    lit: '',

    medecin: medecinNom,

    dateEntree: item.date_entree,

    /*
     * Le backend actuel ne possède pas
     * de champ date_sortie_prevue.
     */

    dateSortiePrevue: '',

    dateSortie: item.date_sortie || '',

    motif: item.motif_hospitalisation,

    statut,

    observations: item.observations || '',


    /*
     * Champs backend conservés pour
     * la modification et les appels API.
     */

    id_hospitalisation: item.id_hospitalisation,

    id_patient: item.id_patient,

    id_chambre: item.id_chambre,

    id_medecin: item.id_medecin,

    date_entree: item.date_entree,

    heure_entree: item.heure_entree,

    date_sortie: item.date_sortie,

    motif_hospitalisation:
      item.motif_hospitalisation,

    diagnostic_entree:
      item.diagnostic_entree,

    statut_backend: item.statut
  }
}


/* =========================================================
   CHARGEMENT DES PATIENTS
========================================================= */

const chargerPatients = async () => {

  try {

    const response =
      await api.get('/patients')

    /*
     * PatientController retourne :
     *
     * {
     *   patients: {
     *     data: [...]
     *   }
     * }
     */

    patients.value =
      response.data?.patients?.data ?? []

  } catch (error) {

    console.error(
      'Erreur chargement patients :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les patients.'
      ),
      'error'
    )
  }
}


/* =========================================================
   CHARGEMENT DES MEDECINS
========================================================= */

const chargerMedecins = async () => {

  try {

    const response =
      await api.get('/medecins')

    /*
     * MedecinController retourne :
     *
     * {
     *   medecins: [...]
     * }
     */

    medecins.value =
      response.data?.medecins ?? []

  } catch (error) {

    console.error(
      'Erreur chargement médecins :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les médecins.'
      ),
      'error'
    )
  }
}


/* =========================================================
   CHARGEMENT DES CHAMBRES DISPONIBLES
========================================================= */

const chargerChambresDisponibles = async () => {

  try {

    const response =
      await api.get('/chambres/disponibles')

    /*
     * ChambreController retourne directement :
     *
     * [...]
     */

    chambres.value =
      Array.isArray(response.data)
        ? response.data
        : []

  } catch (error) {

    console.error(
      'Erreur chargement chambres :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les chambres disponibles.'
      ),
      'error'
    )
  }
}


/* =========================================================
   CHARGEMENT DE TOUTES LES CHAMBRES
========================================================= */

const chargerToutesLesChambres = async () => {

  try {

    const response =
      await api.get('/chambres')

    /*
     * Cette liste est utilisée pour le filtre
     * des chambres dans le tableau.
     */

    if (Array.isArray(response.data)) {

      toutesLesChambres.value =
        response.data

    } else if (
      Array.isArray(response.data?.chambres)
    ) {

      toutesLesChambres.value =
        response.data.chambres

    } else {

      toutesLesChambres.value = []
    }

  } catch (error) {

    console.error(
      'Erreur chargement de toutes les chambres :',
      error
    )

    toutesLesChambres.value = []
  }
}


/* =========================================================
   CHARGEMENT DES HOSPITALISATIONS
========================================================= */

const chargerHospitalisations = async () => {

  try {

    const response =
      await api.get('/hospitalisations')

    /*
     * HospitalisationController retourne :
     *
     * {
     *   hospitalisations: [...]
     * }
     *
     * Chaque hospitalisation contient déjà :
     * patient
     * chambre
     * medecin
     */

    const donnees =
      response.data?.hospitalisations ?? []

    hospitalisations.value =
      donnees.map(normaliserHospitalisation)

  } catch (error) {

    console.error(
      'Erreur chargement hospitalisations :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les hospitalisations.'
      ),
      'error'
    )
  }
}


/* =========================================================
   CHARGEMENT GENERAL
========================================================= */

const chargerDonnees = async () => {

  loading.value = true

  try {

    /*
     * Les données nécessaires au formulaire
     * sont chargées depuis Laravel.
     */

    await Promise.all([
      chargerPatients(),
      chargerMedecins(),
      chargerChambresDisponibles(),
      chargerToutesLesChambres()
    ])

    /*
     * Ensuite on charge les hospitalisations.
     */

    await chargerHospitalisations()

  } finally {

    loading.value = false
  }
}


/* ===================== ROOMS ===================== */

const rooms = computed(() => {

  /*
   * On récupère les numéros des chambres
   * réellement présentes dans la base.
   */

  const source =
    toutesLesChambres.value.length
      ? toutesLesChambres.value
      : chambres.value

  return [
    ...new Set(
      source
        .map(item =>
          String(item.numero_chambre ?? '')
        )
        .filter(Boolean)
    )
  ]
})


/* ===================== FILTERING ===================== */

const filteredHospitalisations = computed(() => {

  const value = search.value
    .trim()
    .toLowerCase()

  return hospitalisations.value.filter(item => {

    const searchable = [
      item.patient,
      item.patientReference,
      item.medecin,
      item.chambre,
      item.motif
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    const matchesSearch =
      !value ||
      searchable.includes(value)

    const matchesStatus =
      !statusFilter.value ||
      item.statut === statusFilter.value

    const matchesRoom =
      !roomFilter.value ||
      item.chambre === roomFilter.value

    return (
      matchesSearch &&
      matchesStatus &&
      matchesRoom
    )
  })
})


/* ===================== PAGINATION ===================== */

const totalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(
      filteredHospitalisations.value.length /
      perPage
    )
  )
)

const paginatedHospitalisations = computed(() => {

  const start =
    (currentPage.value - 1) *
    perPage

  return filteredHospitalisations.value.slice(
    start,
    start + perPage
  )
})

const firstItem = computed(() => {

  if (!filteredHospitalisations.value.length) {
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
    filteredHospitalisations.value.length
  )
)

watch(
  [search, statusFilter, roomFilter],
  () => {
    currentPage.value = 1
  }
)


/* ===================== FILTER RESET ===================== */

const resetFilters = () => {

  search.value = ''

  statusFilter.value = ''

  roomFilter.value = ''

  currentPage.value = 1
}


/* ===================== CREATE ===================== */

const openCreateModal = async () => {

  editingHospitalisation.value = null

  form.value = emptyForm()

  /*
   * On recharge les chambres avant une admission.
   * Une chambre peut être devenue complète depuis
   * le dernier chargement.
   */

  await chargerChambresDisponibles()

  formModalOpen.value = true
}


/* ===================== EDIT ===================== */

const openEditModal = item => {

  editingHospitalisation.value = item

  /*
   * Lors d'une modification, la chambre actuelle
   * doit pouvoir apparaître dans le select même
   * si elle est maintenant occupée.
   */

  const chambreExiste =
    chambres.value.some(
      chambre =>
        Number(chambre.id_chambre) ===
        Number(item.id_chambre)
    )

  if (
    !chambreExiste &&
    item.id_chambre
  ) {

    const chambreActuelle =
      toutesLesChambres.value.find(
        chambre =>
          Number(chambre.id_chambre) ===
          Number(item.id_chambre)
      )

    if (chambreActuelle) {

      chambres.value = [
        ...chambres.value,
        chambreActuelle
      ]
    }
  }

  form.value = {

    id_patient:
      item.id_patient || '',

    id_chambre:
      item.id_chambre || '',

    id_medecin:
      item.id_medecin || '',

    date_entree:
      item.date_entree ||
      item.dateEntree ||
      '',

    heure_entree:
      item.heure_entree
        ? String(item.heure_entree).slice(0, 5)
        : '',

    motif_hospitalisation:
      item.motif_hospitalisation ||
      item.motif ||
      '',

    diagnostic_entree:
      item.diagnostic_entree ||
      '',

    observations:
      item.observations ||
      ''
  }

  formModalOpen.value = true
}


/* ===================== CLOSE ===================== */

const closeFormModal = () => {

  formModalOpen.value = false

  editingHospitalisation.value = null

  form.value = emptyForm()
}


/* =========================================================
   SAVE : ADMISSION / MODIFICATION
========================================================= */

const saveHospitalisation = async () => {

  try {

    /*
     * Payload correspondant exactement
     * aux champs Laravel.
     */

    const payload = {

      id_patient:
        Number(form.value.id_patient),

      id_chambre:
        Number(form.value.id_chambre),

      id_medecin:
        Number(form.value.id_medecin),

      date_entree:
        form.value.date_entree,

      heure_entree:
        form.value.heure_entree,

      motif_hospitalisation:
        form.value.motif_hospitalisation,

      diagnostic_entree:
        form.value.diagnostic_entree,

      observations:
        form.value.observations || null
    }


    /*
     * ================================
     * MODIFICATION
     * ================================
     */

    if (editingHospitalisation.value) {

      const id =
        editingHospitalisation.value.id

      await api.put(
        `/hospitalisations/${id}`,
        payload
      )

      showNotification(
        'Hospitalisation modifiée avec succès.'
      )

    } else {

      /*
       * ================================
       * NOUVELLE ADMISSION
       * ================================
       *
       * On utilise /admettre et NON
       * POST /hospitalisations.
       *
       * Ainsi Laravel applique la logique :
       * - contrôle de capacité
       * - statut de la chambre
       * - statut en_cours
       */

      await api.post(
        '/hospitalisations/admettre',
        payload
      )

      showNotification(
        'Patient hospitalisé avec succès.'
      )
    }


    /*
     * Fermer le formulaire.
     */

    closeFormModal()


    /*
     * Recharger les données depuis PostgreSQL.
     */

    await chargerChambresDisponibles()

    await chargerToutesLesChambres()

    await chargerHospitalisations()

    currentPage.value = 1

  } catch (error) {

    console.error(
      'Erreur enregistrement hospitalisation :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible d’enregistrer l’hospitalisation.'
      ),
      'error'
    )
  }
}


/* ===================== VIEW ===================== */

const openViewModal = item => {

  selectedHospitalisation.value = item
}


/* ===================== EXIT ===================== */

const openExitModal = item => {

  hospitalisationToExit.value = item

  exitDate.value =
    new Date()
      .toISOString()
      .slice(0, 10)
}


/* =========================================================
   CONFIRMER SORTIE
========================================================= */

const confirmExit = async () => {

  if (!hospitalisationToExit.value) {
    return
  }

  if (!exitDate.value) {

    showNotification(
      'Veuillez sélectionner la date de sortie.',
      'error'
    )

    return
  }

  try {

    const id =
      hospitalisationToExit.value.id

    /*
     * Appel de la route Laravel SCRUM-755 :
     *
     * PATCH
     * /hospitalisations/{id}/sortir
     */

    await api.patch(
      `/hospitalisations/${id}/sortir`,
      {
        date_sortie: exitDate.value
      }
    )

    hospitalisationToExit.value = null

    showNotification(
      'Sortie du patient enregistrée avec succès.'
    )


    /*
     * La sortie libère une place.
     * On recharge donc :
     *
     * - les chambres
     * - les hospitalisations
     */

    await chargerChambresDisponibles()

    await chargerToutesLesChambres()

    await chargerHospitalisations()

  } catch (error) {

    console.error(
      'Erreur sortie patient :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible d’enregistrer la sortie du patient.'
      ),
      'error'
    )
  }
}


/* ===================== DELETE ===================== */

const openDeleteModal = item => {

  hospitalisationToDelete.value = item
}


/* =========================================================
   DELETE HOSPITALISATION
========================================================= */

const deleteHospitalisation = async () => {

  if (!hospitalisationToDelete.value) {
    return
  }

  try {

    const id =
      hospitalisationToDelete.value.id

    await api.delete(
      `/hospitalisations/${id}`
    )

    hospitalisationToDelete.value = null

    showNotification(
      'Hospitalisation supprimée avec succès.'
    )

    await chargerChambresDisponibles()

    await chargerToutesLesChambres()

    await chargerHospitalisations()

    if (
      currentPage.value >
      totalPages.value
    ) {

      currentPage.value =
        totalPages.value
    }

  } catch (error) {

    console.error(
      'Erreur suppression hospitalisation :',
      error
    )

    showNotification(
      messageErreur(
        error,
        'Impossible de supprimer l’hospitalisation.'
      ),
      'error'
    )
  }
}


/* ===================== HELPERS ===================== */

const getInitials = name => {

  if (!name) {
    return '--'
  }

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(word => word[0])
    .join('')
    .toUpperCase()
}


const formatDate = date => {

  if (!date) {
    return '—'
  }

  const parsedDate =
    new Date(`${date}T00:00:00`)

  if (
    Number.isNaN(
      parsedDate.getTime()
    )
  ) {
    return date
  }

  return parsedDate
    .toLocaleDateString('fr-FR')
}


const statusClass = status => {

  switch (status) {

    case 'Sortie':
      return 'completed'

    case 'Sortie prévue':
      return 'planned'

    default:
      return 'progress'
  }
}


/* =========================================================
   INITIALISATION DE LA PAGE
========================================================= */

onMounted(async () => {

  await chargerDonnees()
})

</script>


<style scoped src="../../styles/hospitalisations.css"></style>