import { request } from '@/api/client';

export const LeaveApplicationService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`leave/applications?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave applications');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`leave/applications/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave application');
        return data;
    },
    async create(body) {
        const { data, error } = await request('leave/applications', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create leave application');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`leave/applications/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update leave application');
        return data;
    },
    async approve(id, comment = '') {
        const { data, error } = await request(`leave/applications/${id}/approve`, { method: 'POST', body: JSON.stringify({ approval_comment: comment }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to approve leave application');
        return data;
    },
    async reject(id, comment = '') {
        const { data, error } = await request(`leave/applications/${id}/reject`, { method: 'POST', body: JSON.stringify({ approval_comment: comment }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to reject leave application');
        return data;
    },
    async cancel(id) {
        const { data, error } = await request(`leave/applications/${id}/cancel`, { method: 'POST', body: JSON.stringify({}) });
        if (error) throw new Error(data?.message || error.message || 'Failed to cancel leave application');
        return data;
    },
};
