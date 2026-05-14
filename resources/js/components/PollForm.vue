<script setup>
  import { ref, computed, onMounted } from 'vue';
  import { usePollStore } from '@/stores/usePollStore';

  const props = defineProps({
    poll: { type: Object, default: null },
  });

  const emit = defineEmits(['saved', 'cancel']);

  const { createPoll, updatePoll, startPoll, createOption, updateOption, deleteOption } = usePollStore();

  const isEdit = computed(() => props.poll !== null);

  const title            = ref(props.poll?.title ?? '');
  const question         = ref(props.poll?.question ?? '');
  const allowMultiple    = ref(props.poll?.allow_multiple_choices ?? false);
  const allowVoteChange  = ref(props.poll?.allow_vote_change ?? false);
  const resultsPublic    = ref(props.poll?.results_public ?? false);
  const durationMinutes  = ref(props.poll?.duration ? Math.round(props.poll.duration / 60) : null);
  const startNow         = ref(false);

  // Each option: { id: number|null, label: string, originalLabel: string|null }
  const localOptions    = ref([]);
  const deletedOptionIds = ref([]);
  const error           = ref(null);
  const loading         = ref(false);

  onMounted(() => {
    if (props.poll?.options) {
      localOptions.value = props.poll.options.map(o => ({
        id: o.id,
        label: o.label,
        originalLabel: o.label,
      }));
    }
  });

  function addOption() {
    localOptions.value.push({ id: null, label: '', originalLabel: null });
  }

  function removeOption(index) {
    const option = localOptions.value[index];
    if (option.id) deletedOptionIds.value.push(option.id);
    localOptions.value.splice(index, 1);
  }

  async function save() {
    if (!question.value.trim()) {
      error.value = 'La question est obligatoire.';
      return;
    }

    error.value = null;
    loading.value = true;

    try {
      const pollData = {
        title:                  title.value.trim() || null,
        question:               question.value.trim(),
        allow_multiple_choices: allowMultiple.value,
        allow_vote_change:      allowVoteChange.value,
        results_public:         resultsPublic.value,
        duration:               durationMinutes.value ? durationMinutes.value * 60 : null,
      };

      const poll = isEdit.value
        ? await updatePoll(props.poll.id, pollData)
        : await createPoll(pollData);

      for (const id of deletedOptionIds.value) {
        await deleteOption(poll.id, id);
      }

      for (const option of localOptions.value) {
        if (!option.label.trim()) continue;
        if (option.id === null) {
          await createOption(poll.id, option.label.trim());
        } else if (option.label !== option.originalLabel) {
          await updateOption(poll.id, option.id, option.label.trim());
        }
      }

      if (!isEdit.value && startNow.value) {
        await startPoll(poll.id);
      }

      emit('saved');
    } catch (err) {
      error.value = err?.data?.message ?? 'Une erreur est survenue.';
    } finally {
      loading.value = false;
    }
  }
</script>

<template>
  <div class="p-4 max-w-lg mx-auto">
    <h1 class="text-2xl font-bold mb-6">
      {{ isEdit ? 'Modifier le sondage' : 'Nouveau sondage' }}
    </h1>

    <div class="flex flex-col gap-4">

      <div>
        <label class="block text-sm font-medium mb-1">Titre <span class="text-gray-400">(optionnel)</span></label>
        <input v-model="title" type="text" class="input" placeholder="Ex. : Sondage de la semaine" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Question *</label>
        <input v-model="question" type="text" class="input" placeholder="Quelle est votre question ?" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">Options de réponse</label>
        <div class="flex flex-col gap-2">
          <div v-for="(option, index) in localOptions" :key="index" class="flex gap-2">
            <input
              v-model="option.label"
              type="text"
              class="input flex-1"
              :placeholder="`Option ${index + 1}`"
            />
            <button @click="removeOption(index)" class="btn-danger px-3">×</button>
          </div>
        </div>
        <button @click="addOption" class="btn-secondary mt-2">+ Ajouter une option</button>
      </div>

      <div class="border rounded-lg p-3 flex flex-col gap-2">
        <p class="text-sm font-medium mb-1">Paramètres</p>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="allowMultiple" type="checkbox" />
          Autoriser plusieurs choix
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="resultsPublic" type="checkbox" />
          Résultats publics
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="allowVoteChange" type="checkbox" />
          Autoriser la modification du vote
        </label>
        <div class="flex items-center gap-2 text-sm">
          <label>Durée (minutes) :</label>
          <input
            v-model.number="durationMinutes"
            type="number"
            min="1"
            class="input w-24"
            placeholder="Illimitée"
          />
        </div>
      </div>

      <label v-if="!isEdit" class="flex items-center gap-2 text-sm">
        <input v-model="startNow" type="checkbox" />
        Démarrer immédiatement après la création
      </label>

      <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>

      <div class="flex gap-2">
        <button @click="save" :disabled="loading" class="btn-primary">
          {{ loading ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
        <button @click="$emit('cancel')" class="btn-secondary">Annuler</button>
      </div>

    </div>
  </div>
</template>

<style scoped>
  .input {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.375rem 0.5rem;
    width: 100%;
    font-size: 0.875rem;
    outline: none;
  }
  .input:focus {
    border-color: #3b82f6;
  }
  .btn-primary {
    background-color: #3b82f6;
    color: white;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  .btn-secondary {
    background-color: #e5e7eb;
    color: #374151;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-danger {
    background-color: #ef4444;
    color: white;
    padding: 0.375rem 0.5rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
  }
</style>
