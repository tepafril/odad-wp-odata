<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Interview' : 'Schedule Interview' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label class="font-medium">Applicant *</label>
                <InputText v-model="form.applicant_id" type="number" required placeholder="Applicant ID" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Interview Round *</label>
                <InputText v-model="form.interview_round" required placeholder="e.g. Phone Screen, Technical Round 1" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Scheduled At *</label>
                <HrDatePicker v-model="form.scheduled_at" show-time hour-format="24" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Duration (minutes)</label>
                <InputText v-model="form.duration_minutes" type="number" min="15" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Location</label>
                <InputText v-model="form.location" placeholder="Room / Link" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Status</label>
                <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
            </div>

            <!-- Panelists section (edit mode) -->
            <div v-if="isEdit" class="md:col-span-2">
                <div class="flex items-center justify-between mb-2">
                    <label class="font-medium">Panelists</label>
                    <Button type="button" icon="pi pi-plus" label="Add Panelist" size="small" @click="showAddPanelist = true" />
                </div>
                <DataTable :value="panelists" striped-rows size="small">
                    <Column field="employee_id" header="Employee ID" />
                    <Column field="rating" header="Rating" />
                    <Column field="submitted_at" header="Submitted At" />
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Button icon="pi pi-trash" size="small" severity="danger" text @click="removePanelist(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div class="md:col-span-2 flex gap-3 justify-end">
                <Button type="button" label="Cancel" severity="secondary" @click="$router.back()" />
                <Button type="submit" :label="isEdit ? 'Update' : 'Schedule'" :loading="saving" />
            </div>
        </form>

        <!-- Add Panelist Dialog -->
        <Dialog v-model:visible="showAddPanelist" header="Add Panelist" modal :style="{ width: '30rem' }">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Employee ID</label>
                    <InputText v-model="newPanelist.employee_id" type="number" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" @click="showAddPanelist = false" />
                    <Button label="Add" @click="addPanelist" :loading="addingPanelist" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { InterviewService } from '@/service/InterviewService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import HrDatePicker from '@/components/HrDatePicker.vue';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';

const route  = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);
const panelists      = ref([]);
const showAddPanelist = ref(false);
const addingPanelist  = ref(false);
const newPanelist     = ref({ employee_id: '' });

const form = ref({
    applicant_id:     route.query.applicant_id ?? '',
    interview_round:  '',
    scheduled_at:     null,
    duration_minutes: 60,
    location:         '',
    status:           'scheduled',
});

const statusOptions = [
    { label: 'Scheduled',  value: 'scheduled'  },
    { label: 'Completed',  value: 'completed'  },
    { label: 'Cancelled',  value: 'cancelled'  },
    { label: 'No Show',    value: 'no_show'    },
];

async function loadInterview() {
    if (!isEdit.value) return;
    try {
        const data = await InterviewService.getById(route.params.id);
        Object.assign(form.value, data);
        const ps = await InterviewService.getPanelists(route.params.id);
        panelists.value = ps.data ?? ps;
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    const payload = { ...form.value };
    if (payload.scheduled_at instanceof Date) {
        payload.scheduled_at = payload.scheduled_at.toISOString().slice(0, 19).replace('T', ' ');
    }
    try {
        if (isEdit.value) {
            await InterviewService.update(route.params.id, payload);
        } else {
            await InterviewService.create(payload);
        }
        router.push({ name: 'interviews' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

async function addPanelist() {
    if (!newPanelist.value.employee_id) return;
    addingPanelist.value = true;
    try {
        await InterviewService.addPanelist(route.params.id, { employee_id: newPanelist.value.employee_id });
        const ps = await InterviewService.getPanelists(route.params.id);
        panelists.value = ps.data ?? ps;
        showAddPanelist.value = false;
        newPanelist.value = { employee_id: '' };
    } catch (e) {
        alert(e.message);
    } finally {
        addingPanelist.value = false;
    }
}

async function removePanelist(panelist) {
    if (!confirm('Remove this panelist?')) return;
    try {
        await InterviewService.removePanelist(route.params.id, panelist.id);
        const ps = await InterviewService.getPanelists(route.params.id);
        panelists.value = ps.data ?? ps;
    } catch (e) {
        alert(e.message);
    }
}

onMounted(loadInterview);
</script>
