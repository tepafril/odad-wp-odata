import { request } from '@/api/client';

export const ApplicantService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`applicants?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load applicants');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`applicants/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load applicant');
        return data;
    },
    async create(body) {
        const { data, error } = await request('applicants', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create applicant');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`applicants/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update applicant');
        return data;
    },
    async advance(id) {
        const { data, error } = await request(`applicants/${id}/advance`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to advance applicant stage');
        return data;
    },
    async reject(id) {
        const { data, error } = await request(`applicants/${id}/reject`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to reject applicant');
        return data;
    },
    async hire(id) {
        const { data, error } = await request(`applicants/${id}/hire`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to hire applicant');
        return data;
    },
};
