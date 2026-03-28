import { request } from '@/api/client';

export const EmployeeService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`employees?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load employees');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`employees/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load employee');
        return data;
    },
    async create(body) {
        const { data, error } = await request('employees', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create employee');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`employees/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update employee');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`employees/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete employee');
        return data;
    },
    async getOrgChart(id) {
        const { data, error } = await request(`employees/${id}/org-chart`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load org chart');
        return data;
    },
    // Profile Photo
    async uploadPhoto(employeeId, file) {
        const formData = new FormData();
        formData.append('photo', file);
        const { data, error } = await request(`employees/${employeeId}/profile-photo`, { method: 'POST', body: formData });
        if (error) throw new Error(data?.message || error.message || 'Failed to upload photo');
        return data;
    },
    async removePhoto(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/profile-photo`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to remove photo');
        return data;
    },
    // Education
    async getEducation(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/education`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load education');
        return data;
    },
    async addEducation(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/education`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add education');
        return data;
    },
    async updateEducation(id, body) {
        const { data, error } = await request(`education/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update education');
        return data;
    },
    async removeEducation(id) {
        const { data, error } = await request(`education/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete education');
        return data;
    },
    // Work History
    async getWorkHistory(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/work-history`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load work history');
        return data;
    },
    async addWorkHistory(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/work-history`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add work history');
        return data;
    },
    async removeWorkHistory(id) {
        const { data, error } = await request(`work-history/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete work history');
        return data;
    },
    // Bank
    async getBankAccounts(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/bank-accounts`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load bank accounts');
        return data;
    },
    async addBankAccount(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/bank-accounts`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add bank account');
        return data;
    },
    async removeBankAccount(id) {
        const { data, error } = await request(`bank-accounts/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete bank account');
        return data;
    },
    // Documents
    async getDocuments(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/documents`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load documents');
        return data;
    },
    async addDocument(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/documents`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add document');
        return data;
    },
    // Movements
    async getMovements(employeeId) {
        const { data, error } = await request(`employees/${employeeId}/movements`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load movements');
        return data;
    },
    async addMovement(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/movements`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add movement');
        return data;
    },
};
