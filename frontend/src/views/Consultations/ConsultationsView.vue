<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import gsap from 'gsap'
import api, { messageErreur } from '../../api'
import { peutAction } from '../../actions.js'
import { useNotification } from '../../composables/useNotification.js'
import '../../styles/consultations.css'
import ConsultationFormModal from './ConsultationFormModal.vue'
import ConsultationConfirmModal from './ConsultationConfirmModal.vue'

const { success, error: notifierErreur } = useNotification()

const search = ref('')
const selectedDoctor = ref('')
const selectedDate = ref('')
const selectedStatus = ref('')

const consultations = ref([])
const loading = ref(false)

const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const from = ref(0)
const to = ref(0)

const stats = ref({
  total_consultations: 0,
  patients_consultes: 0,
  medecins_actifs: 0,
  consultations_ce_mois: 0
})

async function chargerConsultations(page = 1) {
  loading.value = true

  try {
    const params = { page }
    if (search.value.trim()) params.recherche = search.value.trim()
    if (selectedDoctor.value) params.id_medecin = selectedDoctor.value
    if (selectedDate.value) params.date = selectedDate.value
    if (selectedStatus.value) params.statut = selectedStatus.value

    const reponse = await api.get('/consultations', { params })
    const donnees = reponse.data?.consultations

    consultations.value = donnees?.data ?? []
    currentPage.value = donnees?.current_page ?? 1
    lastPage.value = donnees?.last_page ?? 1
    total.value = donnees?.total ?? 0
    from.value = donnees?.from ?? 0
    to.value = donnees?.to ?? 0
  } catch (error) {
    notifierErreur(messageErreur(error, 'Impossible de charger les consultations.'))
  } finally {
    loading.value = false
  }
}

async function chargerStats() {
  try {
    const reponse = await api.get('/consultations/stats')
    stats.value = reponse.data
  } catch (error) {
    notifierErreur(messageErreur(error, 'Impossible de charger les statistiques.'))
  }
}

const doctors = computed(() => {
  const noms = consultations.value
    .map((c) => c.rendez_vous?.medecin)
    .filter(Boolean)
    .map((m) => ({ id: m.id_medecin, nom: `Dr. ${m.nom} ${m.prenom}` }))

  const uniques = new Map(noms.map((m) => [m.id, m]))
  return [...uniques.values()]
})

function goToPage(page) {
  if (page >= 1 && page <= lastPage.value) {
    chargerConsultations(page)
  }
}

function resetFilters() {
  search.value = ''
  selectedDoctor.value = ''
  selectedDate.value = ''
  selectedStatus.value = ''
  chargerConsultations(1)
}

function statusClass(status) {
  if (status === 'Terminé') return 'status-completed'
  if (status === 'En cours') return 'status-progress'
  if (status === 'Annulé') return 'status-cancelled'
  if (status === 'Confirmé') return 'status-confirmed'
  if (status === 'En attente') return 'status-pending'
  return ''
}

function initiales(nom, prenom) {
  return `${(nom || '').charAt(0)}${(prenom || '').charAt(0)}`.toUpperCase()
}

// --- Modal formulaire (view/create/edit) ---

const modalOuverte = ref(false)
const modalMode = ref('view')
const consultationSelectionnee = ref(null)

function createConsultation() {
  consultationSelectionnee.value = null
  modalMode.value = 'create'
  modalOuverte.value = true
}

function viewConsultation(item) {
  consultationSelectionnee.value = item
  modalMode.value = 'view'
  modalOuverte.value = true
}

function editConsultation(item) {
  consultationSelectionnee.value = item
  modalMode.value = 'edit'
  modalOuverte.value = true
}

function fermerModal() {
  modalOuverte.value = false
  consultationSelectionnee.value = null
}

async function sauvegarde() {
  const estCreation = modalMode.value === 'create'
  fermerModal()
  await Promise.all([chargerConsultations(currentPage.value), chargerStats()])
  success(estCreation ? 'Consultation créée avec succès.' : 'Consultation modifiée avec succès.')
}

// --- Modal confirmation suppression ---

const confirmModalOuverte = ref(false)
const confirmCible = ref(null)
const confirmEnCours = ref(false)

function demanderSuppression(item) {
  confirmCible.value = item
  confirmModalOuverte.value = true
}

function fermerConfirmModal() {
  if (confirmEnCours.value) return
  confirmModalOuverte.value = false
  confirmCible.value = null
}

