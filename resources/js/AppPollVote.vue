<script setup>
  import { ref, computed, onMounted } from 'vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePolling } from '@/composables/usePolling';

  const props = defineProps({
    token:           { type: String, required: true },
    isAuthenticated: { type: Boolean, default: false },
    loginUrl:        { type: String, default: null },
  });

  const { fetchApi } = useFetchApi();

  const pollData        = ref(null);
  const loading         = ref(true);
  const fetchError      = ref(null);
  const selectedOptions = ref([]);
  const voteError       = ref(null);
  const voteLoading     = ref(false);
  const hasVoted        = ref(false);

  const poll = computed(() => pollData.value?.poll ?? null);

  const isExpired = computed(() => {
    if (!poll.value?.ends_at) return false;
    return new Date(poll.value.ends_at) < new Date();
  });

  const canVote = computed(() => {
    if (!props.isAuthenticated) return false;
    if (!poll.value) return false;
    if (poll.value.is_draft) return false;
    if (isExpired.value) return false;
    if (hasVoted.value) return false;
    return true;
  });

  const totalVotes = computed(() => {
    if (!pollData.value?.show_results || !poll.value) return 0;
    return poll.value.options.reduce((sum, o) => sum + (o.votes_count ?? 0), 0);
  });

  function getPercentage(option) {
    if (totalVotes.value === 0) return 0;
    return Math.round(((option.votes_count ?? 0) / totalVotes.value) * 100);
  }

  async function fetchPollData() {
    try {
      const data = await fetchApi({ url: `polls/${props.token}`, timeout: 8000 });
      pollData.value = data;
      if (data.user_vote_option_ids?.length > 0) {
        hasVoted.value = true;
        selectedOptions.value = data.user_vote_option_ids;
      }
    } catch (err) {
      fetchError.value = err?.data?.message ?? 'Sondage introuvable.';
    } finally {
      loading.value = false;
    }
  }

  async function refreshResults() {
    if (!pollData.value?.show_results) return;
    try {
      const data = await fetchApi({ url: `polls/${props.token}`, timeout: 8000 });
      pollData.value = data;
    } catch {
      // On ignore les erreurs de polling silencieusement
    }
  }

  function toggleOption(id) {
    if (!poll.value.allow_multiple_choices) {
      selectedOptions.value = [id];
      return;
    }
    const index = selectedOptions.value.indexOf(id);
    if (index === -1) {
      selectedOptions.value.push(id);
    } else {
      selectedOptions.value.splice(index, 1);
    }
  }

  async function submitVote() {
    if (selectedOptions.value.length === 0) {
      voteError.value = 'Sélectionnez au moins une option.';
      return;
    }

    voteError.value = null;
    voteLoading.value = true;

    try {
      await fetchApi({
        url:    `polls/${props.token}/vote`,
        method: 'POST',
        data:   { option_ids: selectedOptions.value },
      });
      hasVoted.value = true;
      await refreshResults();
    } catch (err) {
      voteError.value = err?.data?.message ?? 'Impossible de soumettre le vote.';
    } finally {
      voteLoading.value = false;
    }
  }

  onMounted(fetchPollData);
  usePolling(refreshResults, 5000);
</script>

<template>
  <div class="p-4 max-w-lg mx-auto">

    <div v-if="loading" class="text-gray-500">Chargement...</div>

    <div v-else-if="fetchError" class="text-red-500">{{ fetchError }}</div>

    <div v-else-if="poll">

      <h1 class="text-2xl font-bold mb-1">{{ poll.title || poll.question }}</h1>
      <p v-if="poll.title" class="text-gray-600 mb-4">{{ poll.question }}</p>

      <!-- Statut expiré -->
      <div v-if="isExpired" class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4 text-sm">
        Ce sondage est terminé.
      </div>

      <!-- Statut brouillon -->
      <div v-else-if="poll.is_draft" class="bg-gray-50 border border-gray-200 text-gray-600 rounded p-3 mb-4 text-sm">
        Ce sondage n'est pas encore ouvert.
      </div>

      <!-- Formulaire de vote -->
      <div v-if="canVote" class="mb-6">
        <p class="text-sm font-medium mb-3">
          {{ poll.allow_multiple_choices ? 'Plusieurs choix possibles' : 'Un seul choix' }}
        </p>

        <div class="flex flex-col gap-2 mb-4">
          <label
            v-for="option in poll.options"
            :key="option.id"
            class="flex items-center gap-3 border rounded p-3 cursor-pointer hover:bg-gray-50"
          >
            <input
              :type="poll.allow_multiple_choices ? 'checkbox' : 'radio'"
              :checked="selectedOptions.includes(option.id)"
              @change="toggleOption(option.id)"
            />
            <span>{{ option.label }}</span>
          </label>
        </div>

        <p v-if="voteError" class="text-red-500 text-sm mb-2">{{ voteError }}</p>

        <button @click="submitVote" :disabled="voteLoading" class="btn-primary">
          {{ voteLoading ? 'Envoi...' : 'Voter' }}
        </button>
      </div>

      <!-- Message déjà voté -->
      <div v-else-if="hasVoted && !isExpired" class="bg-green-50 border border-green-200 text-green-700 rounded p-3 mb-4 text-sm">
        Votre vote a été pris en compte.
      </div>

      <!-- Message non connecté -->
      <div v-else-if="!isAuthenticated && !poll.is_draft && !isExpired" class="bg-blue-50 border border-blue-200 text-blue-700 rounded p-3 mb-4 text-sm">
        <a :href="loginUrl" class="underline font-medium">Connectez-vous</a> pour voter.
      </div>

      <!-- Résultats -->
      <div v-if="pollData.show_results">
        <h2 class="text-lg font-semibold mb-3">Résultats <span class="text-sm text-gray-400 font-normal">({{ totalVotes }} vote{{ totalVotes > 1 ? 's' : '' }})</span></h2>

        <div class="flex flex-col gap-3">
          <div v-for="option in poll.options" :key="option.id">
            <div class="flex justify-between text-sm mb-1">
              <span>{{ option.label }}</span>
              <span class="text-gray-500">{{ getPercentage(option) }}% ({{ option.votes_count ?? 0 }})</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-3">
              <div
                class="bg-blue-500 h-3 rounded-full transition-all duration-500"
                :style="{ width: getPercentage(option) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Résultats non publics -->
      <div v-else-if="!pollData.show_results && !poll.is_draft" class="text-gray-400 text-sm mt-4">
        Les résultats de ce sondage ne sont pas publics.
      </div>

    </div>
  </div>
</template>

<style scoped>
  .btn-primary {
    background-color: #3b82f6;
    color: white;
    padding: 0.5rem 1.25rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
</style>
