<script setup>
  import { ref } from 'vue';
  import PollTable from './components/PollTable.vue';
  import PollForm from './components/PollForm.vue';
  import { usePollStore } from '@/stores/usePollStore';

  const props = defineProps({
    polls: { type: Array, default: () => [] },
    loginUrl: { type: String, default: null },
    username: { type: String, default: null },
  });

  const { setPolls } = usePollStore();
  setPolls(props.polls);

  const view = ref('list');
  const selectedPoll = ref(null);

  function openCreate() {
    selectedPoll.value = null;
    view.value = 'form';
  }

  function openEdit(poll) {
    selectedPoll.value = poll;
    view.value = 'form';
  }

  function backToList() {
    view.value = 'list';
    selectedPoll.value = null;
  }
</script>

<template>
  <PollTable v-if="view === 'list'" @create="openCreate" @edit="openEdit" />
  <PollForm v-else :poll="selectedPoll" @saved="backToList" @cancel="backToList" />
</template>
