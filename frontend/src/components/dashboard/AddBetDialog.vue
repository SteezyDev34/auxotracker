<template>
  <Dialog v-model:visible="dialogVisible" modal header="Ajouter un pari" :style="{ width: '90vw', maxWidth: '600px' }" @hide="closeDialog">
    <AddBetForm @bet-created="onBetCreated" @closeDialog="closeDialog" />
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import AddBetForm from '@/components/add-bet/AddBetForm.vue';

const props = defineProps({
  visible: Boolean
});
const emit = defineEmits(['update:visible', 'bet-created']);

const dialogVisible = ref(props.visible);

watch(() => props.visible, (val) => {
  dialogVisible.value = val;
});

function closeDialog() {
  dialogVisible.value = false;
  emit('update:visible', false);
}

function onBetCreated(bet) {
  emit('bet-created', bet);
  closeDialog();
}
</script>
