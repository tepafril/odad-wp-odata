import { request } from '@/api/client';

export const LeavePolicyService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`leave-policies?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave policies');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`leave-policies/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave policy');
        return data;
    },
    async getDetails(policyId) {
        const { data, error } = await request(`leave-policies/${policyId}/details?per_page=100`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load policy details');
        return data;
    },
    async create(body) {
        const { data, error } = await request('leave-policies', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create leave policy');
        return data;
    },
    async createDetail(policyId, body) {
        const { data, error } = await request(`leave-policies/${policyId}/details`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add policy detail');
        return data;
    },
    async replaceDetails(policyId, details) {
        const { data, error } = await request(`leave-policies/${policyId}/details`, { method: 'PUT', body: JSON.stringify({ details }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to save policy details');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`leave-policies/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update leave policy');
        return data;
    },
    async getAssignments(policyId) {
        const { data, error } = await request(`leave-policies/${policyId}/assignments?per_page=100`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load policy assignments');
        return data;
    },
    async createAssignment(policyId, body) {
        const { data, error } = await request(`leave-policies/${policyId}/assignments`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add policy assignment');
        return data;
    },
    async allocate(policyId) {
        const { data, error } = await request(`leave-policies/${policyId}/allocate`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to allocate leave balances');
        return data;
    },
    async allocateAll(year) {
        const body = year ? JSON.stringify({ year }) : undefined;
        const { data, error } = await request('leave-policies/allocate-all', { method: 'POST', body });
        if (error) throw new Error(data?.message || error.message || 'Failed to run global allocation');
        return data;
    },
    async deleteAssignment(policyId, assignmentId) {
        const { data, error } = await request(`leave-policies/${policyId}/assignments/${assignmentId}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete policy assignment');
        return data;
    },
};
