<template>
  <div class="admin-logs">
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="m-0 text-2xl font-bold">Logs d'activité</h2>
      <div class="flex gap-2">
        <Button icon="pi pi-refresh" label="Actualiser" severity="secondary" @click="fetchLogs" :loading="loading" />
        <Button icon="pi pi-trash" label="Vider les logs" severity="danger" outlined @click="confirmClear" />
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid mb-4">
      <div class="col-6 md:col-3">
        <div class="surface-card border-round p-3 text-center shadow-1">
          <div class="text-2xl font-bold text-primary">{{ stats.total ?? '—' }}</div>
          <div class="text-500 text-sm mt-1">Total</div>
        </div>
      </div>
      <div class="col-6 md:col-3">
        <div class="surface-card border-round p-3 text-center shadow-1">
          <div class="text-2xl font-bold text-green-500">{{ stats.today ?? '—' }}</div>
          <div class="text-500 text-sm mt-1">Aujourd'hui</div>
        </div>
      </div>
      <div class="col-6 md:col-3">
        <div class="surface-card border-round p-3 text-center shadow-1">
          <div class="text-2xl font-bold text-red-500">{{ stats.errors ?? '—' }}</div>
          <div class="text-500 text-sm mt-1">Erreurs</div>
        </div>
      </div>
      <div class="col-6 md:col-3">
        <div class="surface-card border-round p-3 text-center shadow-1">
          <div class="text-2xl font-bold text-blue-500">{{ stats.by_type?.import_finished ?? '—' }}</div>
          <div class="text-500 text-sm mt-1">Imports</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="surface-card border-round p-3 mb-4 shadow-1">
      <div class="flex flex-wrap gap-3 align-items-end">
        <div class="flex flex-column gap-1">
          <label class="text-sm text-500">Recherche</label>
          <InputText v-model="filters.search" placeholder="Chercher..." @keyup.enter="fetchLogs" class="w-12rem" />
        </div>
        <div class="flex flex-column gap-1">
          <label class="text-sm text-500">Sport</label>
          <select v-model="filters.sport" class="p-inputtext w-8rem border-round" style="background:var(--surface-b);color:var(--text-color);border:1px solid var(--surface-d)">
            <option v-for="o in sportOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="flex flex-column gap-1">
          <label class="text-sm text-500">Type</label>
          <select v-model="filters.type" class="p-inputtext w-10rem border-round" style="background:var(--surface-b);color:var(--text-color);border:1px solid var(--surface-d)">
            <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="flex flex-column gap-1">
          <label class="text-sm text-500">Niveau</label>
          <select v-model="filters.level" class="p-inputtext w-8rem border-round" style="background:var(--surface-b);color:var(--text-color);border:1px solid var(--surface-d)">
            <option v-for="o in levelOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="flex flex-column gap-1">
          <label class="text-sm text-500">Date</label>
          <InputText v-model="filters.date" type="date" class="w-10rem" />
        </div>
        <Button icon="pi pi-search" label="Filtrer" @click="fetchLogs" />
        <Button icon="pi pi-times" label="Reset" severity="secondary" outlined @click="resetFilters" />
      </div>
    </div>

    <!-- Timeline logs -->
    <div v-if="loading" class="flex justify-content-center py-6">
      <ProgressSpinner />
    </div>

    <div v-else-if="logs.length === 0" class="surface-card border-round p-6 text-center text-500">
      Aucun log trouvé.
    </div>

    <div v-else class="log-list">
      <div
        v-for="log in logs"
        :key="log.id"
        class="log-entry surface-card border-round mb-2 p-3 shadow-1 flex gap-3 align-items-start"
        :class="`level-${log.level}`"
      >
        <!-- Level indicator -->
        <div class="log-indicator flex-shrink-0 mt-1">
          <i :class="levelIcon(log.level)" :style="{ color: levelColor(log.level) }" style="font-size:1.2rem"></i>
        </div>

        <!-- Logo if available -->
        <div v-if="log.subject_img" class="flex-shrink-0">
          <img
            :src="logoUrl(log.subject_img)"
            :alt="log.subject_name"
            class="border-circle"
            style="width:36px;height:36px;object-fit:contain;background:var(--surface-d)"
            @error="onImgError"
          />
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex align-items-center gap-2 flex-wrap mb-1">
            <Tag :value="log.type" :severity="typeSeverity(log.type)" class="text-xs" />
            <Tag v-if="log.sport" :value="log.sport" severity="secondary" class="text-xs" />
            <span class="text-400 text-xs">{{ formatDate(log.created_at) }}</span>
            <span v-if="log.command" class="text-400 text-xs font-italic">{{ log.command }}</span>
          </div>
          <div class="text-sm">{{ log.description }}</div>
          <div v-if="log.subject_name" class="text-xs text-500 mt-1">
            {{ log.subject_type }} : <strong>{{ log.subject_name }}</strong>
            <span v-if="log.subject_id"> (ID: {{ log.subject_id }})</span>
          </div>
          <div v-if="log.metadata && Object.keys(log.metadata).length" class="mt-2">
            <div class="flex flex-wrap gap-2">
              <span
                v-for="(val, key) in log.metadata"
                :key="key"
                class="text-xs surface-d border-round px-2 py-1"
                style="background: var(--surface-d)"
              >
                {{ key }}: <strong>{{ val }}</strong>
              </span>
            </div>
          </div>
        </div>

        <!-- Delete -->
        <Button
          icon="pi pi-times"
          severity="secondary"
          text
          size="small"
          class="flex-shrink-0"
          @click="deleteLog(log.id)"
        />
      </div>

      <!-- Pagination -->
      <Paginator
        v-if="pagination.total > pagination.per_page"
        :rows="pagination.per_page"
        :totalRecords="pagination.total"
        :first="(pagination.current_page - 1) * pagination.per_page"
        @page="onPage"
        class="mt-3"
      />
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import axios from 'axios'

