<template>
  <div class="examens-page">

    <!-- =====================================================
         HEADER
    ====================================================== -->
    <div class="page-header">

      <div>
        
        <div class="title-section">

          <div class="title-icon">
            <i class="fi fi-rr-document"></i>
          </div>

          <div>
            <h1>Examens</h1>

            <p>
              Gérez les examens médicaux et leurs résultats
            </p>
          </div>

        </div>
      </div>


      <button
        v-if="peutAction('examens.creer')"
        class="btn-primary"
        @click="openCreateModal"
      >
        <i class="fi fi-rr-plus"></i>
        Nouvel examen
      </button>

    </div>


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="quick-actions">

      <button class="quick-card">

        <div class="quick-icon blue">
          <i class="fi fi-rr-document"></i>
        </div>

        <div>
          <strong>Nouvel examen</strong>
          <span>Créer une demande d'examen</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>


      <button class="quick-card">

        <div class="quick-icon orange">
          <i class="fi fi-rr-time-check"></i>
        </div>

        <div>
          <strong>Examens en attente</strong>
          <span>Consulter les examens à réaliser</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>


      <button class="quick-card">

        <div class="quick-icon green">
          <i class="fi fi-rr-check-circle"></i>
        </div>

        <div>
          <strong>Résultats disponibles</strong>
          <span>Consulter les résultats médicaux</span>
        </div>

        <i class="fi fi-rr-angle-small-right arrow"></i>

      </button>

    </div>


    <!-- =====================================================
         CARD
    ====================================================== -->

    <div class="examens-card">

      <!-- SEARCH -->

      <div class="search-section">

        <div class="search-box">

          <i class="fi fi-rr-search"></i>

          <input
            v-model="search"
            type="text"
            placeholder="Rechercher par patient, type d'examen ou médecin..."
          />

        </div>


        <div class="filters">

          <select v-model="statusFilter">

            <option value="">
              Tous les statuts
            </option>

            <option value="En attente">
              En attente
            </option>

            <option value="En cours">
              En cours
            </option>

            <option value="Terminé">
              Terminé
            </option>

          </select>


          <select v-model="typeFilter">

            <option value="">
              Tous les types
            </option>

            <option
              v-for="type in examTypes"
              :key="type"
              :value="type"
            >
              {{ type }}
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


      <!-- =================================================
           TABLE
      ================================================== -->

      <div class="table-responsive">

        <table class="examens-table">

          <thead>

            <tr>
              <th>#</th>
              <th>Patient</th>
              <th>Type d'examen</th>
              <th>Médecin</th>
              <th>Date</th>
              <th>Priorité</th>
              <th>Statut</th>
              <th>Résultat</th>
              <th>Actions</th>
            </tr>

          </thead>


          <tbody>

            <tr
              v-for="(examen, index) in paginatedExamens"
              :key="examen.id"
            >

              <td>
                {{ (currentPage - 1) * perPage + index + 1 }}
              </td>


              <!-- PATIENT -->

              <td>

                <div class="patient-info">

                  <div class="avatar">
                    {{ getInitials(examen.patient) }}
                  </div>

                  <div>
                    <strong>
                      {{ examen.patient }}
                    </strong>

                    <small>
                      {{ examen.patientReference }}
                    </small>
                  </div>

                </div>

              </td>


              <!-- TYPE -->

              <td>

                <div class="exam-type">

                  <div class="exam-icon">
                    <i class="fi fi-rr-document"></i>
                  </div>

                  <span>
                    {{ examen.type }}
                  </span>

                </div>

              </td>


              <!-- DOCTOR -->

              <td>
                {{ examen.medecin }}
              </td>


              <!-- DATE -->

              <td>
                {{ formatDate(examen.date) }}
              </td>


              <!-- PRIORITY -->

              <td>

                <span
                  class="priority-badge"
                  :class="priorityClass(examen.priorite)"
                >
                  {{ examen.priorite }}
                </span>

              </td>


              <!-- STATUS -->

              <td>

                <span
                  class="status-badge"
                  :class="statusClass(examen.statut)"
                >

                  <span class="status-dot"></span>

                  {{ examen.statut }}

                </span>

              </td>


              <!-- RESULT -->

              <td>

                <span
                  v-if="examen.resultat"
                  class="result-available"
                >
                  Disponible
                </span>

                <span
                  v-else
                  class="result-pending"
                >
                  —
                </span>

              </td>


              <!-- ACTIONS -->

              <td>

                <div class="actions">

                  <button
                    class="action-btn view"
                    title="Consulter"
                    @click="openViewModal(examen)"
                  >
                    <i class="fi fi-rr-eye"></i>
                  </button>


                  <button
                    v-if="peutAction('examens.modifier')"
                    class="action-btn edit"
                    title="Modifier"
                    @click="openEditModal(examen)"
                  >
                    <i class="fi fi-rr-pencil"></i>
                  </button>


                  <button
                    v-if="peutAction('examens.supprimer')"
                    class="action-btn delete"
                    title="Supprimer"
                    @click="openDeleteModal(examen)"
                  >
                    <i class="fi fi-rr-trash"></i>
                  </button>

                </div>

              </td>

            </tr>


            <!-- EMPTY -->

            <tr v-if="filteredExamens.length === 0">

              <td colspan="9">

                <div class="empty-state">

                  <div class="empty-icon">
                    <i class="fi fi-rr-document"></i>
                  </div>

                  <h3>Aucun examen trouvé</h3>

                  <p>
                    Aucun examen ne correspond aux critères sélectionnés.
                  </p>

                  <button
                    class="btn-primary"
                    @click="openCreateModal"
                  >
                    <i class="fi fi-rr-plus"></i>
                    Nouvel examen
                  </button>

                </div>

              </td>

            </tr>

          </tbody>

        </table>

      </div>


      <!-- =================================================
           PAGINATION
      ================================================== -->

      <div
        v-if="filteredExamens.length"
        class="table-footer"
      >

        <span>

          Affichage de
          {{ firstItem }}
          à
          {{ lastItem }}
          sur
          {{ filteredExamens.length }}
          examens

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





    <!-- =====================================================
         CREATE / EDIT MODAL
    ====================================================== -->

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
                editingExamen
                  ? 'Modifier l’examen'
                  : 'Nouvel examen'
              }}
            </h2>

            <p>
              {{
                editingExamen
                  ? 'Modifiez les informations de l’examen'
                  : 'Renseignez les informations de l’examen médical'
              }}
            </p>

          </div>


          <button
            class="modal-close"
            @click="closeFormModal"
          >
            ×
          </button>

        </div>


        <form @submit.prevent="saveExamen">

          <div class="form-grid">
            <!-- CONSULTATION -->

