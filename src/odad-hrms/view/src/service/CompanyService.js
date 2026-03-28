import { request } from '@/api/client';

export const CompanyService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`companies?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load companies');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`companies/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load company');
        return data;
    },
    async create(body) {
        const { data, error } = await request('companies', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create company');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`companies/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update company');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`companies/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete company');
        return data;
    },
};
