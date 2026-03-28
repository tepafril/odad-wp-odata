<template>
    <div>
        <div v-if="loading" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-4xl" />
        </div>

        <template v-else-if="event">
            <!-- Event Info Card -->
            <div class="card mb-4">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold">{{ event.title }}</h2>
                        <p class="text-surface-500">Trainer: {{ event.trainer }}</p>
                        <p class="text-surface-500">{{ event.start_date }} — {{ event.end_date }}</p>
                        <p v-if="event.location" class="text-surface-500">{{ event.location }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <Tag class="capitalize" :value="event.status" :severity="statusSeverity(event.status)" />
                        <Tag class="capitalize" :value="event.training_type" severity="info" />
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-3">
                    <div><span class="font-medium">Max Participants:</span> {{ event.max_participants ?? 'Unlimited' }}</div>
                    <div><span class="font-medium">Cost:</span> {{ event.cost ?? 'N/A' }} {{ event.currency }}</div>
                    <div><span class="font-medium">Enrolled:</span> {{ participants.length }}</div>
                </div>

                <p v-if="event.description" class="text-surface-600">{{ event.description }}</p>

                <div class="mt-3">
                    <Button label="Edit Event" icon="pi pi-pencil" severity="secondary" size="small"
                        @click="$router.push({ name: 'training-event-edit', params: { id: event.id } })" />
                </div>
            </div>

            <!-- Participants -->
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold">Participants ({{ participants.length }})</h3>
                    <Button label="Add Participant" icon="pi pi-user-plus" size="small"
                        @click="showAddDialog = true" />
                </div>

                <DataTable :value="participants" :loading="participantsLoading" striped-rows row-hover>
                    <Column field="employee_id" header="Employee ID" />
                    <Column field="attendance_status" header="Attendance">
                        <template #body="{ data }">
                            <Tag class="capitalize" :value="data.attendance_status" :severity="attendanceSeverity(data.attendance_status)" />
                        </template>
                    </Column>
                    <Column field="rating" header="Rating" />
                    <Column field="feedback" header="Feedback">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.feedback ?? '' }}</span>
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" size="small" severity="secondary" text
                                @click="openUpdateDialog(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </template>

        <!-- Add Participant Dialog -->
        <Dialog v-model:visible="showAddDialog" header="Add Participant" modal :style="{ width: '30rem' }">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Employee ID *</label>
                    <InputText v-model="addForm.employee_id" type="number" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" @click="showAddDialog = false" />
                    <Button label="Register" :loading="adding" @click="addParticipant" />
                </div>
            </div>
        </Dialog>

        <!-- Update Participant Dialog -->
        <Dialog v-model:visible="showUpdateDialog" header="Update Participant" modal :style="{ width: '35rem' }">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Attendance Status</label>
                    <Dropdown v-model="updateForm.attendance_status" :options="attendanceOptions"
                        option-label="label" option-value="value" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Rating (1–5)</label>
                    <InputText v-model="updateForm.rating" type="number" min="1" max="5" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Feedback</label>
                    <Textarea v-model="updateForm.feedback" rows="4" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" @click="showUpdateDialog = false" />
                    <Button label="Save" :loading="updating" @click="updateParticipant" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { TrainingEventService } from '@/service/TrainingEventService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

const route = useRoute();

const event              = ref(null);
const loading            = ref(false);
const participants       = ref([]);
const participantsLoading = ref(false);
const showAddDialog      = ref(false);
const showUpdateDialog   = ref(false);
const adding             = ref(false);
const updating           = ref(false);
const selectedParticipant = ref(null);

const addForm = ref({ employee_id: '' });
const updateForm = ref({ attendance_status: 'registered', rating: null, feedback: '' });

const attendanceOptions = [
    { label: 'Registered', value: 'registered' },
    { label: 'Attended',   value: 'attended'   },
    { label: 'Absent',     value: 'absent'     },
    { label: 'Cancelled',  value: 'cancelled'  },
];

function statusSeverity(status) {
    const map = { planned: 'secondary', in_progress: 'info', completed: 'success', cancelled: 'danger' };
    return map[status] ?? 'secondary';
}

function attendanceSeverity(status) {
    const map = { registered: 'info', attended: 'success', absent: 'danger', cancelled: 'secondary' };
    return map[status] ?? 'secondary';
}

async function loadEvent() {
    loading.value = true;
    try {
        event.value = await TrainingEventService.getById(route.params.id);
        await loadParticipants();
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function loadParticipants() {
    participantsLoading.value = true;
    try {
        // Use the list endpoint filtered by event — we register/update via sub-resource
        // The API returns participants when GET /training-events/{id}/participants is called
        // but since we only have POST on that route, we fetch from the general list instead.
        // For now we fetch all participants for this event via a workaround:
        // We store them after each add/update, starting with an empty list on first load.
        // A proper GET /training-events/{id}/participants route would be ideal;
        // for now participants are populated from add/update operations.
        // Initial load: participants list is empty until we GET from a list endpoint.
        // Since the spec only defines POST on the participants sub-route, we rely on
        // any existing data returned from the event or a participants index.
        // Leave as empty array — populated on add/update.
    } catch (e) {
        console.error(e);
    } finally {
        participantsLoading.value = false;
    }
}

async function addParticipant() {
    if (!addForm.value.employee_id) return;
    adding.value = true;
    try {
        const p = await TrainingEventService.registerParticipant(route.params.id, {
            employee_id: parseInt(addForm.value.employee_id),
        });
        participants.value.push(p);
        showAddDialog.value = false;
        addForm.value = { employee_id: '' };
    } catch (e) {
        alert(e.message);
    } finally {
        adding.value = false;
    }
}

function openUpdateDialog(participant) {
    selectedParticipant.value = participant;
    updateForm.value = {
        attendance_status: participant.attendance_status,
        rating:            participant.rating ?? null,
        feedback:          participant.feedback ?? '',
    };
    showUpdateDialog.value = true;
}

async function updateParticipant() {
    updating.value = true;
    try {
        const updated = await TrainingEventService.updateParticipant(
            route.params.id,
            selectedParticipant.value.id,
            updateForm.value
        );
        const idx = participants.value.findIndex(p => p.id === selectedParticipant.value.id);
        if (idx !== -1) participants.value[idx] = updated;
        showUpdateDialog.value = false;
    } catch (e) {
        alert(e.message);
    } finally {
        updating.value = false;
    }
}

onMounted(loadEvent);
</script>
