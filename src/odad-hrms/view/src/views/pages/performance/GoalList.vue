<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Goals</h2>
            <Button label="New Goal" icon="pi pi-plus" @click="$router.push({ name: 'goal-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <InputText v-model="filters.employee_id" type="number" placeholder="Employee ID" class="w-40"
                @change="loadData" />
            <Dropdown v-model="filters.goal_type" :options="goalTypeOptions" option-label="label" option-value="value"
                placeholder="Type" show-clear class="w-44" @change="loadData" />
            <Dropdown v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
                placeholder="Status" show-clear class="w-44" @change="loadData" />
        </div>

        <DataTable :value="items" :loading="loading" lazy :total-records="total" :rows="perPage"
            paginator @page="onPage" row-hover striped-rows>
            <Column field="title" header="Title" />
            <Column field="goal_type" header="Type">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.goal_type" severity="info" />
                </template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column field="completion_percent" header="Progress">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-surface-200 rounded-full h-2 min-w-16">
                            <div class="bg-primary h-2 rounded-full" :style="{ width: data.completion_percent + '%' }" />
                        </div>
                        <span class="text-sm text-surface-500">{{ data.completion_percent }}%</span>
                    </div>
                </template>
            </Column>
            <Column field="start_date" header="Start" />
            <Column field="end_date" header="End" />
            <Column header="Actions">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" size="small" severity="secondary" text
                        @click="$router.push({ name: 'goal-edit', params: { id: data.id } })" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { GoalService } from '@/service/GoalService';
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
    employee_id: '',
    goal_type:   null,
    status:      null,
});

const goalTypeOptions = [
    { label: 'Individual',  value: 'individual'  },
    { label: 'Department',  value: 'department'  },
    { label: 'Company',     value: 'company'     },
];

const statusOptions = [
    { label: 'Not Started',  value: 'not_started'  },
    { label: 'In Progress',  value: 'in_progress'  },
    { label: 'Completed',    value: 'completed'    },
    { label: 'Cancelled',    value: 'cancelled'    },
];

function statusSeverity(status) {
    const map = { not_started: 'secondary', in_progress: 'info', completed: 'success', cancelled: 'danger' };
    return map[status] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await GoalService.getList({
            page:        page.value,
            per_page:    perPage.value,
            employee_id: filters.value.employee_id || undefined,
            goal_type:   filters.value.goal_type   || undefined,
            status:      filters.value.status       || undefined,
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