<div class="form-group full">

  <label>Consultation *</label>

  <select
    v-model="form.id_consultation"
    @change="onConsultationChange"
    required
  >

    <option value="">
      Sélectionner une consultation
    </option>

    <option
      v-for="consultation in consultations"
      :key="consultation.id_consultation"
      :value="consultation.id_consultation"
    >
      Consultation #{{ consultation.id_consultation }}
      -
      {{ consultation.rendez_vous?.patient?.nom }}
      {{ consultation.rendez_vous?.patient?.prenom }}
    </option>

  </select>

</div>


              <!-- PATIENT -->

<div class="form-group">

  <label>Patient *</label>

  <input
    v-model="form.patient"
    type="text"
    placeholder="Sélectionnez une consultation"
    readonly
  />

</div>


             <!-- REFERENCE -->

<div class="form-group">

  <label>Référence patient</label>

  <input
    v-model="form.patientReference"
    type="text"
    placeholder="Référence patient"
    readonly
  />

</div>

            <!-- TYPE -->

            <div class="form-group">

              <label>Type d'examen *</label>

              <select
                v-model="form.type"
                required
              >

                <option value="">
                  Sélectionner
                </option>

                <option
                  v-for="type in examTypes"
                  :key="type"
                  :value="type"
                >
                  {{ type }}
                </option>

              </select>

            </div>


              <!-- DOCTOR -->

<div class="form-group">

  <label>Médecin *</label>

  <input
    v-model="form.medecin"
    type="text"
    placeholder="Sélectionnez une consultation"
    readonly
  />

