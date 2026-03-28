<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Offer Letter' : 'New Offer Letter' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label class="font-medium">Applicant ID *</label>
                <InputText v-model="form.applicant_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Designation ID *</label>
                <InputText v-model="form.designation_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Department ID *</label>
                <InputText v-model="form.department_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Offered Salary *</label>
                <InputText v-model="form.offered_salary" type="number" step="0.01" min="0" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Currency</label>
                <InputText v-model="form.currency" maxlength="3" placeholder="USD" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Joining Date *</label>
                <HrDatePicker v-model="form.joining_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Offer Date *</label>
                <HrDatePicker v-model="form.offer_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Expiry Date *</label>
                <HrDatePicker v-model="form.expiry_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Status</label>
                <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Letter Content *</label>
                <Textarea v-model="form.letter_content" rows="12" required />
            </div>

            <div class="md:col-span-2 flex gap-3 justify-between flex-wrap">
                <div class="flex gap-2">
                    <Button type="button" label="Preview Letter" icon="pi pi-eye" severity="secondary" @click="showPreview = true" />
                    <Button v-if="isEdit" type="button" label="Send to Applicant" icon="pi pi-send" severity="info"
                        :loading="sending" @click="sendLetter" />
                </div>
                <div class="flex gap-2">
                    <Button type="button" label="Cancel" severity="secondary" @click="$router.back()" />
                    <Button type="submit" :label="isEdit ? 'Update' : 'Create'" :loading="saving" />
                </div>
            </div>
        </form>

        <!-- Preview Dialog -->
        <Dialog v-model:visible="showPreview" header="Offer Letter Preview" modal maximizable
            :style="{ width: '60rem' }">
            <div class="prose max-w-none p-4 border rounded" v-html="form.letter_content" />
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { OfferLetterService } from '@/service/OfferLetterService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import HrDatePicker from '@/components/HrDatePicker.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';

const route  = useRoute();
const router = useRouter();

const isEdit     = computed(() => !!route.params.id);
const saving     = ref(false);
const sending    = ref(false);
const showPreview = ref(false);

const form = ref({
    applicant_id:   route.query.applicant_id ?? '',
    designation_id: '',
    department_id:  '',
    offered_salary: '',
    currency:       'USD',
    joining_date:   null,
    offer_date:     null,
    expiry_date:    null,
    letter_content: '',
    status:         'draft',
});

const statusOptions = [
    { label: 'Draft',     value: 'draft'     },
    { label: 'Sent',      value: 'sent'      },
    { label: 'Accepted',  value: 'accepted'  },
    { label: 'Declined',  value: 'declined'  },
    { label: 'Expired',   value: 'expired'   },
    { label: 'Cancelled', value: 'cancelled' },
];

function formatDate(val) {
    if (!val) return null;
    if (val instanceof Date) return val.toISOString().slice(0, 10);
    return val;
}

async function loadOffer() {
    if (!isEdit.value) return;
    try {
        const data = await OfferLetterService.getById(route.params.id);
        Object.assign(form.value, data);
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    const payload = {
        ...form.value,
        joining_date: formatDate(form.value.joining_date),
        offer_date:   formatDate(form.value.offer_date),
        expiry_date:  formatDate(form.value.expiry_date),
    };
    try {
        if (isEdit.value) {
            await OfferLetterService.update(route.params.id, payload);
        } else {
            await OfferLetterService.create(payload);
        }
        router.push({ name: 'job-offers' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

async function sendLetter() {
    if (!confirm('Send this offer letter to the applicant by email?')) return;
    sending.value = true;
    try {
        await OfferLetterService.send(route.params.id);
        alert('Offer letter sent successfully.');
        await loadOffer();
    } catch (e) {
        alert(e.message);
    } finally {
        sending.value = false;
    }
}

onMounted(loadOffer);
</script>
