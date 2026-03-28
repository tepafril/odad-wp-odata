import { request } from '@/api/client';

export const SalaryStructureService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`salary-structures?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load salary structures');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`salary-structures/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load salary structure');
        return data;
    },
    async create(body) {
        const { data, error } = await request('salary-structures', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create salary structure');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`salary-structures/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update salary structure');
        return data;
    },
    async getDetails(id) {
        const { data, error } = await request(`salary-structures/${id}/details`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load structure details');
        return data;
    },
    async addDetail(id, body) {
        const { data, error } = await request(`salary-structures/${id}/details`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add structure detail');
        return data;
    },
    async removeDetail(id, did) {
        const { data, error } = await request(`salary-structures/${id}/details/${did}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to remove structure detail');
        return data;
    },
};
