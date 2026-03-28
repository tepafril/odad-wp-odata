import { request } from '@/api/client';

export const EmploymentTypeService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`employment-types?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load employment types');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`employment-types/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load employment type');
        return data;
    },
    async create(body) {
        const { data, error } = await request('employment-types', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create employment type');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`employment-types/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update employment type');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`employment-types/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete employment type');
        return data;
    },
};
