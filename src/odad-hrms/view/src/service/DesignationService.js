import { request } from '@/api/client';

export const DesignationService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`designations?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load designations');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`designations/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load designation');
        return data;
    },
    async create(body) {
        const { data, error } = await request('designations', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create designation');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`designations/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update designation');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`designations/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete designation');
        return data;
    },
};
