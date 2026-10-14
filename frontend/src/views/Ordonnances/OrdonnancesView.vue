<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import gsap from 'gsap'
import api from '../../api.js'
import { peutAction } from '../../actions.js'
import '../../styles/ordonnances.css'
const showCreateModal = ref(false)
const consultationsDisponibles = ref([])
const createError = ref('')
const editingId = ref(null)
const newOrdonnance = ref({
  id_consultation: '',
  date_ordonnance: '',
  type: '',
  duree_traitement: '',
  instructions_generales: ''
})
const search = ref('')
const selectedDoctor = ref('')
const selectedDate = ref('')
const selectedStatus = ref('')

const currentPage = ref(1)
const perPage = 5

const ordonnances = ref([])
const loading = ref(false)
const error = ref('')

function formatDate(date) {
  if (!date) return ''

  return new Intl.DateTimeFormat('fr-FR').format(
    new Date(`${date}T00:00:00`)
  )
}

function initials(prenom = '', nom = '') {
  return `${prenom.charAt(0)}${nom.charAt(0)}`.toUpperCase()
}

function mapOrdonnance(item) {
  const consultation = item.consultation
  const rendezVous = consultation?.rendez_vous
  const patient = rendezVous?.patient
  const medecin = rendezVous?.medecin

  return {
    id: item.id_ordonnance,

    patient: patient
      ? `${patient.prenom} ${patient.nom}`
      : 'Patient inconnu',

    cin: patient?.cin ?? '-',

    initials: patient
      ? initials(patient.prenom, patient.nom)
      : '--',

    medecin: medecin
      ? `Dr. ${medecin.prenom} ${medecin.nom}`
      : 'Médecin inconnu',

    specialite: `Spécialité #${medecin?.id_specialite ?? '-'}`,

    date: formatDate(item.date_ordonnance),
    dateISO: item.date_ordonnance,

    diagnostic: consultation?.diagnostic ?? '-',

    medicaments: (item.lignes ?? [])
      .map(ligne => ligne.medicament?.nom_medicament)
      .filter(Boolean),

    statut: 'Active',

    raw: item
  }
}

async function loadOrdonnances() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/ordonnances')

    ordonnances.value = (response.data.ordonnances ?? [])
      .map(mapOrdonnance)
  } catch (err) {
    console.error(err)
    error.value = 'Impossible de charger les ordonnances.'
  } finally {
    loading.value = false
  }
}

const doctors = computed(() => [
  ...new Set(ordonnances.value.map(item => item.medecin))
])

const filteredOrdonnances = computed(() => {
  const keyword = search.value.trim().toLowerCase()

  return ordonnances.value.filter(item => {
    const matchSearch =
      !keyword ||
      item.patient.toLowerCase().includes(keyword) ||
      item.medecin.toLowerCase().includes(keyword) ||
      item.diagnostic.toLowerCase().includes(keyword) ||
      item.medicaments.some(m =>
        m.toLowerCase().includes(keyword)
      )

    const matchDoctor =
      !selectedDoctor.value ||
      item.medecin === selectedDoctor.value

    const matchDate =
      !selectedDate.value ||
      item.dateISO === selectedDate.value

    const matchStatus =
      !selectedStatus.value ||
      item.statut === selectedStatus.value

    return (
      matchSearch &&
      matchDoctor &&
      matchDate &&
      matchStatus
    )
  })
})

const totalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(filteredOrdonnances.value.length / perPage)
  )
)

const paginatedOrdonnances = computed(() => {
  const start = (currentPage.value - 1) * perPage

  return filteredOrdonnances.value.slice(
    start,
    start + perPage
  )
})

