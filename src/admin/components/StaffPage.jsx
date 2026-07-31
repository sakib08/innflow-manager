import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';
import { cfg } from './config';

function emptyEmployeeForm() {
  return {
    id: null,
    first_name: '',
    last_name: '',
    role_id: '',
    email: '',
    phone: '',
    hire_date: '',
    status: 'active',
    address: '',
  };
}

export default function StaffPage() {
  const [employees, setEmployees] = useState([]);
  const [roles, setRoles] = useState([]);
  const [salaries, setSalaries] = useState([]);
  const [form, setForm] = useState(emptyEmployeeForm());
  const [saving, setSaving] = useState(false);
  const [salary, setSalary] = useState({ employee_id: '', salary_month: '', base_salary: '', allowances: 0, deductions: 0 });
  const settings = cfg.settings || {};
  const editing = !!form.id;

  const load = () => {
    Promise.all([api('/employees'), api('/employee-roles'), api('/salaries')]).then(([e, r, s]) => {
      setEmployees(e);
      setRoles(r);
      setSalaries(s);
    });
  };

  useEffect(load, []);

  const startEdit = (employee) => {
    setForm({
      id: employee.id,
      first_name: employee.first_name || '',
      last_name: employee.last_name || '',
      role_id: employee.role_id ? String(employee.role_id) : '',
      email: employee.email || '',
      phone: employee.phone || '',
      hire_date: employee.hire_date || '',
      status: employee.status || 'active',
      address: employee.address || '',
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const cancelEdit = () => setForm(emptyEmployeeForm());

  const saveEmployee = async () => {
    if (!form.first_name || !form.last_name) return;
    setSaving(true);
    try {
      const body = {
        first_name: form.first_name,
        last_name: form.last_name,
        role_id: form.role_id,
        email: form.email,
        phone: form.phone,
        hire_date: form.hire_date,
        status: form.status,
        address: form.address,
      };
      if (editing) {
        await api(`/employees/${form.id}`, { method: 'POST', body });
      } else {
        await api('/employees', { method: 'POST', body });
      }
      setForm(emptyEmployeeForm());
      load();
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader title="Staff" subtitle="Employees, roles and salary records" />
      <div className="grid gap-6 xl:grid-cols-2">
        <Card className="space-y-3 p-5">
          <div className="flex items-center justify-between gap-2">
            <h3 className="font-display text-lg font-semibold">{editing ? 'Edit employee' : 'Add employee'}</h3>
            {editing && (
              <Button variant="ghost" className="!px-2 !py-1 text-xs" onClick={cancelEdit}>
                Cancel
              </Button>
            )}
          </div>
          {editing && (
            <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
              Editing employee #{form.id}. Changes will update the existing record.
            </p>
          )}
          <div className="grid grid-cols-2 gap-2">
            <Input label="First name" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
            <Input label="Last name" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
          </div>
          <Select label="Role" value={form.role_id} onChange={(e) => setForm({ ...form, role_id: e.target.value })}>
            <option value="">Select role</option>
            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </Select>
          <Input label="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Hire date" type="date" value={form.hire_date || ''} onChange={(e) => setForm({ ...form, hire_date: e.target.value })} />
          <Select label="Status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </Select>
          <Textarea label="Address" rows={2} value={form.address || ''} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          <div className="flex flex-wrap gap-2">
            <Button onClick={saveEmployee} disabled={saving || !form.first_name || !form.last_name}>
              {saving ? 'Saving…' : editing ? 'Update employee' : 'Save employee'}
            </Button>
            {editing && (
              <Button variant="secondary" onClick={cancelEdit}>
                Cancel edit
              </Button>
            )}
          </div>
        </Card>

        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Record salary</h3>
          <Select label="Employee" value={salary.employee_id} onChange={(e) => setSalary({ ...salary, employee_id: e.target.value })}>
            <option value="">Select</option>
            {employees.map((e) => <option key={e.id} value={e.id}>{e.first_name} {e.last_name}</option>)}
          </Select>
          <Input
            label="Month"
            type="month"
            value={salary.salary_month ? salary.salary_month.slice(0, 7) : ''}
            onChange={(e) => setSalary({ ...salary, salary_month: e.target.value ? `${e.target.value}-01` : '' })}
          />
          <Input label="Base salary" type="number" value={salary.base_salary} onChange={(e) => setSalary({ ...salary, base_salary: e.target.value })} />
          <div className="grid grid-cols-2 gap-2">
            <Input label="Allowances" type="number" value={salary.allowances} onChange={(e) => setSalary({ ...salary, allowances: e.target.value })} />
            <Input label="Deductions" type="number" value={salary.deductions} onChange={(e) => setSalary({ ...salary, deductions: e.target.value })} />
          </div>
          <Button
            onClick={async () => {
              await api('/salaries', { method: 'POST', body: salary });
              setSalary({ employee_id: '', salary_month: '', base_salary: '', allowances: 0, deductions: 0 });
              load();
            }}
          >
            Save salary
          </Button>
          <p className="text-xs text-brand-500">
            Saving again for the same employee and month updates that record instead of creating a new one.
          </p>
        </Card>
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <div>
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 className="font-display text-xl font-semibold">Employees</h3>
            <div className="flex flex-wrap gap-2">
              <Button
                variant="secondary"
                className="!px-2 !py-1 text-xs"
                onClick={() => window.location.assign(buildApiUrl('/employees/export', { format: 'csv' }))}
              >
                Export CSV
              </Button>
              <Button
                variant="secondary"
                className="!px-2 !py-1 text-xs"
                onClick={() => window.location.assign(buildApiUrl('/employees/export', { format: 'xlsx' }))}
              >
                Export Excel
              </Button>
            </div>
          </div>
          <Table
            columns={[
              { key: 'employee_code', label: 'Code' },
              { key: 'name', label: 'Name', render: (r) => `${r.first_name} ${r.last_name}` },
              { key: 'role_name', label: 'Role' },
              { key: 'phone', label: 'Phone' },
              { key: 'status', label: 'Status', render: (r) => <Badge tone={r.status === 'active' ? 'success' : 'neutral'}>{r.status}</Badge> },
              {
                key: 'actions',
                label: 'Actions',
                render: (r) => (
                  <div className="flex gap-2">
                    <Button
                      variant="secondary"
                      className="!px-2 !py-1 text-xs"
                      onClick={() => startEdit(r)}
                    >
                      Edit
                    </Button>
                    <Button
                      variant="ghost"
                      className="!px-2 !py-1 text-xs"
                      onClick={async () => {
                        if (!window.confirm(`Move “${r.first_name} ${r.last_name}” to trash?`)) return;
                        await api(`/employees/${r.id}`, { method: 'DELETE' });
                        load();
                      }}
                    >
                      Trash
                    </Button>
                  </div>
                ),
              },
            ]}
            rows={employees}
          />
        </div>
        <div>
          <h3 className="mb-3 font-display text-xl font-semibold">Salaries</h3>
          <p className="mb-2 text-xs text-brand-500">
            Each employee's most recent month is shown as <Badge tone="success">Current</Badge>; earlier months are
            kept for history and marked <Badge tone="neutral">Old</Badge>.
          </p>
          <Table
            columns={[
              { key: 'employee', label: 'Employee', render: (r) => `${r.first_name || ''} ${r.last_name || ''}` },
              { key: 'salary_month', label: 'Month' },
              { key: 'net_salary', label: 'Net', render: (r) => money(r.net_salary, settings) },
              { key: 'payment_status', label: 'Payment', render: (r) => <Badge tone={r.payment_status === 'paid' ? 'success' : 'warning'}>{r.payment_status}</Badge> },
              {
                key: 'is_latest',
                label: 'Record',
                render: (r) => (r.is_latest ? <Badge tone="success">Current</Badge> : <Badge tone="neutral">Old</Badge>),
              },
            ]}
            rows={salaries}
            rowClassName={(r) => (!r.is_latest ? 'opacity-50 grayscale-[30%]' : '')}
          />
        </div>
      </div>
    </div>
  );
}
