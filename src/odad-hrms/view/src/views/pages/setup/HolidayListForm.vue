<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { HolidayListService } from '@/service/HolidayListService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!props.editId);

const form = reactive({
    name:   '',
    year:   new Date().getFullYear(),
    status: 'active',
});

// Inline holidays management
const holidays = ref([]); // [{ name, date, is_restricted, description }]

const statusOptions = [
    { label: 'Active',   value: 'active'   },
    { label: 'Inactive', value: 'inactive' },
];

function reset() {
    Object.assign(form, {
        name: '', year: new Date().getFullYear(), status: 'active',
    });
    holidays.value = [];
}

function addHoliday() {
    holidays.value.push({ name: '', date: null, is_restricted: false, description: '' });
}

function removeHoliday(idx) {
    holidays.value.splice(idx, 1);
}

function toDateStr(val) {
    if (!val) return null;
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    const d = new Date(val);
    return isNaN(d) ? null : d.toISOString().slice(0, 10);
}

async function loadItem(id) {
    const [list, hols] = await Promise.all([
        HolidayListService.getById(id),
        HolidayListService.getHolidays(id),
    ]);
    Object.assign(form, {
        name:   list.name   ?? '',
        year:   Number(list.year ?? new Date().getFullYear()),
        status: list.status ?? 'active',
    });
    holidays.value = (hols?.items ?? []).map(h => ({
        name:          h.name          ?? '',
        date:          h.date          ?? null,
        is_restricted: !!Number(h.is_restricted),
        description:   h.description   ?? '',
    }));
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

async function save() {
    if (!form.name.trim()) return toast.add({ severity: 'warn', detail: 'List name is required.', life: 3000 });
    if (holidays.value.some(h => !h.name.trim() || !h.date))
        return toast.add({ severity: 'warn', detail: 'Each holiday must have a name and date.', life: 3000 });

    saving.value = true;
    try {
        const body = {
            name:   form.name.trim(),
            year:   Number(form.year),
            status: form.status,
        };
        let listId = props.editId;
        if (isEdit.value) {
            await HolidayListService.update(listId, body);
        } else {
            const created = await HolidayListService.create(body);
            listId = created?.id ?? created;
        }

        // Build holiday payload with DB-compatible fields
        const holidayPayload = holidays.value.map(h => ({
            name:          h.name.trim(),
            date:          toDateStr(h.date),
            is_restricted: h.is_restricted ? 1 : 0,
            description:   h.description.trim() || null,
        }));

        if (isEdit.value) {
            // Replace all holidays (delete old + insert new)
            await HolidayListService.replaceHolidays(listId, holidayPayload);
        } else {
            // Create holidays one by one for a new list
            for (const h of holidayPayload) {
                await HolidayListService.createHoliday(listId, h);
            }
        }

        toast.add({ severity: 'success', detail: isEdit.value ? 'Holiday list updated.' : 'Holiday list created.', life: 3000 });
        emit('saved');
        emit('update:modelValue', false);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}

defineExpose({ save });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">List Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. Public Holidays 2025" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Year</label>
                <InputNumber v-model="form.year" :min="2000" :max="2100" :useGrouping="false" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
            </div>
        </div>

        <div class="border-t pt-4">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold">Holidays</span>
                <Button label="Add Holiday" icon="pi pi-plus" size="small" outlined @click="addHoliday" />
            </div>
            <div v-if="holidays.length === 0" class="text-surface-500 text-sm text-center py-3">
                No holidays added yet.
            </div>
            <div v-for="(h, idx) in holidays" :key="idx"
                class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border rounded p-3 mb-2">
                <div class="flex flex-col gap-1 md:col-span-3">
                    <label class="text-sm font-medium">Name</label>
                    <InputText v-model="h.name" fluid placeholder="Holiday name" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <label class="text-sm font-medium">Date</label>
                    <HrDatePicker v-model="h.date" showIcon fluid />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                    <label class="text-sm font-medium">Description</label>
                    <InputText v-model="h.description" fluid placeholder="Optional" />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="h.is_restricted" :binary="true" inputId="restricted" />
                        <label for="restricted" class="text-sm">Restricted</label>
                    </div>
                </div>
                <div class="flex items-end md:col-span-1">
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small" @click="removeHoliday(idx)" />
                </div>
            </div>
        </div>
    </div>
</template>
