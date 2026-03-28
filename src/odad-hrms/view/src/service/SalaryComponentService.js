import { request } from '@/api/client';

export const SalaryComponentService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`salary-components?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load salary components');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`salary-components/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load salary component');
        return data;
    },
    async create(body) {
        const { data, error } = await request('salary-components', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create salary component');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`salary-components/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update salary component');
        return data;
    },
};