async function executerSuppression() {
  const item = confirmCible.value
  confirmEnCours.value = true

  try {
    await api.delete(`/consultations/${item.id_consultation}`)
    success('Consultation supprimée avec succès.')
    confirmModalOuverte.value = false
    confirmCible.value = null
    await Promise.all([chargerConsultations(currentPage.value), chargerStats()])
  } catch (error) {
    notifierErreur(messageErreur(error, 'Impossible de supprimer cette consultation.'))
  } finally {
    confirmEnCours.value = false
  }
}

// --- Ordonnance (hors scope SCRUM-38, place-holders) ---

function createOrdonnance(item) {
  console.log('Nouvelle ordonnance', item)
}

function printOrdonnance(item) {
  console.log('Imprimer ordonnance', item)
}

function downloadOrdonnance(item) {
  console.log('Télécharger ordonnance', item)
}

/* =========================================================
   GSAP
========================================================= */

onMounted(async () => {
  await Promise.all([chargerConsultations(1), chargerStats()])

  await nextTick()

  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })

  tl.from('.consultations-heading', { y: 18, opacity: 0, duration: 0.55 })
    .from('.consultation-stat-card', { y: 22, opacity: 0, scale: 0.97, stagger: 0.08, duration: 0.5 }, '-=0.25')
    .from('.consultation-filter-card', { y: 18, opacity: 0, duration: 0.45 }, '-=0.25')
    .from('.consultation-table-card', { y: 20, opacity: 0, duration: 0.5 }, '-=0.2')
})
</script>
<template>

  <section class="consultations-page">

    <!-- HEADER -->
    <div class="consultations-heading">
      <div>
        <h1>Gestion des consultations</h1>
        <p class="consultation-subtitle">Consultez et gérez les consultations des patients</p>
      </div>

      <button
        v-if="peutAction('consultations.creer')"
        class="new-consultation-btn"
        @click="createConsultation"
      >
        <i class="fi fi-rr-plus"></i>
        Nouvelle consultation
      </button>
    </div>

    <!-- STATS -->
    <div class="consultation-stats">
      <article class="consultation-stat-card">
        <div class="consultation-stat-icon blue">
          <i class="fi fi-rr-stethoscope"></i>
        </div>
        <div>
          <strong>{{ stats.total_consultations }}</strong>
          <span>Consultations</span>
        </div>
      </article>

      <article class="consultation-stat-card">
        <div class="consultation-stat-icon green">
          <i class="fi fi-rr-users"></i>
        </div>
        <div>
          <strong>{{ stats.patients_consultes }}</strong>
          <span>Patients consultés</span>
        </div>
      </article>

      <article class="consultation-stat-card">
        <div class="consultation-stat-icon purple">
          <i class="fi fi-rr-user-md"></i>
        </div>
        <div>
          <strong>{{ stats.medecins_actifs }}</strong>
          <span>Médecins</span>
        </div>
      </article>

      <article class="consultation-stat-card">
        <div class="consultation-stat-icon orange">
          <i class="fi fi-rr-calendar"></i>
        </div>
        <div>
          <strong>{{ stats.consultations_ce_mois }}</strong>
          <span>Ce mois-ci</span>
        </div>
      </article>
    </div>

    <!-- FILTERS -->
    <div class="consultation-filter-card">
      <div class="consultation-search">
        <i class="fi fi-rr-search"></i>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par patient, médecin ou diagnostic..."
          @keyup.enter="chargerConsultations(1)"
        />
      </div>

      <select v-model="selectedDoctor" @change="chargerConsultations(1)">
        <option value="">Tous les médecins</option>
        <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
          {{ doctor.nom }}
        </option>
      </select>

      <input v-model="selectedDate" type="date" @change="chargerConsultations(1)" />

      <select v-model="selectedStatus" @change="chargerConsultations(1)">
        <option value="">Tous les statuts</option>
        <option value="Terminé">Terminée</option>
        <option value="En cours">En cours</option>
        <option value="Annulé">Annulée</option>
      </select>

      <button class="filter-icon-btn" title="Filtres" @click="chargerConsultations(1)">
        <i class="fi fi-rr-filter"></i>
      </button>

      <button class="reset-consultation-btn" @click="resetFilters">
        <i class="fi fi-rr-refresh"></i>
        Réinitialiser
      </button>
    </div>

    <!-- TABLE -->
    <div class="consultation-table-card">

      <div v-if="loading" class="consultation-empty">Chargement...</div>

      <div v-else class="consultation-table-wrapper">
        <table class="consultation-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Patient</th>
              <th>Médecin</th>
              <th>Date et heure</th>
              <th>Motif de consultation</th>
              <th>Diagnostic</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in consultations" :key="item.id_consultation">
              <td class="consultation-number">{{ item.id_consultation }}</td>

              <!-- PATIENT -->
              <td>
                <div class="consultation-person">
                  <div class="consultation-avatar">
                    {{ initiales(item.rendez_vous?.patient?.nom, item.rendez_vous?.patient?.prenom) }}
                  </div>
                  <div>
                    <strong>{{ item.rendez_vous?.patient?.nom }} {{ item.rendez_vous?.patient?.prenom }}</strong>
                    <span>{{ item.rendez_vous?.patient?.cin }}</span>
                  </div>
                </div>
              </td>

              <!-- MEDECIN -->
              <td>
                <div class="consultation-doctor">
                  <strong>Dr. {{ item.rendez_vous?.medecin?.nom }} {{ item.rendez_vous?.medecin?.prenom }}</strong>
                  <span>{{ item.rendez_vous?.medecin?.specialite?.nom_specialite }}</span>
                </div>
              </td>

              <!-- DATE -->
              <td>
                <div class="consultation-date">
                  <strong>{{ item.rendez_vous?.date_rendez_vous }}</strong>
                  <span>{{ (item.rendez_vous?.heure_debut || '').slice(0, 5) }}</span>
                </div>
              </td>

              <!-- MOTIF -->
              <td>{{ item.motif }}</td>

              <!-- DIAGNOSTIC -->
              <td>{{ item.diagnostic }}</td>

              <!-- STATUS -->
              <td>
                <span class="consultation-status" :class="statusClass(item.rendez_vous?.statut)">
                  {{ item.rendez_vous?.statut }}
                </span>
              </td>

              <!-- ACTIONS -->
              <td>
                <div class="consultation-actions">
                  <button title="Voir" class="consultation-action-btn" @click="viewConsultation(item)">
                    <i class="fi fi-rr-eye"></i>
                  </button>

                  <button
                    v-if="peutAction('consultations.modifier')"
                    title="Modifier"
                    class="consultation-action-btn"
                    @click="editConsultation(item)"
                  >
                    <i class="fi fi-rr-pencil"></i>
                  </button>

                  <button
                    v-if="peutAction('consultations.supprimer')"
                    title="Supprimer"
                    class="consultation-action-btn delete"
                    @click="demanderSuppression(item)"
                  >
                    <i class="fi fi-rr-trash"></i>
                  </button>

                  <button v-if="peutAction('ordonnances.creer')" title="Créer ordonnance" class="consultation-action-btn" @click="createOrdonnance(item)">
                    <i class="fi fi-rr-document"></i>
                  </button>

                  <button v-if="peutAction('ordonnances.telecharger')" title="Imprimer ordonnance" class="consultation-action-btn" @click="printOrdonnance(item)">
                    <i class="fi fi-rr-print"></i>
                  </button>

                  <button v-if="peutAction('ordonnances.telecharger')" title="Télécharger ordonnance" class="consultation-action-btn" @click="downloadOrdonnance(item)">
                    <i class="fi fi-rr-download"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!loading && consultations.length === 0">
              <td colspan="8" class="consultation-empty">Aucune consultation trouvée.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <div class="consultation-table-footer">
        <span>
          Affichage de {{ from }} à {{ to }} sur {{ total }} résultats
        </span>

        <div class="consultation-pagination">
          <button :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
          <button
            v-for="page in lastPage"
            :key="page"
            :class="{ active: currentPage === page }"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button :disabled="currentPage === lastPage" @click="goToPage(currentPage + 1)">›</button>
        </div>
      </div>
    </div>

    <!-- TODO SCRUM-614-style : ConsultationConfirmModal pour remplacer
         window.confirm(), une fois le composant cree sur le modele de
         RendezVousConfirmModal. Pour l'instant la suppression declenche
         directement executerSuppression() via confirmModalOuverte. -->
    <ConsultationFormModal
      v-if="modalOuverte"
      :mode="modalMode"
      :consultation="consultationSelectionnee"
      @close="fermerModal"
      @saved="sauvegarde"
    />

    <ConsultationConfirmModal
      :open="confirmModalOuverte"
      :loading="confirmEnCours"
      title="Supprimer la consultation ?"
      :message="`Voulez-vous vraiment supprimer la consultation de ${confirmCible?.rendez_vous?.patient?.nom ?? ''} ${confirmCible?.rendez_vous?.patient?.prenom ?? ''} ?`"
      warning-text="Cette action est irréversible."
      confirm-label="Supprimer"
      @close="fermerConfirmModal"
      @confirm="executerSuppression"
    />
  </section>
</template>