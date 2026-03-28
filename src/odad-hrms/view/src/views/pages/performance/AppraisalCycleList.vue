<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Appraisal Cycles</h2>
            <Button label="New Cycle" icon="pi pi-plus"
                @click="$router.push({ name: 'appraisal-cycle-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <Dropdown v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
                placeholder="Filter by status" show-clear class="w-44" @change="loadData" />
        </div>

        <DataTable :value="items" :loading="loading" lazy :total-records="total" :rows="perPage"
            paginator @page="onPage" row-hover striped-rows>
            <Column field="name" header="Name" />
            <Column field="cycle_type" header="Cycle Type" />
            <Column field="start_date" header="Start Date" />
            <Column field="end_date" header="End Date" />
            <Column field="submission_deadline" header="Deadline" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column header="Actions">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" size="small" severity="secondary" text
                        @click="$router.push({ name: 'appraisal-cycle-edit', params: { id: data.id } })" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { AppraisalCycleService } from '@/service/AppraisalCycleService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';

const items   = ref([]);
const loading = ref(false);
const total   = ref(0);
const page    = ref(1);
const perPage = ref(20);

const filters = ref({ status: null });

const statusOptions = [
    { label: 'Draft',     value: 'draft'     },
    { label: 'Active',    value: 'active'    },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

function statusSeverity(status) {
    const map = { draft: 'secondary', active: 'success', completed: 'info', cancelled: 'danger' };
    return map[status] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await AppraisalCycleService.getList({
            page:     page.value,
            per_page: perPage.value,
            status:   filters.value.status || undefined,
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
