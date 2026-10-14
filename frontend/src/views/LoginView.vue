<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api, { messageErreur } from '../api'
import { resolveRedirection } from '../router/guards'
import { saveSession } from '../auth.js'

const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const mfaCode = ref('')
const mfaChallenge = ref('')
const mfaRequired = ref(false)
const showPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const terminerConnexion = (data) => {
  saveSession({
    token: data.token,
    user: data.user
  })

  successMessage.value = data?.message || 'Connexion réussie'
  router.push(resolveRedirection(route.query.redirect))
}

const login = async () => {
  errorMessage.value = ''
  successMessage.value = ''
  loading.value = true

  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value
    })

    if (response.status === 202 && response.data?.mfa_required) {
      mfaChallenge.value = response.data.challenge_id
      mfaRequired.value = true
      password.value = ''
      successMessage.value = response.data?.message || 'Code de sécurité envoyé.'
      return
    }

    terminerConnexion(response.data)
  } catch (error) {
    if (error.response?.status === 401) {
      errorMessage.value = 'Email ou mot de passe incorrect.'
    } else {
      errorMessage.value = messageErreur(
        error,
        'Une erreur est survenue lors de la connexion.'
      )
    }
  } finally {
    loading.value = false
  }
}

const verifierMfa = async () => {
  errorMessage.value = ''
  successMessage.value = ''
  loading.value = true

  try {
    const response = await api.post('/login/mfa', {
      challenge_id: mfaChallenge.value,
      code: mfaCode.value
    })

    terminerConnexion(response.data)
  } catch (error) {
    errorMessage.value = messageErreur(
      error,
      'Le code de sécurité est invalide ou expiré.'
    )
  } finally {
    loading.value = false
  }
}

const recommencer = () => {
  mfaRequired.value = false
  mfaChallenge.value = ''
  mfaCode.value = ''
  errorMessage.value = ''
  successMessage.value = ''
}
</script>
<template>
  <main class="login-page">
    <section class="visual-side">
      <img src="/images/login.jpg" alt="Réception VITALIS" class="reception-image" />
    </section>
    <div class="diagonal-line"></div>
    <section class="form-side">
      <div class="login-form">
        <h1>Connexion</h1>
        <p class="subtitle">Connectez-vous à votre compte</p>
        <div v-if="errorMessage" class="message error-message">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="message success-message">
          {{ successMessage }}
        </div>
        <form v-if="!mfaRequired" @submit.prevent="login">
          <div class="field">
            <label for="email">Email</label>
            <div class="input-wrapper">
              <span class="input-icon">✉</span>
              <input
                id="email"
                v-model.trim="email"
                type="email"
                placeholder="votre.email@example.com"
                autocomplete="email"
                required
              />
            </div>
          </div>
          <div class="field">
            <label for="password">Mot de passe</label>
            <div class="input-wrapper">
              <span class="input-icon">🔒</span>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                autocomplete="current-password"
                required
              />
              <button
                type="button"
                class="password-toggle"
                aria-label="Afficher ou masquer le mot de passe"
                @click="showPassword = !showPassword"
              >
                👁
              </button>
            </div>
          </div>
          <div class="options">
            <span></span>
            <RouterLink to="/forgot-password">
              Mot de passe oublié ?
            </RouterLink>
          </div>
          <button type="submit" class="login-button" :disabled="loading">
            {{ loading ? 'Connexion...' : 'Se connecter' }}
          </button>
        </form>

        <form v-else @submit.prevent="verifierMfa">
          <div class="field">
            <label for="mfa-code">Code de sécurité</label>
            <div class="input-wrapper">
              <span class="input-icon">🔐</span>
              <input
                id="mfa-code"
                v-model.trim="mfaCode"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="000000"
                required
              />
            </div>
          </div>
          <button type="submit" class="login-button" :disabled="loading">
            {{ loading ? 'Vérification...' : 'Vérifier le code' }}
          </button>
          <button type="button" class="password-toggle" @click="recommencer">
            Revenir à la connexion
          </button>
        </form>
        <div class="separator"><span>ou</span></div>
        <p class="contact">
          Vous n'avez pas de compte ?
          <a href="#" @click.prevent>Contacter l'administrateur</a>
        </p>
      </div>
    </section>
  </main>
</template>
<style src="../styles/login.css"></style>