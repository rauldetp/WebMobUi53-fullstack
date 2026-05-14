<script setup>
  import { usePollStore } from '@/stores/usePollStore';

  const emit = defineEmits(['create', 'edit']);
  const { polls, deletePoll, startPoll } = usePollStore();

  function getPollStatus(poll) {
    if (poll.is_draft) return { label: 'Brouillon', color: '#6b7280' };
    if (poll.ends_at && new Date(poll.ends_at) < new Date()) return { label: 'Expiré', color: '#ef4444' };
    return { label: 'Actif', color: '#22c55e' };
  }

  function getShareLink(poll) {
    return window.location.origin + '/polls/' + poll.secret_token;
  }

  async function copyLink(poll) {
    await navigator.clipboard.writeText(getShareLink(poll));
  }

  async function handleStart(poll) {
    try {
      await startPoll(poll.id);
    } catch (err) {
      alert(err?.data?.message ?? 'Impossible de démarrer le sondage.');
    }
  }

  async function handleDelete(poll) {
    if (!confirm(`Supprimer "${poll.title || poll.question}" ?`)) return;
    try {
      await deletePoll(poll.id);
    } catch (err) {
      alert('Impossible de supprimer le sondage.');
    }
  }
</script>

<template>
  <div class="p-4 max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Mes sondages</h1>
      <button @click="$emit('create')" class="btn-primary">Nouveau sondage</button>
    </div>

    <p v-if="polls.length === 0" class="text-gray-500">Aucun sondage pour l'instant.</p>

    <div v-else class="flex flex-col gap-3">
      <div
        v-for="poll in polls"
        :key="poll.id"
        class="border rounded-lg p-4 flex flex-col gap-3"
      >
        <div class="flex justify-between items-start gap-2">
          <div>
            <p class="font-semibold">{{ poll.title || poll.question }}</p>
            <p v-if="poll.title" class="text-sm text-gray-500 mt-0.5">{{ poll.question }}</p>
          </div>
          <span class="text-sm font-medium shrink-0" :style="{ color: getPollStatus(poll).color }">
            {{ getPollStatus(poll).label }}
          </span>
        </div>

        <p v-if="poll.ends_at" class="text-xs text-gray-400">
          Expire le {{ new Date(poll.ends_at).toLocaleString('fr-CH') }}
        </p>

        <div class="flex gap-2 flex-wrap">
          <button @click="$emit('edit', poll)" class="btn-secondary">Modifier</button>
          <button v-if="poll.is_draft" @click="handleStart(poll)" class="btn-primary">Démarrer</button>
          <button @click="copyLink(poll)" class="btn-secondary">Copier le lien</button>
          <button @click="handleDelete(poll)" class="btn-danger">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
  .btn-primary {
    background-color: #3b82f6;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-secondary {
    background-color: #e5e7eb;
    color: #374151;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-danger {
    background-color: #ef4444;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
</style>
