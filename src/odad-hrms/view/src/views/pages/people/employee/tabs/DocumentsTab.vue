<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { EmployeeService } from '@/service/EmployeeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({ employeeId: { type: Number, required: true } });
const toast   = useToast();

const items   = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const saving   = ref(false);

const documentTypeOptions = [
    'Passport', 'National ID', 'Driving License', 'Visa', 'Work Permit',
    'Birth Certificate', 'Contract', 'Certificate', 'Other',
];

const form = ref({ document_type: 'Passport', title: '', issue_date: null, expiry_date: null, notes: '' });

onMounted(load);

async function load() {
    loading.value = true;
    try { const d = await EmployeeService.getDocuments(props.employeeId); items.value = d?.items ?? d ?? []; }
    catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { loading.value = false; }
}

function toDateStr(v) { if (!v) return null; if (typeof v === 'string') return v; const d = new Date(v); return isNaN(d) ? null : d.toISOString().slice(0,10); }

function openNew() {
    Object.assign(form.value, { document_type: 'Passport', title: '', issue_date: null, expiry_date: null, notes: '' });
    dialogVisible.value = true;
}

async function save() {
    if (!form.value.title?.trim()) return toast.add({ severity: 'warn', detail: 'Document title is required.', life: 3000 });
    saving.value = true;
    try {
        await EmployeeService.addDocument(props.employeeId, {
            ...form.value,
            issue_date:  toDateStr(form.value.issue_date),
            expiry_date: toDateStr(form.value.expiry_date),
            attachment_id: 0, // placeholder — file upload not implemented here
        });
        toast.add({ severity: 'success', detail: 'Document record added.', life: 3000 });
        dialogVisible.value = false; load();
    } catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { saving.value = false; }
}
</script>

<template>
    <div>
        <div class="flex justify-end mb-3">
            <Button label="Add Document" icon="pi pi-plus" size="small" @click="openNew" />
        </div>
        <DataTable :value="items" :loading="loading" dataKey="id">
            <template #empty><div class="text-center py-6 text-surface-500">No documents.</div></template>
            <Column field="document_type" header="Type"       style="min-width:10rem" />
            <Column field="title"         header="Title"      style="min-width:14rem" />
            <Column field="issue_date"    header="Issued"     style="min-width:9rem">
                <template #body="{ data }">{{ data.issue_date || '—' }}</template>
            </Column>
            <Column field="expiry_date"   header="Expires"    style="min-width:9rem">
                <template #body="{ data }">
                    <span :class="data.expiry_date && new Date(data.expiry_date) < new Date() ? 'text-red-500 font-medium' : ''">
                        {{ data.expiry_date || '—' }}
                    </span>
                </template>
            </Column>
            <Column field="is_verified"   header="Verified"   style="min-width:7rem">
                <template #body="{ data }">
                    <i :class="parseInt(data.is_verified) ? 'pi pi-verified text-green-500' : 'pi pi-clock text-orange-400'" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" header="Add Document"
            modal :style="{ width: '38rem' }" :dismissableMask="true">
            <div class="flex flex-col gap-4 pt-1">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Document Type</label>
                    <Select v-model="form.document_type" :options="documentTypeOptions" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Title <span class="text-red-500">*</span></label>
                    <InputText v-model="form.title" fluid placeholder="e.g. Passport #A1234567" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Issue Date</label>
                        <HrDatePicker v-model="form.issue_date" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Expiry Date</label>
                        <HrDatePicker v-model="form.expiry_date" showIcon fluid />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Notes</label>
                    <Textarea v-model="form.notes" rows="2" fluid />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>
