<template>
  <Dialog v-model:visible="dialogVisible" modal header="Ajouter un pari" :style="{ width: '90vw', maxWidth: '600px' }" @hide="closeDialog" @show="onDialogShow">
    <AddBetForm ref="addBetFormRef" @bet-created="onBetCreated" @closeDialog="closeDialog" />
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
const addBetFormRef = ref(null);

watch(() => props.visible, (val) => {
  dialogVisible.value = val;
});

/**
 * Gérer l'ouverture de la modal
 */
function onDialogShow() {
  // Charger les sports au moment de l'ouverture de la modal
  if (addBetFormRef.value && addBetFormRef.value.loadSportsOnModalOpen) {
    addBetFormRef.value.loadSportsOnModalOpen();
  }
}

function closeDialog() {
  dialogVisible.value = false;
  emit('update:visible', false);
}

function onBetCreated(bet) {
  emit('bet-created', bet);
  closeDialog();
}
</script>
