<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Training Event' : 'New Training Event' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Title *</label>
                <InputText v-model="form.title" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Company ID *</label>
                <InputText v-model="form.company_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Trainer *</label>
                <InputText v-model="form.trainer" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Training Type</label>
                <Dropdown v-model="form.training_type" :options="typeOptions" option-label="label" option-value="value" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Status</label>
                <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Start Date *</label>
                <HrDatePicker v-model="form.start_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">End Date *</label>
                <HrDatePicker v-model="form.end_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Location</label>
                <InputText v-model="form.location" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Max Participants</label>
                <InputText v-model="form.max_participants" type="number" min="1" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Cost</label>
                <InputText v-model="form.cost" type="number" step="0.01" min="0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Currency</label>
                <InputText v-model="form.currency" maxlength="3" placeholder="USD" />
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Description</label>
                <Textarea v-model="form.description" rows="4" />
            </div>

            <div class="md:col-span-2 flex gap-3 justify-end">
                <Button type="button" label="Cancel" severity="secondary" @click="$router.back()" />
                <Button type="submit" :label="isEdit ? 'Update' : 'Create'" :loading="saving" />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { TrainingEventService } from '@/service/TrainingEventService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import HrDatePicker from '@/components/HrDatePicker.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const route  = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);

const form = ref({
    company_id:       '',
    title:            '',
    description:      '',
    trainer:          '',
    training_type:    'internal',
    start_date:       null,
    end_date:         null,
    location:         '',
    max_participants: null,
    cost:             null,
    currency:         '',
    status:           'planned',
});

const typeOptions = [
    { label: 'Internal', value: 'internal' },
    { label: 'External', value: 'external' },
    { label: 'Online',   value: 'online'   },
    { label: 'Workshop', value: 'workshop' },
];

const statusOptions = [
    { label: 'Planned',     value: 'planned'     },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed',   value: 'completed'   },
    { label: 'Cancelled',   value: 'cancelled'   },
];

function formatDate(val) {
    if (!val) return null;
    if (val instanceof Date) return val.toISOString().slice(0, 10);
    return val;
}

async function loadEvent() {
    if (!isEdit.value) return;
    try {
        const data = await TrainingEventService.getById(route.params.id);
        Object.assign(form.value, data);
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    const payload = {
        ...form.value,
        start_date: formatDate(form.value.start_date),
        end_date:   formatDate(form.value.end_date),
    };
    try {
        if (isEdit.value) {
            await TrainingEventService.update(route.params.id, payload);
        } else {
            await TrainingEventService.create(payload);
        }
        router.push({ name: 'training-events' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

onMounted(loadEvent);
</script>
