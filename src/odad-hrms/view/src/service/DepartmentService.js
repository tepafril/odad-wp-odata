import { request } from '@/api/client';

export const DepartmentService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`departments?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load departments');
        return data;
    },
    async getTree() {
        const { data, error } = await request('departments/tree');
        if (error) throw new Error(data?.message || error.message || 'Failed to load department tree');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`departments/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load department');
        return data;
    },
    async create(body) {
        const { data, error } = await request('departments', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create department');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`departments/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update department');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`departments/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete department');
        return data;
    },
};
