import { request } from '@/api/client';

export const BranchService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`branches?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load branches');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`branches/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load branch');
        return data;
    },
    async create(body) {
        const { data, error } = await request('branches', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create branch');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`branches/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update branch');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`branches/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete branch');
        return data;
    },
};
