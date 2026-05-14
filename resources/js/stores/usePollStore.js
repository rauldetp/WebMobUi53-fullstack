import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

const polls = ref([]);

export function usePollStore() {
  const { fetchApi } = useFetchApi();

  function setPolls(data) {
    polls.value = data;
  }

  async function createPoll(data) {
    const poll = await fetchApi({ url: 'polls', data, method: 'POST' });
    polls.value.unshift(poll);
    return poll;
  }

  async function updatePoll(id, data) {
    const poll = await fetchApi({ url: `polls/${id}`, data, method: 'PUT' });
    const index = polls.value.findIndex(p => p.id === id);
    if (index !== -1) polls.value[index] = { ...polls.value[index], ...poll };
    return poll;
  }

  async function startPoll(id) {
    const poll = await fetchApi({ url: `polls/${id}/start`, method: 'POST' });
    const index = polls.value.findIndex(p => p.id === id);
    if (index !== -1) polls.value[index] = { ...polls.value[index], ...poll };
    return poll;
  }

  async function deletePoll(id) {
    await fetchApi({ url: `polls/${id}`, method: 'DELETE' });
    polls.value = polls.value.filter(p => p.id !== id);
  }

  async function createOption(pollId, label) {
    const option = await fetchApi({ url: `polls/${pollId}/options`, data: { label }, method: 'POST' });
    const poll = polls.value.find(p => p.id === pollId);
    if (poll) poll.options = [...(poll.options ?? []), option];
    return option;
  }

  async function updateOption(pollId, optionId, label) {
    return await fetchApi({ url: `polls/${pollId}/options/${optionId}`, data: { label }, method: 'PUT' });
  }

  async function deleteOption(pollId, optionId) {
    await fetchApi({ url: `polls/${pollId}/options/${optionId}`, method: 'DELETE' });
    const poll = polls.value.find(p => p.id === pollId);
    if (poll) poll.options = poll.options.filter(o => o.id !== optionId);
  }

  return {
    polls,
    setPolls,
    createPoll,
    updatePoll,
    startPoll,
    deletePoll,
    createOption,
    updateOption,
    deleteOption,
  };
}
