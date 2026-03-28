<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { EmployeeService } from '@/service/EmployeeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({ employeeId: { type: Number, required: true } });
const toast   = useToast();
const confirm = useConfirm();

const items   = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const editItem = ref(null);
const saving   = ref(false);

const form = ref({ institution: '', degree: '', field_of_study: '', start_date: null, end_date: null, grade_or_gpa: '', notes: '' });

onMounted(load);

async function load() {
    loading.value = true;
    try { const d = await EmployeeService.getEducation(props.employeeId); items.value = d?.items ?? d ?? []; }
    catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { loading.value = false; }
}

function toDateStr(v) { if (!v) return null; if (typeof v === 'string') return v; const d = new Date(v); return isNaN(d) ? null : d.toISOString().slice(0,10); }

function openNew() {
    editItem.value = null;
    Object.assign(form.value, { institution: '', degree: '', field_of_study: '', start_date: null, end_date: null, grade_or_gpa: '', notes: '' });
    dialogVisible.value = true;
}

async function save() {
    if (!form.value.institution?.trim()) return toast.add({ severity: 'warn', detail: 'Institution is required.', life: 3000 });
    saving.value = true;
    try {
        const body = { ...form.value, start_date: toDateStr(form.value.start_date), end_date: toDateStr(form.value.end_date) };
        if (editItem.value) await EmployeeService.updateEducation(editItem.value.id, body);
        else await EmployeeService.addEducation(props.employeeId, body);
        toast.add({ severity: 'success', detail: 'Saved.', life: 3000 });
        dialogVisible.value = false; load();
    } catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { saving.value = false; }
}

function openEdit(row) {
    editItem.value = row;
    Object.assign(form.value, { institution: row.institution, degree: row.degree ?? '', field_of_study: row.field_of_study ?? '', start_date: row.start_date ?? null, end_date: row.end_date ?? null, grade_or_gpa: row.grade_or_gpa ?? '', notes: row.notes ?? '' });
    dialogVisible.value = true;
}

function confirmDelete(row) {
    confirm.require({
        message: `Delete this education record?`, header: 'Delete', icon: 'pi pi-trash', acceptClass: 'p-button-danger',
        accept: async () => { try { await EmployeeService.removeEducation(row.id); toast.add({ severity: 'success', detail: 'Deleted.', life: 3000 }); load(); } catch (e) { toast.add({ severity: 'error', detail: e.message, life: 5000 }); } },
    });
}
</script>

<template>
    <div>
        <div class="flex justify-end mb-3">
            <Button label="Add Education" icon="pi pi-plus" size="small" @click="openNew" />
        </div>
        <DataTable :value="items" :loading="loading" dataKey="id">
            <template #empty><div class="text-center py-6 text-surface-500">No education records.</div></template>
            <Column field="institution"   header="Institution"    style="min-width:14rem" />
            <Column field="degree"        header="Degree"         style="min-width:10rem" />
            <Column field="field_of_study" header="Field"         style="min-width:10rem" />
            <Column field="start_date"    header="From"           style="min-width:8rem" />
            <Column field="end_date"      header="To"             style="min-width:8rem" />
            <Column field="grade_or_gpa"  header="Grade/GPA"      style="min-width:8rem" />
            <Column header="Actions" style="min-width:9rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                        <Button icon="pi pi-trash"  text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" :header="editItem ? 'Edit Education' : 'Add Education'"
            modal :style="{ width: '40rem' }" :dismissableMask="true">
            <div class="flex flex-col gap-4 pt-1">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Institution <span class="text-red-500">*</span></label>
                    <InputText v-model="form.institution" fluid />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Degree</label>
                        <InputText v-model="form.degree" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Field of Study</label>
                        <InputText v-model="form.field_of_study" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Start Date</label>
                        <HrDatePicker v-model="form.start_date" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">End Date</label>
                        <HrDatePicker v-model="form.end_date" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Grade / GPA</label>
                        <InputText v-model="form.grade_or_gpa" fluid />
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
        <ConfirmDialog />
    </div>
</template>
