<script setup>
import { computed, ref, useAttrs, watch } from 'vue';
import { HolidayService } from '@/service/HolidayService';
import { getPrimeDateFormat } from '@/utils/dateFormat';

const props = defineProps({
    dateFormat: {
        type: String,
        default: () => getPrimeDateFormat(),
    },
});

const model = defineModel();
const attrs = useAttrs();

const holidays = ref([]);
const loadedYears = ref(new Set());

const holidayMap = computed(() => {
    const map = {};
    for (const h of holidays.value) {
        map[h.date] = h.name;
    }
    return map;
});

async function loadYear(year) {
    if (!year || loadedYears.value.has(year)) return;
    loadedYears.value.add(year);
    try {
        const res = await HolidayService.getList({ year, per_page: 500 });
        const items = res?.items ?? [];
        const existing = new Set(holidays.value.map(h => h.date));
        holidays.value.push(...items.filter(h => !existing.has(h.date)));
    } catch {
        loadedYears.value.delete(year);
    }
}

function onMonthChange(event) {
    loadYear(event.year);
}

function isHoliday(date) {
    const key = `${date.year}-${String(date.month + 1).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`;
    return key in holidayMap.value;
}

function holidayName(date) {
    const key = `${date.year}-${String(date.month + 1).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`;
    return holidayMap.value[key] || '';
}

// Load current year on mount
loadYear(new Date().getFullYear());

// When model value changes, ensure that year is loaded too
watch(model, (val) => {
    if (!val) return;
    const d = val instanceof Date ? val : new Date(val);
    if (!isNaN(d)) loadYear(d.getFullYear());
}, { immediate: true });
</script>

<template>
    <DatePicker v-model="model" v-bind="attrs" :dateFormat="props.dateFormat" @month-change="onMonthChange">
        <template #date="{ date }">
            <span
                v-if="isHoliday(date)"
                v-tooltip="holidayName(date)"
                class="inline-flex items-center justify-center w-8 h-8 rounded-full"
                style="background-color: var(--p-red-100); color: var(--p-red-600);"
            >{{ date.day }}</span>
            <span v-else>{{ date.day }}</span>
        </template>
    </DatePicker>
</template>
