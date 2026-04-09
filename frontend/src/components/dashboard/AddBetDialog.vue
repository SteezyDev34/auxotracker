<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    :header="dialogHeader"
    :style="{ width: '90vw', maxWidth: '600px' }"
    @hide="closeDialog"
    @show="onDialogShow"
  >
    <AddBetForm
      ref="addBetFormRef"
      :editing-bet="editingBet"
      @bet-created="onBetCreated"
      @bet-deleted="onBetDeleted"
      @closeDialog="closeDialog"
    />
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import Dialog from "primevue/dialog";
import AddBetForm from "@/components/add-bet/AddBetForm.vue";

const props = defineProps({
  visible: Boolean,
  editingBet: {
    type: Object,
    default: null,
  },
});
const emit = defineEmits(["update:visible", "bet-created", "bet-deleted"]);

const dialogVisible = ref(props.visible);
const addBetFormRef = ref(null);
const editingBet = ref(props.editingBet);

const dialogHeader = computed(() => {
  return editingBet.value ? "Modifier le pari" : "Ajouter un pari";
});

watch(
  () => props.visible,
  (val) => {
    dialogVisible.value = val;
  }
);

watch(
  () => props.editingBet,
  (val) => {
    editingBet.value = val;
  }
);

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
  emit("update:visible", false);
}

function onBetCreated(bet) {
  emit("bet-created", bet);
  closeDialog();
}

function onBetDeleted(betId) {
  emit("bet-deleted", betId);
  closeDialog();
}
</script>