</div>


            <!-- DATE -->

            <div class="form-group">

              <label>Date *</label>

              <input
                v-model="form.date"
                type="date"
                required
              />

            </div>


            <!-- PRIORITY -->

            <div class="form-group">

              <label>Priorité</label>

              <select v-model="form.priorite">

                <option value="Normale">
                  Normale
                </option>

                <option value="Urgente">
                  Urgente
                </option>

              </select>

            </div>


            <!-- STATUS -->

            <div class="form-group">

              <label>Statut</label>

              <select v-model="form.statut">

                <option value="En attente">
                  En attente
                </option>

                <option value="En cours">
                  En cours
                </option>

                <option value="Terminé">
                  Terminé
                </option>

              </select>

            </div>

            <!-- INDICATIONS CLINIQUES -->

<div class="form-group full">

  <label>Indications cliniques *</label>

  <textarea
    v-model="form.indications_cliniques"
    rows="3"
    placeholder="Ex : douleur persistante au genou droit..."
    required
  ></textarea>

</div>


            <!-- RESULT -->

            <div class="form-group full">

              <label>Résultat</label>

              <textarea
                v-model="form.resultat"
                rows="4"
                placeholder="Résultat de l'examen..."
              ></textarea>

            </div>


            <!-- NOTES -->

            <div class="form-group full">

              <label>Notes</label>

              <textarea
                v-model="form.notes"
                rows="3"
                placeholder="Informations ou observations complémentaires..."
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
              v-if="peutAction(editingExamen ? 'examens.modifier' : 'examens.creer')"
              type="submit"
              class="btn-save"
            >

              <i class="fi fi-rr-check"></i>

              {{
                editingExamen
                  ? 'Enregistrer les modifications'
                  : 'Créer l’examen'
              }}

            </button>

          </div>

        </form>

      </div>

    </div>


    <!-- =====================================================
         VIEW MODAL
    ====================================================== -->

    <div
      v-if="selectedExamen"
      class="modal-overlay"
      @click.self="selectedExamen = null"
    >

      <div class="modal view-modal">

        <div class="modal-header">

          <div>
            <h2>Détails de l'examen</h2>

            <p>
              Informations complètes de l'examen médical
            </p>
          </div>


          <button
            class="modal-close"
            @click="selectedExamen = null"
          >
            ×
          </button>

        </div>


        <div class="patient-profile">

          <div class="large-avatar">
            {{ getInitials(selectedExamen.patient) }}
          </div>

          <div>

            <h3>
              {{ selectedExamen.patient }}
            </h3>

            <span>
              {{ selectedExamen.patientReference }}
            </span>

          </div>

        </div>


        <div class="details-grid">

          <div class="detail-item">
            <span>Type d'examen</span>
            <strong>{{ selectedExamen.type }}</strong>
          </div>


          <div class="detail-item">
            <span>Médecin</span>
            <strong>{{ selectedExamen.medecin }}</strong>
          </div>


          <div class="detail-item">
            <span>Date</span>
            <strong>
              {{ formatDate(selectedExamen.date) }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Priorité</span>

            <strong>
              {{ selectedExamen.priorite }}
            </strong>
          </div>


          <div class="detail-item">
            <span>Statut</span>

            <strong>
              {{ selectedExamen.statut }}
            </strong>
          </div>

        </div>


        <div class="result-section">

          <span>Résultat</span>

          <p>
            {{
              selectedExamen.resultat ||
              'Aucun résultat disponible pour le moment.'
            }}
          </p>

        </div>


        <div
          v-if="selectedExamen.notes"
          class="result-section"
        >

          <span>Notes</span>

          <p>
            {{ selectedExamen.notes }}
          </p>

        </div>

      </div>

    </div>


    <!-- =====================================================
         DELETE MODAL
    ====================================================== -->

    <div
      v-if="examenToDelete"
      class="modal-overlay"
      @click.self="examenToDelete = null"
    >

      <div class="delete-modal">

        <div class="delete-icon">
          <i class="fi fi-rr-trash"></i>
        </div>

        <h2>Supprimer l'examen ?</h2>

        <p>
          Voulez-vous vraiment supprimer l'examen

          <strong>
            {{ examenToDelete.type }}
          </strong>

          du patient

          <strong>
            {{ examenToDelete.patient }}
          </strong>
          ?
        </p>


        <span>
          Cette action est irréversible.
        </span>


        <div class="delete-actions">

          <button
            class="btn-cancel"
            @click="examenToDelete = null"
          >
            Annuler
          </button>


          <button
            class="btn-delete"
            @click="deleteExamen"
          >
            Supprimer
          </button>

        </div>

      </div>

    </div>


    <!-- =====================================================
         NOTIFICATION
    ====================================================== -->

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
import { peut } from '../../rbac.js'
import { peutAction } from '../../actions.js'


/*
|--------------------------------------------------------------------------
| SCRUM-749
|--------------------------------------------------------------------------
|
| Frontend Examens finalisé.
|
| IMPORTANT :
| Les données ci-dessous servent uniquement à finaliser
| l'interface Frontend.
|
| Dans SCRUM-750 :
|
| import api from '../api'
|
| GET    /examens
| POST   /examens
| PUT    /examens/{id}
| DELETE /examens/{id}
|
| seront ajoutés lorsque le Backend Examens sera disponible.
|
*/


/* =========================================================
   TYPES
========================================================= */

const examTypes = [
  'Analyse sanguine',
  'Radiographie',
  'Échographie',
  'IRM',
  'Scanner',
  'ECG',
  'Analyse urinaire',
  'Autre'
]


 /* =========================================================
   DONNÉES EXAMENS - API
========================================================= */

const examens = ref([])
const loading = ref(false)
const consultations = ref([])

const mapperExamen = (demande) => {
  const patient = demande.consultation?.rendez_vous?.patient
  const medecin = demande.consultation?.rendez_vous?.medecin

  return {
    id: demande.id_demande_examen,

    patient: patient
      ? `${patient.nom ?? ''} ${patient.prenom ?? ''}`.trim()
      : 'Patient non disponible',

    patientReference: patient?.cin ?? '',

    type: demande.type_examen ?? 'Non renseigné',

    medecin: medecin
      ? `Dr. ${medecin.nom ?? ''} ${medecin.prenom ?? ''}`.trim()
      : 'Médecin non disponible',

    date: demande.date_prevue,

    priorite:
      demande.niveau_urgence === 'urgent'
        ? 'Urgente'
        : 'Normale',

    statut:
      demande.statut === 'realise'
        ? 'Terminé'
        : demande.statut === 'en_cours'
          ? 'En cours'
          : 'En attente',

    resultat:
      demande.resultat?.resultat_detaille ?? '',

    notes: demande.observation ?? '',
    indications_cliniques:
      demande.indications_cliniques ?? '',

    id_consultation: demande.id_consultation,

    demandeOriginale: demande
  }
}

   const chargerExamens = async () => {
  loading.value = true

  try {
    const reponse = await api.get('/demandes-examen')
    const demandes = reponse.data?.demandes_examens ?? []

    let resultats = []

    if (peut('resultats.read')) {
      try {
        const reponseResultats = await api.get('/resultats')
        resultats = reponseResultats.data?.resultats ?? []
      } catch (error) {
        showNotification(
          messageErreur(
            error,
            'Les demandes sont disponibles, mais les résultats n’ont pas pu être chargés.'
          ),
          'error'
        )
      }
    }

    const resultatsParDemande = new Map(
      resultats.map(resultat => [
        Number(resultat.id_demande_examen),
        resultat
      ])
    )

    examens.value = demandes.map(demande =>
      mapperExamen({
        ...demande,
        resultat:
          resultatsParDemande.get(
            Number(demande.id_demande_examen)
          ) ?? null
      })
    )

  } catch (error) {

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les examens.'
      ),
      'error'
    )

  } finally {
    loading.value = false
  }
}


