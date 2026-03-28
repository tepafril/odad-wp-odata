import { request } from '@/api/client';

export const ShiftService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`shifts?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load shifts');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`shifts/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load shift');
        return data;
    },
    async create(body) {
        const { data, error } = await request('shifts', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create shift');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`shifts/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update shift');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`shifts/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete shift');
        return data;
    },
    async getAssignments(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`shift-assignments?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load shift assignments');
        return data;
    },
    async getAssignmentById(id) {
        const { data, error } = await request(`shift-assignments/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load shift assignment');
        return data;
    },
    async createAssignment(body) {
        const { data, error } = await request('shift-assignments', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create shift assignment');
        return data;
    },
    async updateAssignment(id, body) {
        const { data, error } = await request(`shift-assignments/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update shift assignment');
        return data;
    },
    async deleteAssignment(id) {
        const { data, error } = await request(`shift-assignments/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete shift assignment');
        return data;
    },
};
