import { request } from '@/api/client';

export const LoanService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`loans?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load loans');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`loans/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load loan');
        return data;
    },
    async create(body) {
        const { data, error } = await request('loans', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create loan');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`loans/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update loan');
        return data;
    },
    async approve(id) {
        const { data, error } = await request(`loans/${id}/approve`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to approve loan');
        return data;
    },
    async disburse(id) {
        const { data, error } = await request(`loans/${id}/disburse`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to disburse loan');
        return data;
    },
    async getRepayments(id) {
        const { data, error } = await request(`loans/${id}/repayments`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load loan repayments');
        return data;
    },
};