/* =========================================================
   CHARGEMENT DES CONSULTATIONS
========================================================= */

const chargerConsultations = async () => {
  try {
    const reponse = await api.get('/consultations')

    consultations.value =
      reponse.data?.consultations?.data ?? []

  } catch (error) {

    showNotification(
      messageErreur(
        error,
        'Impossible de charger les consultations.'
      ),
      'error'
    )
  }
}


/* =========================================================
   SÉLECTION D'UNE CONSULTATION
========================================================= */

const onConsultationChange = () => {
  const consultation =
    consultations.value.find(
      item =>
        Number(item.id_consultation) ===
        Number(form.value.id_consultation)
    )

  if (!consultation) {
    form.value.patient = ''
    form.value.patientReference = ''
    form.value.medecin = ''
    return
  }

  const patient =
    consultation.rendez_vous?.patient

  const medecin =
    consultation.rendez_vous?.medecin

  form.value.patient = patient
    ? `${patient.nom ?? ''} ${patient.prenom ?? ''}`.trim()
    : ''

  form.value.patientReference =
    patient?.cin ?? ''

  form.value.medecin = medecin
    ? `Dr. ${medecin.nom ?? ''} ${medecin.prenom ?? ''}`.trim()
    : ''
}


/* =========================================================
   CHARGEMENT INITIAL
========================================================= */

