import { request } from '@/api/client';

export const HolidayService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`holidays?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load holidays');
        return data;
    },
};
