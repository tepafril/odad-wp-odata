import { request } from '@/api/client';

export const LeaveTypeService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`leave-types?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave types');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`leave-types/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave type');
        return data;
    },
    async create(body) {
        const { data, error } = await request('leave-types', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create leave type');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`leave-types/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update leave type');
        return data;
    },
};
