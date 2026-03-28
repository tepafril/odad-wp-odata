import { request } from '@/api/client';

export const CompensatoryRequestService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`compensatory-requests?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load compensatory requests');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`compensatory-requests/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load compensatory request');
        return data;
    },
    async create(body) {
        const { data, error } = await request('compensatory-requests', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create compensatory request');
        return data;
    },
    async approve(id, comment = '') {
        const { data, error } = await request(`compensatory-requests/${id}/approve`, { method: 'POST', body: JSON.stringify({ approval_comment: comment }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to approve compensatory request');
        return data;
    },
    async reject(id, comment = '') {
        const { data, error } = await request(`compensatory-requests/${id}/reject`, { method: 'POST', body: JSON.stringify({ approval_comment: comment }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to reject compensatory request');
        return data;
    },
};
