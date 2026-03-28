import { request } from '@/api/client';

export const EmployeeSalaryService = {
    async getCurrent(employeeId, date = null) {
        const q = date ? `?date=${date}` : '';
        const { data, error } = await request(`employees/${employeeId}/salary${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load employee salary');
        return data;
    },
    async assign(employeeId, body) {
        const { data, error } = await request(`employees/${employeeId}/salary`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to assign salary');
        return data;
    },
    async update(employeeId, sid, body) {
        const { data, error } = await request(`employees/${employeeId}/salary/${sid}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update salary');
        return data;
    },
};
