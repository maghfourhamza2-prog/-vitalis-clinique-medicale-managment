<template>
  <div class="facturation-page">
    <!-- =========================================================
         HEADER
    ========================================================== -->
    <div class="page-header">
      <div>
        <h1>Facturation</h1>
        <p>Gestion des factures et des paiements</p>
      </div>

      <button
        class="primary-btn header-create-btn"
        type="button"
        @click="openCreateModal"
      >
        <span>+</span>
        Nouvelle facture
      </button>
    </div>

    <!-- =========================================================
         NOTIFICATION
    ========================================================== -->
    <div
      v-if="notification.message"
      class="notification"
      :class="`notification-${notification.type}`"
    >
      {{ notification.message }}
    </div>

    <!-- =========================================================
         STATISTIQUES
    ========================================================== -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-content">
          <span class="stat-label">Total factures</span>
          <strong class="stat-value">{{ factures.length }}</strong>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-content">
          <span class="stat-label">Factures payées</span>
          <strong class="stat-value">{{ nombreFacturesPayees }}</strong>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-content">
          <span class="stat-label">En attente</span>
          <strong class="stat-value">{{ nombreFacturesImpayees }}</strong>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-content">
          <span class="stat-label">Montant encaissé</span>
          <strong class="stat-value">
            {{ formatMontant(totalPaye) }}
          </strong>
        </div>
      </div>
    </div>

    <!-- =========================================================
         FILTRES
    ========================================================== -->
    <div class="facturation-card">
      <div class="filters-section">
        <div class="search-box">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher une facture..."
          />
        </div>

        <div class="filter-group">
          <select v-model="filterStatut">
            <option value="">Tous les statuts</option>
            <option value="impayee">Impayée</option>
            <option value="partielle">Partiellement payée</option>
            <option value="payee">Payée</option>
            <option value="annulee">Annulée</option>
          </select>
        </div>

        <button
          v-if="search || filterStatut"
          type="button"
          class="secondary-btn"
          @click="resetFilters"
        >
          Réinitialiser
        </button>
      </div>
    </div>

    <!-- =========================================================
         LOADING
    ========================================================== -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Chargement des factures...</p>
    </div>

    <!-- =========================================================
         ERREUR
    ========================================================== -->
    <div v-else-if="errorMessage" class="error-container">
      <p>{{ errorMessage }}</p>

      <button
        type="button"
        class="secondary-btn"
        @click="loadData"
      >
        Réessayer
      </button>
    </div>

    <!-- =========================================================
         TABLEAU
    ========================================================== -->
    <div v-else class="facturation-card table-card">
      <div class="table-wrapper">
        <table class="facturation-table">
          <thead>
            <tr>
              <th>Facture</th>
              <th>Date</th>
              <th>Référence</th>
              <th>Montant net</th>
              <th>Payé</th>
              <th>Mode</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="filteredFactures.length === 0">
              <td colspan="8" class="empty-state">
                Aucune facture trouvée.
              </td>
            </tr>

            <tr
              v-for="facture in filteredFactures"
              :key="facture.id"
            >
              <td>
                <div class="invoice-main">
                  <strong>{{ facture.numero }}</strong>
                  <span>#{{ facture.id }}</span>
                </div>
              </td>

              <td>
                {{ formatDate(facture.date) }}
              </td>

              <td>
                <div class="reference-cell">
                  <span v-if="facture.referenceType">
                    {{ facture.referenceType }}
                  </span>

                  <span v-if="facture.reference">
                    #{{ facture.reference }}
                  </span>

                  <span v-if="!facture.reference">
                    —
                  </span>
                </div>
              </td>

              <td>
                <strong>
                  {{ formatMontant(facture.montant) }}
                </strong>
              </td>

              <td>
                {{ formatMontant(facture.paye) }}
              </td>

              <td>
                {{ facture.modePaiement || '—' }}
              </td>

              <td>
                <span
                  class="status-badge"
                  :class="getStatusClass(facture.statut)"
                >
                  {{ getStatusLabel(facture.statut) }}
                </span>
              </td>

              <td>
                <div class="actions-cell">
                  <button
                    type="button"
                    class="action-btn"
                    title="Voir"
                    @click="openViewModal(facture)"
                  >
                    Voir
                  </button>

                  <button
                    v-if="peutAction('factures.modifier')"
                    type="button"
                    class="action-btn"
                    title="Modifier"
                    @click="openEditModal(facture)"
                  >
                    Modifier
                  </button>

                  <button
                    v-if="facture.statut !== 'annulee' && peutAction('paiements.creer')"
                    type="button"
                    class="action-btn"
                    title="Ajouter un paiement"
                    @click="openPaymentModal(facture)"
                  >
                    Paiement
                  </button>

                  <button
                    v-if="facture.statut !== 'annulee' && peutAction('factures.annuler')"
                    type="button"
                    class="action-btn danger-action"
                    title="Annuler"
                    @click="cancelFacture(facture)"
                  >
                    Annuler
                  </button>

                  <button
                    v-if="peutAction('factures.supprimer')"
                    type="button"
                    class="action-btn danger-action"
                    title="Supprimer"
                    @click="openDeleteModal(facture)"
                  >
                    Supprimer
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =========================================================
         MODAL CREATION / MODIFICATION
    ========================================================== -->
    <div
      v-if="showFormModal"
      class="modal-overlay"
      @click.self="closeFormModal"
    >
      <div class="modal-container">
        <div class="modal-header">
          <div>
            <h2>
              {{ isEditing ? 'Modifier la facture' : 'Nouvelle facture' }}
            </h2>
            <p>
              {{ isEditing
                ? 'Modifier les informations de la facture'
                : 'Créer une nouvelle facture'
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

        <form
          class="modal-body"
          @submit.prevent="saveFacture"
        >
          <div class="form-grid">
            <!-- NUMERO -->
            <div class="form-group">
              <label for="numero_facture">
                Numéro de facture *
              </label>

              <input
                id="numero_facture"
                v-model.trim="form.numero_facture"
                type="text"
                required
                placeholder="Ex: FAC-2026-001"
              />
            </div>

            <!-- DATE -->
            <div class="form-group">
              <label for="date_facture">
                Date de facture *
              </label>

              <input
                id="date_facture"
                v-model="form.date_facture"
                type="date"
                required
              />
            </div>

            <!-- REMISE -->
            <div class="form-group">
              <label for="remise">
                Remise
              </label>

              <input
                id="remise"
                v-model.number="form.remise"
                type="number"
                min="0"
                step="0.01"
                placeholder="0"
              />
            </div>

            <!-- STATUT -->
            <div class="form-group">
              <label for="statut_paiement">
                Statut *
              </label>

              <select
                id="statut_paiement"
                v-model="form.statut_paiement"
                required
              >
                <option value="impayee">Impayée</option>
                <option value="partielle">Partiellement payée</option>
                <option value="payee">Payée</option>
                <option value="annulee">Annulée</option>
              </select>
            </div>

            <!-- CONSULTATION -->
            <div class="form-group">
              <label for="id_consultation">
                ID Consultation
              </label>

              <input
                id="id_consultation"
                v-model="form.id_consultation"
                type="number"
                min="1"
                placeholder="ID de la consultation"
              />

              <small>
                Laisser vide si la facture concerne une hospitalisation.
              </small>
            </div>

            <!-- HOSPITALISATION -->
            <div class="form-group">
              <label for="id_hospitalisation">
                ID Hospitalisation
              </label>

              <input
                id="id_hospitalisation"
                v-model="form.id_hospitalisation"
                type="number"
                min="1"
                placeholder="ID de l'hospitalisation"
              />

              <small>
                Laisser vide si la facture concerne une consultation.
              </small>
            </div>

            <!-- OBSERVATIONS -->
            <div class="form-group full-width">
              <label for="observations">
                Observations
              </label>

              <textarea
                id="observations"
                v-model="form.observations"
                rows="4"
                placeholder="Observations..."
              ></textarea>
            </div>
          </div>

          <div
            v-if="formError"
            class="form-error"
          >
            {{ formError }}
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="secondary-btn"
              @click="closeFormModal"
            >
              Annuler
            </button>

            <button
              v-if="peutAction(isEditing ? 'factures.modifier' : 'factures.creer')"
              type="submit"
              class="primary-btn"
              :disabled="saving"
            >
              {{ saving
                ? 'Enregistrement...'
                : (isEditing ? 'Enregistrer' : 'Créer la facture')
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =========================================================
         MODAL PAIEMENT
    ========================================================== -->
    <div
      v-if="showPaymentModal"
      class="modal-overlay"
      @click.self="closePaymentModal"
    >
      <div class="modal-container modal-small">
        <div class="modal-header">
          <div>
            <h2>Ajouter un paiement</h2>

            <p v-if="paymentFacture">
              Facture :
              <strong>{{ paymentFacture.numero }}</strong>
            </p>
          </div>

          <button
            type="button"
            class="modal-close"
            @click="closePaymentModal"
          >
            ×
          </button>
        </div>

        <form
          class="modal-body"
          @submit.prevent="savePayment"
        >
          <div
            v-if="paymentFacture"
            class="payment-summary"
          >
            <div>
              <span>Montant net</span>
              <strong>
                {{ formatMontant(paymentFacture.montant) }}
              </strong>
            </div>

            <div>
              <span>Déjà payé</span>
              <strong>
                {{ formatMontant(paymentFacture.paye) }}
              </strong>
            </div>

            <div>
              <span>Reste</span>
              <strong>
                {{ formatMontant(paymentFacture.reste) }}
              </strong>
            </div>
          </div>

          <div class="form-group">
            <label for="montant_paye">
              Montant du paiement *
            </label>

            <input
              id="montant_paye"
              v-model.number="paymentForm.montant_paye"
              type="number"
              min="0.01"
              step="0.01"
              required
              :max="paymentFacture?.reste || undefined"
              placeholder="0.00"
            />
          </div>

          <div class="form-group">
            <label for="mode_paiement">
              Mode de paiement *
            </label>

            <select
              id="mode_paiement"
              v-model="paymentForm.mode_paiement"
              required
            >
              <option value="especes">Espèces</option>
              <option value="carte_bancaire">
                Carte bancaire
              </option>
              <option value="virement">
                Virement
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="payment_observations">
              Observations
            </label>

            <textarea
              id="payment_observations"
              v-model="paymentForm.observations"
              rows="3"
              placeholder="Observations du paiement..."
            ></textarea>
          </div>

          <div
            v-if="paymentError"
            class="form-error"
          >
            {{ paymentError }}
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="secondary-btn"
              @click="closePaymentModal"
            >
              Annuler
            </button>

            <button
              v-if="peutAction('paiements.creer')"
              type="submit"
              class="primary-btn"
              :disabled="savingPayment"
            >
              {{ savingPayment
                ? 'Enregistrement...'
                : 'Enregistrer le paiement'
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =========================================================
         MODAL DETAILS
    ========================================================== -->
    <div
      v-if="showViewModal"
      class="modal-overlay"
      @click.self="closeViewModal"
    >
      <div class="modal-container">
        <div class="modal-header">
          <div>
            <h2>Détails de la facture</h2>

            <p v-if="selectedFacture">
              {{ selectedFacture.numero }}
            </p>
          </div>

          <button
            type="button"
            class="modal-close"
            @click="closeViewModal"
          >
            ×
          </button>
        </div>

        <div
          v-if="selectedFacture"
          class="modal-body"
        >
          <div class="details-grid">
            <div class="detail-item">
              <span>Numéro</span>
              <strong>{{ selectedFacture.numero }}</strong>
            </div>

            <div class="detail-item">
              <span>Date</span>
              <strong>
                {{ formatDate(selectedFacture.date) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Patient</span>
              <strong>
                {{ selectedFacture.patient }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Référence</span>
              <strong>
                {{ selectedFacture.reference || '—' }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Montant total</span>
              <strong>
                {{ formatMontant(selectedFacture.montantTotal) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Remise</span>
              <strong>
                {{ formatMontant(selectedFacture.remise) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Montant net</span>
              <strong>
                {{ formatMontant(selectedFacture.montant) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Montant payé</span>
              <strong>
                {{ formatMontant(selectedFacture.paye) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Reste</span>
              <strong>
                {{ formatMontant(selectedFacture.reste) }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Mode de paiement</span>
              <strong>
                {{ selectedFacture.modePaiement || '—' }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Statut</span>
              <strong>
                {{ getStatusLabel(selectedFacture.statut) }}
              </strong>
            </div>
          </div>

          <div class="detail-description">
            <span>Observations</span>

            <p>
              {{ selectedFacture.observations || 'Aucune observation.' }}
            </p>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="secondary-btn"
              @click="closeViewModal"
            >
              Fermer
            </button>

            <button
              v-if="
                selectedFacture.statut !== 'annulee' &&
                selectedFacture.reste > 0
              "
              type="button"
              class="primary-btn"
              @click="openPaymentModal(selectedFacture)"
            >
              Ajouter un paiement
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- =========================================================
         MODAL SUPPRESSION
    ========================================================== -->
    <div
      v-if="showDeleteModal"
      class="modal-overlay"
      @click.self="closeDeleteModal"
    >
      <div class="modal-container modal-small">
        <div class="modal-header">
          <div>
            <h2>Supprimer la facture</h2>
            <p>Cette action est irréversible.</p>
          </div>

          <button
            type="button"
            class="modal-close"
            @click="closeDeleteModal"
          >
            ×
          </button>
        </div>

        <div class="modal-body">
          <p>
            Voulez-vous vraiment supprimer la facture
            <strong>
              {{ factureToDelete?.numero }}
            </strong>
            ?
          </p>

          <div
            v-if="deleteError"
            class="form-error"
          >
            {{ deleteError }}
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="secondary-btn"
              @click="closeDeleteModal"
            >
              Annuler
            </button>

            <button
              v-if="peutAction('factures.supprimer')"
              type="button"
              class="danger-btn"
              :disabled="deleting"
              @click="deleteFacture"
            >
              {{ deleting ? 'Suppression...' : 'Supprimer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { peutAction } from '../../actions.js'
import { computed, onMounted, ref } from 'vue'
import api, { messageErreur } from '../../api'

/* ============================================================
   DONNEES
============================================================ */

const factures = ref([])
const paiements = ref([])

const loading = ref(false)
const saving = ref(false)
const savingPayment = ref(false)
const deleting = ref(false)

const errorMessage = ref('')
const formError = ref('')
const paymentError = ref('')
const deleteError = ref('')

const search = ref('')
const filterStatut = ref('')

const notification = ref({
  message: '',
  type: 'success'
})

/* ============================================================
   MODALS
============================================================ */

const showFormModal = ref(false)
const showPaymentModal = ref(false)
const showViewModal = ref(false)
const showDeleteModal = ref(false)

const isEditing = ref(false)

const selectedFacture = ref(null)
const paymentFacture = ref(null)
const factureToDelete = ref(null)

/* ============================================================
   FORMULAIRE FACTURE
============================================================ */

const defaultForm = () => ({
  numero_facture: '',
  date_facture: getToday(),
  remise: 0,
  statut_paiement: 'impayee',
  observations: '',
  id_consultation: '',
  id_hospitalisation: ''
})

const form = ref(defaultForm())

/* ============================================================
   FORMULAIRE PAIEMENT
============================================================ */

const defaultPaymentForm = () => ({
  montant_paye: '',
  mode_paiement: 'especes',
  observations: ''
})

const paymentForm = ref(defaultPaymentForm())

/* ============================================================
   COMPUTED
============================================================ */

const filteredFactures = computed(() => {
  const terme = search.value.trim().toLowerCase()

  return factures.value.filter((facture) => {
    const correspondRecherche =
      !terme ||
      String(facture.numero || '')
        .toLowerCase()
        .includes(terme) ||
      String(facture.id || '')
        .toLowerCase()
        .includes(terme) ||
      String(facture.reference || '')
        .toLowerCase()
        .includes(terme)

    const correspondStatut =
      !filterStatut.value ||
      facture.statut === filterStatut.value

    return correspondRecherche && correspondStatut
  })
})

const nombreFacturesPayees = computed(() => {
  return factures.value.filter(
    (facture) => facture.statut === 'payee'
  ).length
})

const nombreFacturesImpayees = computed(() => {
  return factures.value.filter(
    (facture) => facture.statut === 'impayee'
  ).length
})

const totalPaye = computed(() => {
  return factures.value.reduce(
    (total, facture) => total + Number(facture.paye || 0),
    0
  )
})

/* ============================================================
   CHARGEMENT INITIAL
============================================================ */

onMounted(() => {
  loadData()
})

/* ============================================================
   CHARGER FACTURES + PAIEMENTS
============================================================ */

async function loadData() {
  loading.value = true
  errorMessage.value = ''

  try {
    const [facturesResponse, paiementsResponse] =
      await Promise.all([
        api.get('/factures'),
        api.get('/paiements')
      ])

    const facturesBackend =
      facturesResponse.data?.factures || []

    const paiementsBackend =
      paiementsResponse.data?.paiements || []

    paiements.value = Array.isArray(paiementsBackend)
      ? paiementsBackend
      : []

    factures.value = Array.isArray(facturesBackend)
      ? facturesBackend.map(normalizeFacture)
      : []
  } catch (error) {
    console.error('Erreur chargement facturation:', error)

    errorMessage.value = messageErreur(
      error,
      'Impossible de charger les données de facturation.'
    )
  } finally {
    loading.value = false
  }
}

/* ============================================================
   NORMALISER UNE FACTURE
============================================================ */

function normalizeFacture(facture) {
  const id = facture.id_facture

  const paiementsFacture = paiements.value.filter(
    (paiement) =>
      Number(paiement.id_facture) === Number(id)
  )

  const paiementsValides = paiementsFacture.filter(
    (paiement) => paiement.statut === 'valide'
  )

  const montantPaye = paiementsValides.reduce(
    (total, paiement) =>
      total + Number(paiement.montant_paye || 0),
    0
  )

  const montantNet = Number(facture.montant_net || 0)
  const montantTotal = Number(facture.montant_total || 0)
  const remise = Number(facture.remise || 0)

  const reste = Math.max(
    0,
    montantNet - montantPaye
  )

  let statut = facture.statut_paiement

  /*
   * Le statut backend reste prioritaire.
   * Si le backend ne l'a pas correctement synchronisé,
   * on peut déduire le statut à partir des paiements.
   */
  if (
    statut !== 'annulee' &&
    montantNet > 0
  ) {
    if (montantPaye >= montantNet) {
      statut = 'payee'
    } else if (montantPaye > 0) {
      statut = 'partielle'
    } else {
      statut = 'impayee'
    }
  }

  const dernierPaiement =
    paiementsValides.length > 0
      ? paiementsValides[paiementsValides.length - 1]
      : null

  return {
    id,
    numero: facture.numero_facture,
    date: facture.date_facture,
    montantTotal,
    montant: montantNet,
    remise,
    paye: montantPaye,
    reste,
    statut,
    observations: facture.observations || '',
    patient: 'Patient non chargé',
    reference:
      facture.id_consultation ||
      facture.id_hospitalisation ||
      null,
    referenceType: facture.id_consultation
      ? 'Consultation'
      : facture.id_hospitalisation
        ? 'Hospitalisation'
        : '',
    modePaiement: dernierPaiement
      ? getModePaiementLabel(
          dernierPaiement.mode_paiement
        )
      : '—',

    /*
     * On conserve aussi les champs backend
     * afin de pouvoir modifier la facture.
     */
    raw: facture
  }
}

/* ============================================================
   CREATION
============================================================ */

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  form.value = defaultForm()

  showFormModal.value = true
}

/* ============================================================
   MODIFICATION
============================================================ */

function openEditModal(facture) {
  isEditing.value = true
  formError.value = ''

  const raw = facture.raw || {}

  form.value = {
    numero_facture: raw.numero_facture || '',
    date_facture: formatDateForInput(
      raw.date_facture
    ),
    remise: Number(raw.remise || 0),
    statut_paiement:
      raw.statut_paiement || facture.statut || 'impayee',
    observations: raw.observations || '',
    id_consultation:
      raw.id_consultation || '',
    id_hospitalisation:
      raw.id_hospitalisation || ''
  }

  showFormModal.value = true
}

/* ============================================================
   SAUVEGARDER FACTURE
============================================================ */

async function saveFacture() {
  formError.value = ''

  const consultationId = toNullableInteger(
    form.value.id_consultation
  )

  const hospitalisationId = toNullableInteger(
    form.value.id_hospitalisation
  )

  /*
   * Le backend exige au moins une consultation
   * ou une hospitalisation pour calculer le montant.
   */
  if (!consultationId && !hospitalisationId) {
    formError.value =
      'Veuillez renseigner un ID de consultation ou un ID d’hospitalisation.'
    return
  }

  if (consultationId && hospitalisationId) {
    formError.value =
      'Une facture doit être liée à une consultation ou à une hospitalisation, pas aux deux.'
    return
  }

  const payload = {
    numero_facture:
      form.value.numero_facture,
    date_facture:
      form.value.date_facture,
    remise:
      Number(form.value.remise || 0),
    statut_paiement:
      form.value.statut_paiement,
    observations:
      form.value.observations || null,
    id_consultation:
      consultationId,
    id_hospitalisation:
      hospitalisationId
  }

  saving.value = true

  try {
    if (isEditing.value && selectedFacture.value) {
      await api.put(
        `/factures/${selectedFacture.value.id}`,
        payload
      )

      showNotification(
        'Facture modifiée avec succès.',
        'success'
      )
    } else {
      await api.post(
        '/factures',
        payload
      )

      showNotification(
        'Facture créée avec succès.',
        'success'
      )
    }

    closeFormModal()
    await loadData()
  } catch (error) {
    console.error('Erreur sauvegarde facture:', error)

    formError.value = messageErreur(
      error,
      'Impossible d’enregistrer la facture.'
    )
  } finally {
    saving.value = false
  }
}

/* ============================================================
   PAIEMENT
============================================================ */

function openPaymentModal(facture) {
  if (!facture) return

  selectedFacture.value = null
  paymentError.value = ''

  paymentFacture.value = facture

  paymentForm.value = defaultPaymentForm()

  showViewModal.value = false
  showPaymentModal.value = true
}

async function savePayment() {
  paymentError.value = ''

  if (!paymentFacture.value) {
    paymentError.value =
      'Aucune facture sélectionnée.'
    return
  }

  const montant = Number(
    paymentForm.value.montant_paye
  )

  const reste = Number(
    paymentFacture.value.reste || 0
  )

  if (!montant || montant <= 0) {
    paymentError.value =
      'Le montant du paiement doit être supérieur à 0.'
    return
  }

  if (montant > reste) {
    paymentError.value =
      'Le montant du paiement dépasse le reste à payer.'
    return
  }

  savingPayment.value = true

  try {
    const now = new Date()

    const payload = {
      date_paiement: formatDateForApi(now),
      heure_paiement: formatTimeForApi(now),
      montant_paye: montant,
      mode_paiement:
        paymentForm.value.mode_paiement,
      statut: 'valide',
      observations:
        paymentForm.value.observations || null,
      id_facture:
        paymentFacture.value.id
    }

    await api.post(
      '/paiements',
      payload
    )

    showNotification(
      'Paiement enregistré avec succès.',
      'success'
    )

    closePaymentModal()
    await loadData()
  } catch (error) {
    console.error('Erreur paiement:', error)

    paymentError.value = messageErreur(
      error,
      'Impossible d’enregistrer le paiement.'
    )
  } finally {
    savingPayment.value = false
  }
}

/* ============================================================
   ANNULATION
============================================================ */

async function cancelFacture(facture) {
  if (!facture?.id) return

  const confirmation = window.confirm(
    `Voulez-vous vraiment annuler la facture ${facture.numero} ?`
  )

  if (!confirmation) return

  try {
    await api.patch(
      `/factures/${facture.id}/annuler`
    )

    showNotification(
      'Facture annulée avec succès.',
      'success'
    )

    await loadData()
  } catch (error) {
    console.error('Erreur annulation facture:', error)

    showNotification(
      messageErreur(
        error,
        'Impossible d’annuler la facture.'
      ),
      'error'
    )
  }
}

/* ============================================================
   SUPPRESSION
============================================================ */

function openDeleteModal(facture) {
  factureToDelete.value = facture
  deleteError.value = ''
  showDeleteModal.value = true
}

async function deleteFacture() {
  if (!factureToDelete.value) return

  deleting.value = true
  deleteError.value = ''

  try {
    await api.delete(
      `/factures/${factureToDelete.value.id}`
    )

    showNotification(
      'Facture supprimée avec succès.',
      'success'
    )

    closeDeleteModal()
    await loadData()
  } catch (error) {
    console.error('Erreur suppression facture:', error)

    deleteError.value = messageErreur(
      error,
      'Impossible de supprimer la facture.'
    )
  } finally {
    deleting.value = false
  }
}

/* ============================================================
   DETAILS
============================================================ */

function openViewModal(facture) {
  selectedFacture.value = facture
  showViewModal.value = true
}

/* ============================================================
   FERMETURE MODALS
============================================================ */

function closeFormModal() {
  showFormModal.value = false
  formError.value = ''
}

function closePaymentModal() {
  showPaymentModal.value = false
  paymentError.value = ''
  paymentFacture.value = null
}

function closeViewModal() {
  showViewModal.value = false
  selectedFacture.value = null
}

function closeDeleteModal() {
  showDeleteModal.value = false
  deleteError.value = ''
  factureToDelete.value = null
}

/* ============================================================
   FILTRES
============================================================ */

function resetFilters() {
  search.value = ''
  filterStatut.value = ''
}

/* ============================================================
   STATUTS
============================================================ */

function getStatusLabel(statut) {
  const labels = {
    impayee: 'Impayée',
    partielle: 'Partiellement payée',
    payee: 'Payée',
    annulee: 'Annulée'
  }

  return labels[statut] || statut || '—'
}

function getStatusClass(statut) {
  const classes = {
    impayee: 'status-pending',
    partielle: 'status-partial',
    payee: 'status-paid',
    annulee: 'status-cancelled'
  }

  return classes[statut] || ''
}

/* ============================================================
   MODE DE PAIEMENT
============================================================ */

function getModePaiementLabel(mode) {
  const labels = {
    especes: 'Espèces',
    carte_bancaire: 'Carte bancaire',
    virement: 'Virement'
  }

  return labels[mode] || mode || '—'
}

/* ============================================================
   NOTIFICATION
============================================================ */

function showNotification(message, type = 'success') {
  notification.value = {
    message,
    type
  }

  window.setTimeout(() => {
    notification.value.message = ''
  }, 4000)
}

/* ============================================================
   FORMATAGE
============================================================ */

function formatMontant(value) {
  const montant = Number(value || 0)

  return new Intl.NumberFormat(
    'fr-FR',
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }
  ).format(montant) + ' DH'
}

function formatDate(value) {
  if (!value) return '—'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(
    'fr-FR'
  ).format(date)
}

function formatDateForInput(value) {
  if (!value) return ''

  if (typeof value === 'string') {
    return value.substring(0, 10)
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0')
  ].join('-')
}

function formatDateForApi(date) {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0')
  ].join('-')
}

function formatTimeForApi(date) {
  return [
    String(date.getHours()).padStart(2, '0'),
    String(date.getMinutes()).padStart(2, '0')
  ].join(':')
}

function getToday() {
  return formatDateForApi(new Date())
}

/* ============================================================
   HELPERS
============================================================ */

function toNullableInteger(value) {
  if (
    value === '' ||
    value === null ||
    value === undefined
  ) {
    return null
  }

  const number = Number(value)

  return Number.isInteger(number) && number > 0
    ? number
    : null
}
</script>

<style src="../../styles/facturation.css"></style>