const toast = useToast()
const apiBase = import.meta.env.VITE_API_URL

const logs = ref([])
const stats = ref({})
const loading = ref(false)
const pagination = ref({ total: 0, per_page: 50, current_page: 1 })

const filters = ref({
  search: '',
  sport: '',
  type: '',
  level: '',
  date: '',
})

const sportOptions = [
  { label: 'Tous', value: '' },
  { label: 'Tennis', value: 'tennis' },
  { label: 'Football', value: 'football' },
  { label: 'Basketball', value: 'basketball' },
]

const typeOptions = [
  { label: 'Tous', value: '' },
  { label: 'Import démarré', value: 'import_started' },
  { label: 'Import terminé', value: 'import_finished' },
  { label: 'Équipe créée', value: 'team_created' },
  { label: 'Équipe mise à jour', value: 'team_updated' },
  { label: 'Ligue créée', value: 'league_created' },
  { label: 'Ligue mise à jour', value: 'league_updated' },
  { label: 'Match créé', value: 'match_created' },
  { label: 'Cache écrit', value: 'cache_write' },
  { label: 'Erreur', value: 'error' },
]

const levelOptions = [
  { label: 'Tous', value: '' },
  { label: 'Info', value: 'info' },
  { label: 'Succès', value: 'success' },
  { label: 'Avertissement', value: 'warning' },
  { label: 'Erreur', value: 'error' },
]

function authHeaders() {
  const token = localStorage.getItem('token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

async function fetchLogs(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 50 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.sport !== '') params.sport = filters.value.sport
    if (filters.value.type !== '') params.type = filters.value.type
    if (filters.value.level !== '') params.level = filters.value.level
    if (filters.value.date) params.date = filters.value.date

    const res = await axios.get(`${apiBase}/admin/logs`, { params, headers: authHeaders() })
    const paginator = res.data.data ?? {}
    logs.value = paginator.data ?? []
    stats.value = res.data.stats ?? {}
    pagination.value = {
      total: paginator.total ?? 0,
      per_page: paginator.per_page ?? 50,
      current_page: paginator.current_page ?? 1,
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les logs', life: 3000 })
  } finally {
    loading.value = false
  }
}

function onPage(event) {
  fetchLogs(event.page + 1)
}

function resetFilters() {
  filters.value = { search: '', sport: '', type: '', level: '', date: '' }
  fetchLogs()
}

async function deleteLog(id) {
  try {
    await axios.delete(`${apiBase}/admin/logs/${id}`, { headers: authHeaders() })
    logs.value = logs.value.filter(l => l.id !== id)
    if (stats.value.total) stats.value.total--
    toast.add({ severity: 'success', summary: 'Supprimé', life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression échouée', life: 3000 })
  }
}

async function confirmClear() {
  if (!window.confirm('Supprimer tous les logs ? Cette action est irréversible.')) return
  try {
    await axios.delete(`${apiBase}/admin/logs/clear`, { headers: authHeaders() })
    logs.value = []
    stats.value = {}
    toast.add({ severity: 'success', summary: 'Logs supprimés', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de vider les logs', life: 3000 })
  }
}

function logoUrl(path) {
  if (!path) return null
  if (path.startsWith('http')) return path
  const base = (import.meta.env.VITE_API_URL ?? '').replace(/\/api$/, '')
  return `${base}/storage/${path}`
}

function onImgError(e) {
  e.target.style.display = 'none'
}

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

function levelIcon(level) {
  const map = {
    info: 'pi pi-info-circle',
    success: 'pi pi-check-circle',
    warning: 'pi pi-exclamation-triangle',
    error: 'pi pi-times-circle',
  }
  return map[level] ?? 'pi pi-circle'
}

function levelColor(level) {
  const map = {
    info: 'var(--blue-500)',
    success: 'var(--green-500)',
    warning: 'var(--orange-500)',
    error: 'var(--red-500)',
  }
  return map[level] ?? 'var(--text-color-secondary)'
}

function typeSeverity(type) {
  if (type?.includes('error')) return 'danger'
  if (type?.includes('created')) return 'success'
  if (type?.includes('updated')) return 'info'
  if (type?.includes('import_finished')) return 'success'
  if (type?.includes('import_started')) return 'secondary'
  return 'secondary'
}

onMounted(fetchLogs)
</script>

<style scoped>
.log-entry {
  border-left: 3px solid transparent;
  transition: border-color 0.2s;
}
.log-entry.level-error { border-left-color: var(--red-400); }
.log-entry.level-warning { border-left-color: var(--orange-400); }
.log-entry.level-success { border-left-color: var(--green-400); }
.log-entry.level-info { border-left-color: var(--blue-400); }
</style>