onMounted(() => {
  chargerExamens()
  chargerConsultations()
})

/* =========================================================
   FILTERS
========================================================= */

const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')


/* =========================================================
   PAGINATION
========================================================= */

const currentPage = ref(1)

const perPage = 6


/* =========================================================
   MODALS
========================================================= */

const formModalOpen = ref(false)

const editingExamen = ref(null)

const selectedExamen = ref(null)

const examenToDelete = ref(null)


/* =========================================================
   FORM
========================================================= */

const emptyForm = () => ({
  id_consultation: '',
  patient: '',
  patientReference: '',
  type: '',
  medecin: '',
  date: '',
  priorite: 'Normale',
  statut: 'En attente',
  resultat: '',
  notes: '',
  indications_cliniques: ''
})


const form = ref(emptyForm())


/* =========================================================
   NOTIFICATION
========================================================= */

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
   FILTERED DATA
========================================================= */

const filteredExamens = computed(() => {

  const value =
    search.value
      .trim()
      .toLowerCase()


  return examens.value.filter(examen => {

    const searchable = [
      examen.patient,
      examen.patientReference,
      examen.type,
      examen.medecin
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()


    const matchesSearch =
      !value ||
      searchable.includes(value)


    const matchesStatus =
      !statusFilter.value ||
      examen.statut === statusFilter.value


    const matchesType =
      !typeFilter.value ||
      examen.type === typeFilter.value


    return (
      matchesSearch &&
      matchesStatus &&
      matchesType
    )

  })

})


/* =========================================================
   PAGINATION
========================================================= */

const totalPages = computed(() => {

  return Math.max(
    1,
    Math.ceil(
      filteredExamens.value.length /
      perPage
    )
  )

})


const paginatedExamens = computed(() => {

  const start =
    (currentPage.value - 1) *
    perPage


  return filteredExamens.value.slice(
    start,
    start + perPage
  )

})


const firstItem = computed(() => {

  if (!filteredExamens.value.length) {
    return 0
  }


  return (
    (currentPage.value - 1) *
      perPage +
    1
  )

})


const lastItem = computed(() => {

  return Math.min(
    currentPage.value * perPage,
    filteredExamens.value.length
  )

})


watch(
  [
    search,
    statusFilter,
    typeFilter
  ],
  () => {

    currentPage.value = 1

  }
)


/* =========================================================
   RESET FILTERS
========================================================= */

const resetFilters = () => {

  search.value = ''

  statusFilter.value = ''

  typeFilter.value = ''

  currentPage.value = 1
}


/* =========================================================
   CREATE
========================================================= */

const openCreateModal = () => {

  editingExamen.value = null

  form.value = emptyForm()

  formModalOpen.value = true
}


/* =========================================================
   EDIT
========================================================= */

const openEditModal = examen => {

  editingExamen.value = examen

  form.value = {
    ...examen
  }

  formModalOpen.value = true
}


/* =========================================================
   CLOSE FORM
========================================================= */

const closeFormModal = () => {

  formModalOpen.value = false

  editingExamen.value = null

  form.value = emptyForm()
}


/* =========================================================
   SAVE
========================================================= */

  const saveExamen = async () => {
  const aujourdHui =
    new Date().toISOString().split('T')[0]

  const statutBackend =
    form.value.statut === 'Terminé'
      ? 'realise'
      : form.value.statut === 'En cours'
        ? 'en_cours'
        : 'en_attente'

  const prioriteBackend =
    form.value.priorite === 'Urgente'
      ? 'urgent'
      : 'normal'

  const donnees = {
    date_demande:
      editingExamen.value?.demandeOriginale?.date_demande
      ?? aujourdHui,

    type_examen: form.value.type,

    niveau_urgence: prioriteBackend,

    indications_cliniques:
      form.value.indications_cliniques,

    statut: statutBackend,

    date_prevue: form.value.date,

    date_realisation:
      statutBackend === 'realise'
        ? (
            editingExamen.value?.demandeOriginale?.date_realisation
            ?? aujourdHui
          )
        : null,

    observation:
      form.value.notes || null,

    id_consultation:
      Number(form.value.id_consultation)
  }

  try {
    let idDemandeExamen = null

    // ==========================================
    // 1. AJOUTER OU MODIFIER LA DEMANDE D'EXAMEN
    // ==========================================
    if (editingExamen.value) {
      const reponse = await api.put(
        `/demandes-examen/${editingExamen.value.id}`,
        donnees
      )

      idDemandeExamen =
        reponse.data.demande_examen.id_demande_examen
    } else {
      const reponse = await api.post(
        '/demandes-examen',
        donnees
      )

      idDemandeExamen =
        reponse.data.demande_examen.id_demande_examen
    }

    // ==========================================
    // 2. RÉCUPÉRER LE RÉSULTAT SAISI
    // ==========================================
    const resultatTexte =
      form.value.resultat?.trim()

    const resultatExistant =
      editingExamen.value
        ?.demandeOriginale
        ?.resultat

    // ==========================================
    // 3. AJOUTER OU MODIFIER LE RÉSULTAT
    // ==========================================
    if (resultatTexte) {
      const donneesResultat = {
        date_resultat: aujourdHui,
        resultat_detaille: resultatTexte,
        conclusion: null,
        valeurs_mesurees: null,
        fichier_resultat: null,
        image_resultat: null,
        observations: null,
        id_demande_examen: idDemandeExamen
      }

      // Le résultat existe déjà → modification
      if (resultatExistant?.id_resultat) {
        await api.put(
          `/resultats/${resultatExistant.id_resultat}`,
          donneesResultat
        )
      }

      // Aucun résultat → création
      else {
        await api.post(
          '/resultats',
          donneesResultat
        )
      }
    }

    // ==========================================
    // 4. SI LE RÉSULTAT A ÉTÉ VIDÉ
    // ==========================================
    else if (resultatExistant?.id_resultat) {
      await api.delete(
        `/resultats/${resultatExistant.id_resultat}`
      )
    }

    // ==========================================
    // 5. NOTIFICATION
    // ==========================================
    showNotification(
      editingExamen.value
        ? 'Examen modifié avec succès.'
        : 'Examen ajouté avec succès.'
    )

    // Fermer le formulaire
    closeFormModal()

    // Retour à la première page
    currentPage.value = 1

    // Recharger les examens depuis le backend
    await chargerExamens()

  } catch (error) {
    showNotification(
      messageErreur(
        error,
        editingExamen.value
          ? 'Impossible de modifier l’examen.'
          : 'Impossible d’ajouter l’examen.'
      ),
      'error'
    )
  }
}

/* =========================================================
   VIEW
========================================================= */

const openViewModal = examen => {

  selectedExamen.value = examen
}


/* =========================================================
   DELETE
========================================================= */

const openDeleteModal = examen => {

  examenToDelete.value = examen
}


    const deleteExamen = async () => {
  if (!examenToDelete.value) {
    return
  }

  try {
    const resultat =
      examenToDelete.value
        ?.demandeOriginale
        ?.resultat

    // Supprimer d'abord le résultat associé
    if (resultat?.id_resultat) {
      await api.delete(
        `/resultats/${resultat.id_resultat}`
      )
    }

    // Puis supprimer la demande d'examen
    await api.delete(
      `/demandes-examen/${examenToDelete.value.id}`
    )

    examenToDelete.value = null

    await chargerExamens()

    if (currentPage.value > totalPages.value) {
      currentPage.value =
        Math.max(totalPages.value, 1)
    }

    showNotification(
      'Examen supprimé avec succès.'
    )
  } catch (error) {
    showNotification(
      messageErreur(
        error,
        'Impossible de supprimer l’examen.'
      ),
      'error'
    )
  }
}

/* =========================================================
   HELPERS
========================================================= */

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

    case 'Terminé':
      return 'completed'

    case 'En cours':
      return 'progress'

    default:
      return 'pending'

  }
}


const priorityClass = priority => {

  return priority === 'Urgente'
    ? 'urgent'
    : 'normal'
}
</script>


<style scoped src="../../styles/examens.css"></style>