function resetFilters() {
  search.value = ''
  selectedDoctor.value = ''
  selectedDate.value = ''
  selectedStatus.value = ''
  currentPage.value = 1
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

function statusClass(status) {
  if (status === 'Active') {
    return 'status-active'
  }

  if (status === 'À renouveler') {
    return 'status-renew'
  }

  return ''
}

/* =========================================================
   ACTIONS
========================================================= */

async function createOrdonnance() {
  createError.value = ''

  try {
    const response = await api.get(
      '/ordonnances/consultations-disponibles'
    )

    consultationsDisponibles.value =
      response.data.consultations ?? []

    showCreateModal.value = true
  } catch (err) {
    console.error(err)
    alert('Impossible de charger les consultations.')
  }
}
async function saveOrdonnance() {
  createError.value = ''

  try {
    if (editingId.value) {
      await api.put(
        `/ordonnances/${editingId.value}`,
        newOrdonnance.value
      )
    } else {
      await api.post(
        '/ordonnances',
        newOrdonnance.value
      )
    }

    showCreateModal.value = false
    editingId.value = null

    newOrdonnance.value = {
      id_consultation: '',
      date_ordonnance: '',
      type: '',
      duree_traitement: '',
      instructions_generales: ''
    }

    await loadOrdonnances()

  } catch (err) {
    console.error(err)

    createError.value =
      err.response?.data?.message ||
      'Impossible d’enregistrer l’ordonnance.'
  }
}

function viewOrdonnance(item) {
  console.log('Voir ordonnance', item)
}

function editOrdonnance(item) {
  editingId.value = item.id

  newOrdonnance.value = {
    id_consultation: item.raw.id_consultation,
    date_ordonnance: item.raw.date_ordonnance,
    type: item.raw.type,
    duree_traitement: item.raw.duree_traitement,
    instructions_generales: item.raw.instructions_generales
  }

  consultationsDisponibles.value = [
    {
      id_consultation: item.raw.id_consultation,
      patient_prenom: item.patient.split(' ')[0],
      patient_nom: item.patient.split(' ').slice(1).join(' '),
      diagnostic: item.diagnostic
    }
  ]

  showCreateModal.value = true
}

async function deleteOrdonnance(item) {
  const confirmed = confirm(
    `Voulez-vous vraiment supprimer l'ordonnance #${item.id} ?`
  )

  if (!confirmed) return

  try {
    await api.delete(`/ordonnances/${item.id}`)
    await loadOrdonnances()
  } catch (err) {
    console.error(err)

    alert(
      err.response?.data?.message ||
      "Impossible de supprimer l'ordonnance."
    )
  }
}

function downloadOrdonnance(item) {
  console.log('Télécharger ordonnance', item)
}

/* =========================================================
   GSAP
========================================================= */

onMounted(async () => {
  await loadOrdonnances()
  await nextTick()

  const tl = gsap.timeline({
    defaults: {
      ease: 'power3.out'
    }
  })

  tl.from('.ord-title', {
    y: 18,
    opacity: 0,
    duration: 0.55
  })

    .from(
      '.ord-stat-card',
      {
        y: 22,
        opacity: 0,
        scale: 0.97,
        duration: 0.5,
        stagger: 0.08
      },
      '-=0.25'
    )

    .from(
      '.ord-filter-card',
      {
        y: 18,
        opacity: 0,
        duration: 0.45
      },
      '-=0.25'
    )

    .from(
      '.ord-table-card',
      {
        y: 20,
        opacity: 0,
        duration: 0.5
      },
      '-=0.2'
    )
})
</script>

<template>
  <section class="ordonnances-page">

    <!-- HEADER -->

    <div class="ord-title">

      <div>
        <h1>
          Gestion des ordonnances
        </h1>

        <p class="subtitle">
          Consultez et gérez les ordonnances des patients
        </p>
      </div>

      <button
        v-if="peutAction('ordonnances.creer')"
        class="new-ordonnance-btn"
        @click="createOrdonnance"
      >
        <i class="fi fi-rr-plus"></i>

        Nouvelle ordonnance
      </button>

    </div>

    <!-- STATS -->

    <div class="ord-stats">

      <article class="ord-stat-card">

        <div class="stat-icon blue">
          <i class="fi fi-rr-document"></i>
        </div>

        <div>
          <strong>128</strong>
          <span>Ordonnances</span>
          <small>↗ +12%</small>
        </div>

      </article>


      <article class="ord-stat-card">

        <div class="stat-icon green">
          <i class="fi fi-rr-users"></i>
        </div>

        <div>
          <strong>98</strong>
          <span>Patients concernés</span>
          <small>↗ +8%</small>
        </div>

      </article>


      <article class="ord-stat-card">

        <div class="stat-icon purple">
          <i class="fi fi-rr-capsules"></i>
        </div>

        <div>
          <strong>245</strong>
          <span>Médicaments prescrits</span>
          <small>↗ +15%</small>
        </div>

      </article>


      <article class="ord-stat-card">

        <div class="stat-icon orange">
          <i class="fi fi-rr-calendar"></i>
        </div>

        <div>
          <strong>32</strong>
          <span>Ce mois-ci</span>
          <small>↗ +20%</small>
        </div>

      </article>

    </div>

    <!-- FILTRES -->

    <div class="ord-filter-card">

      <div class="search-input">

        <i class="fi fi-rr-search"></i>

        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par patient, médecin ou médicament..."
        />

      </div>


      <select v-model="selectedDoctor">

        <option value="">
          Tous les médecins
        </option>

        <option
          v-for="doctor in doctors"
          :key="doctor"
          :value="doctor"
        >
          {{ doctor }}
        </option>

      </select>


      <input
        v-model="selectedDate"
        type="date"
      />


      <select v-model="selectedStatus">

        <option value="">
          Tous les statuts
        </option>

        <option value="Active">
          Active
        </option>

        <option value="À renouveler">
          À renouveler
        </option>

      </select>


      <button
        class="reset-button"
        @click="resetFilters"
      >
        <i class="fi fi-rr-refresh"></i>

        Réinitialiser
      </button>

    </div>

    <!-- TABLEAU -->

    <div class="ord-table-card">

      <div class="table-wrapper">

        <table class="ord-table">

          <thead>
            <tr>
              <th>#</th>
              <th>Patient</th>
              <th>Médecin</th>
              <th>Date</th>
              <th>Diagnostic</th>
              <th>Médicaments</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>


          <tbody>

            <tr
              v-for="item in paginatedOrdonnances"
              :key="item.id"
            >

              <td>
                {{ item.id }}
              </td>


              <!-- PATIENT -->

              <td>

                <div class="person-cell">

                  <div class="avatar">
                    {{ item.initials }}
                  </div>

                  <div>

                    <strong>
                      {{ item.patient }}
                    </strong>

                    <span>
                      {{ item.cin }}
                    </span>

                  </div>

                </div>

              </td>


              <!-- MEDECIN -->

              <td>

                <div class="doctor-cell">

                  <strong>
                    {{ item.medecin }}
                  </strong>

                  <span>
                    {{ item.specialite }}
                  </span>

                </div>

              </td>


              <!-- DATE -->

              <td>
                {{ item.date }}
              </td>


              <!-- DIAGNOSTIC -->

              <td>
                {{ item.diagnostic }}
              </td>


              <!-- MEDICAMENTS -->

              <td>

                <div class="medicines">

                  <span>
                    {{
                      item.medicaments
                        .slice(0, 2)
                        .join(', ')
                    }}
                  </span>

                  <small
                    v-if="item.medicaments.length > 2"
                  >
                    +{{ item.medicaments.length - 2 }}
                  </small>

                </div>

              </td>


              <!-- STATUT -->

              <td>

                <span
                  class="status-badge"
                  :class="statusClass(item.statut)"
                >
                  {{ item.statut }}
                </span>

              </td>


              <!-- ACTIONS -->

              <td>

                <div class="actions">

                  <button
                    class="action-btn"
                    title="Voir"
                    @click="viewOrdonnance(item)"
                  >
                    <i class="fi fi-rr-eye"></i>
                  </button>


                  <button
                    v-if="peutAction('ordonnances.telecharger')"
                    class="action-btn download"
                    title="Télécharger"
                    @click="downloadOrdonnance(item)"
                  >
                    <i class="fi fi-rr-download"></i>
                  </button>


                  <button
                    v-if="peutAction('ordonnances.modifier')"
                    class="action-btn"
                    title="Modifier"
                    @click="editOrdonnance(item)"
                  >
                    <i class="fi fi-rr-pencil"></i>
                  </button>


                  <button
                    v-if="peutAction('ordonnances.supprimer')"
                    class="action-btn delete"
                    title="Supprimer"
                    @click="deleteOrdonnance(item)"
                  >
                    <i class="fi fi-rr-trash"></i>
                  </button>

                </div>

              </td>

            </tr>


            <tr v-if="paginatedOrdonnances.length === 0">

              <td
                colspan="8"
                class="ord-empty"
              >
                Aucune ordonnance trouvée.
              </td>

            </tr>

          </tbody>

        </table>

      </div>

      <!-- PAGINATION -->

      <div class="ord-table-footer">

        <span>
          Affichage de 1 à
          {{ paginatedOrdonnances.length }}
          sur
          {{ filteredOrdonnances.length }}
          résultats
        </span>


        <div class="pagination">

          <button
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            ‹
          </button>


          <button
            v-for="page in totalPages"
            :key="page"
            :class="{ active: currentPage === page }"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>


          <button
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            ›
          </button>

        </div>

            </div>

    </div>

    <!-- MODAL NOUVELLE ORDONNANCE -->
    <div
      v-if="showCreateModal"
      class="modal-overlay"
    >
      <div class="modal-card">

        <h2>Nouvelle ordonnance</h2>

        <label>Consultation</label>
        <select v-model="newOrdonnance.id_consultation">
          <option value="">
            Sélectionner une consultation
          </option>

          <option
            v-for="c in consultationsDisponibles"
            :key="c.id_consultation"
            :value="c.id_consultation"
          >
            {{ c.patient_prenom }} {{ c.patient_nom }}
            - {{ c.diagnostic }}
          </option>
        </select>

        <label>Date</label>
        <input
          v-model="newOrdonnance.date_ordonnance"
          type="date"
        />

        <label>Type</label>
        <input
          v-model="newOrdonnance.type"
          type="text"
          placeholder="Ex: Traitement"
        />

        <label>Durée du traitement</label>
        <input
          v-model="newOrdonnance.duree_traitement"
          type="text"
          placeholder="Ex: 7 jours"
        />

        <label>Instructions générales</label>
        <textarea
          v-model="newOrdonnance.instructions_generales"
          placeholder="Instructions..."
        ></textarea>

        <p v-if="createError">
          {{ createError }}
        </p>

        <div class="modal-actions">

          <button
            type="button"
            @click="showCreateModal = false; editingId = null"
          >
            Annuler
          </button>

          <button
            type="button"
            class="new-ordonnance-btn"
            @click="saveOrdonnance"
          >
            Enregistrer
          </button>

        </div>

      </div>
    </div>

  </section>
</template>