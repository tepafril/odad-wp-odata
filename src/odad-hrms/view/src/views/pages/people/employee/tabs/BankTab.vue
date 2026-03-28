<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { EmployeeService } from '@/service/EmployeeService';

const props = defineProps({ employeeId: { type: Number, required: true } });
const toast   = useToast();
const confirm = useConfirm();

const items   = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const saving   = ref(false);

const form = ref({ bank_name: '', branch_name: '', account_name: '', account_number: '', routing_number: '', is_primary: true });

onMounted(load);

async function load() {
    loading.value = true;
    try { const d = await EmployeeService.getBankAccounts(props.employeeId); items.value = d?.items ?? d ?? []; }
    catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { loading.value = false; }
}

function openNew() {
    Object.assign(form.value, { bank_name: '', branch_name: '', account_name: '', account_number: '', routing_number: '', is_primary: true });
    dialogVisible.value = true;
}

async function save() {
    if (!form.value.bank_name?.trim())    return toast.add({ severity: 'warn', detail: 'Bank name is required.', life: 3000 });
    if (!form.value.account_number?.trim()) return toast.add({ severity: 'warn', detail: 'Account number is required.', life: 3000 });
    saving.value = true;
    try {
        await EmployeeService.addBankAccount(props.employeeId, { ...form.value, is_primary: form.value.is_primary ? 1 : 0 });
        toast.add({ severity: 'success', detail: 'Bank account added.', life: 3000 });
        dialogVisible.value = false; load();
    } catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { saving.value = false; }
}

function confirmDelete(row) {
    confirm.require({
        message: 'Delete this bank account?', header: 'Delete', icon: 'pi pi-trash', acceptClass: 'p-button-danger',
        accept: async () => { try { await EmployeeService.removeBankAccount(row.id); toast.add({ severity: 'success', detail: 'Deleted.', life: 3000 }); load(); } catch (e) { toast.add({ severity: 'error', detail: e.message, life: 5000 }); } },
    });
}
</script>

<template>
    <div>
        <div class="flex justify-end mb-3">
            <Button label="Add Bank Account" icon="pi pi-plus" size="small" @click="openNew" />
        </div>
        <DataTable :value="items" :loading="loading" dataKey="id">
            <template #empty><div class="text-center py-6 text-surface-500">No bank accounts.</div></template>
            <Column field="bank_name"     header="Bank"        style="min-width:12rem" />
            <Column field="branch_name"   header="Branch"      style="min-width:10rem">
                <template #body="{ data }">{{ data.branch_name || '—' }}</template>
            </Column>
            <Column field="account_name"  header="Account Name" style="min-width:12rem" />
            <Column field="account_number" header="Account No." style="min-width:10rem">
                <template #body>****</template>
            </Column>
            <Column field="is_primary"    header="Primary"     style="min-width:7rem">
                <template #body="{ data }">
                    <i :class="parseInt(data.is_primary) ? 'pi pi-check text-green-500' : ''" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:7rem">
                <template #body="{ data }">
                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" header="Add Bank Account"
            modal :style="{ width: '38rem' }" :dismissableMask="true">
            <div class="flex flex-col gap-4 pt-1">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Bank Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.bank_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Branch Name</label>
                        <InputText v-model="form.branch_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2 col-span-2">
                        <label class="font-medium">Account Holder Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.account_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Account Number <span class="text-red-500">*</span></label>
                        <InputText v-model="form.account_number" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Routing / SWIFT</label>
                        <InputText v-model="form.routing_number" fluid />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_primary" :binary="true" inputId="is_primary" />
                    <label for="is_primary" class="cursor-pointer font-medium">Primary Account</label>
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
