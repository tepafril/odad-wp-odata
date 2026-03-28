<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Appraisals</h2>
            <Button label="New Appraisal" icon="pi pi-plus"
                @click="$router.push({ name: 'appraisal-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <InputText v-model="filters.appraisal_cycle_id" type="number" placeholder="Cycle ID" class="w-36"
                @change="loadData" />
            <InputText v-model="filters.employee_id" type="number" placeholder="Employee ID" class="w-36"
                @change="loadData" />
            <Dropdown v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
                placeholder="Status" show-clear class="w-48" @change="loadData" />
        </div>

        <DataTable :value="items" :loading="loading" lazy :total-records="total" :rows="perPage"
            paginator @page="onPage" row-hover striped-rows>
            <Column field="employee_id" header="Employee ID" />
            <Column field="appraiser_id" header="Appraiser ID" />
            <Column field="appraisal_cycle_id" header="Cycle ID" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column field="self_rating" header="Self Rating" />
            <Column field="appraiser_rating" header="Manager Rating" />
            <Column field="final_rating" header="Final Rating" />
            <Column header="Actions">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="['draft','self_review'].includes(data.status)"
                            label="Self Review" size="small" severity="info" text
                            @click="$router.push({ name: 'appraisal-review', params: { id: data.id }, query: { tab: 'self' } })" />
                        <Button v-if="data.status === 'manager_review'"
                            label="Manager Review" size="small" severity="warn" text
                            @click="$router.push({ name: 'appraisal-review', params: { id: data.id }, query: { tab: 'manager' } })" />
                        <Button v-if="['calibration'].includes(data.status)"
                            label="Finalize" size="small" severity="success" text
                            @click="$router.push({ name: 'appraisal-review', params: { id: data.id }, query: { tab: 'finalize' } })" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { AppraisalService } from '@/service/AppraisalService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';

const items   = ref([]);
const loading = ref(false);
const total   = ref(0);
const page    = ref(1);
const perPage = ref(20);

const filters = ref({
    appraisal_cycle_id: '',
    employee_id:        '',
    status:             null,
});

const statusOptions = [
    { label: 'Draft',           value: 'draft'           },
    { label: 'Self Review',     value: 'self_review'     },
    { label: 'Manager Review',  value: 'manager_review'  },
    { label: 'Calibration',     value: 'calibration'     },
    { label: 'Completed',       value: 'completed'       },
];

function statusSeverity(status) {
    const map = {
        draft:          'secondary',
        self_review:    'info',
        manager_review: 'warn',
        calibration:    'warn',
        completed:      'success',
    };
    return map[status] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await AppraisalService.getList({
            page:                page.value,
            per_page:            perPage.value,
            appraisal_cycle_id:  filters.value.appraisal_cycle_id || undefined,
            employee_id:         filters.value.employee_id         || undefined,
            status:              filters.value.status              || undefined,
        });
        items.value = res.data ?? res;
        total.value = res.total ?? items.value.length;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function onPage(event) {
    page.value = event.page + 1;
    loadData();
}

onMounted(loadData);
</script>
