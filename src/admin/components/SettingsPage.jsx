import { useState, useEffect } from 'react';
import { api, money } from '../../shared/api';
import { Button, Card, Input, PageHeader, Select } from '../../shared/ui';
import { cfg } from './config';

export default function SettingsPage() {
  const [form, setForm] = useState(cfg.settings || {});
  const [saved, setSaved] = useState(false);
  const [discounts, setDiscounts] = useState([]);
  const [discount, setDiscount] = useState({ code: '', name: '', discount_type: 'percent', discount_value: '', min_nights: 1 });
  const [seeding, setSeeding] = useState(false);
  const [seedMsg, setSeedMsg] = useState('');
  const [seedErr, setSeedErr] = useState('');

  useEffect(() => {
    api('/settings').then(setForm);
    api('/discounts').then(setDiscounts);
  }, []);

  const loadDemo = async () => {
    setSeeding(true);
    setSeedMsg('');
    setSeedErr('');
    try {
      const res = await api('/seed-demo', { method: 'POST' });
      setSeedMsg(res.message || 'Demo data loaded.');
      setForm(await api('/settings'));
      setDiscounts(await api('/discounts'));
    } catch (err) {
      setSeedErr(err.message);
    } finally {
      setSeeding(false);
    }
  };

  return (
    <div>
      <PageHeader title="Settings" subtitle="Hotel configuration and discount codes" />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-3 p-5">
          <Input label="Hotel name" value={form.hotel_name || ''} onChange={(e) => setForm({ ...form, hotel_name: e.target.value })} />
          <div className="grid grid-cols-2 gap-2">
            <Input label="Currency" value={form.currency || ''} onChange={(e) => setForm({ ...form, currency: e.target.value })} />
            <Input label="Symbol" value={form.currency_symbol || ''} onChange={(e) => setForm({ ...form, currency_symbol: e.target.value })} />
          </div>
          <Input label="Tax rate (%)" type="number" value={form.tax_rate || ''} onChange={(e) => setForm({ ...form, tax_rate: e.target.value })} />
          <div className="grid grid-cols-2 gap-2">
            <Input label="Check-in time" value={form.check_in_time || ''} onChange={(e) => setForm({ ...form, check_in_time: e.target.value })} />
            <Input label="Check-out time" value={form.check_out_time || ''} onChange={(e) => setForm({ ...form, check_out_time: e.target.value })} />
          </div>
          <Input label="Phone" value={form.phone || ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Email" value={form.email || ''} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input label="Address" value={form.address || ''} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          <Select
            label="Default frontend language"
            value={form.frontend_language || 'en'}
            onChange={(e) => setForm({ ...form, frontend_language: e.target.value })}
          >
            <option value="en">English</option>
            <option value="es">Español (Spanish)</option>
            <option value="fr">Français (French)</option>
            <option value="de">Deutsch (German)</option>
            <option value="bn">বাংলা (Bengali)</option>
            <option value="ar">العربية (Arabic)</option>
          </Select>
          <p className="-mt-1 text-xs text-brand-500">
            Guests can still switch language on the booking form; this is the default when they first visit.
          </p>
          <div>
            <label className="mb-1 block text-sm font-medium text-brand-800">Frontend primary color</label>
            <div className="flex flex-wrap items-center gap-3">
              <input
                type="color"
                className="h-10 w-14 cursor-pointer rounded border border-sand-200 bg-white p-1"
                value={form.frontend_primary_color || '#27584c'}
                onChange={(e) => setForm({ ...form, frontend_primary_color: e.target.value })}
                aria-label="Frontend primary color"
              />
              <Input
                label=""
                className="w-36 font-mono text-sm"
                value={form.frontend_primary_color || '#27584c'}
                onChange={(e) => setForm({ ...form, frontend_primary_color: e.target.value })}
                placeholder="#27584c"
              />
              <Button
                type="button"
                variant="ghost"
                className="!px-2 !py-1 text-xs"
                onClick={() => setForm({ ...form, frontend_primary_color: '#27584c' })}
              >
                Reset
              </Button>
            </div>
            <p className="mt-1 text-xs text-brand-500">
              Used on the public booking form (header, buttons, accents) so it can match your theme.
            </p>
            <div
              className="mt-3 overflow-hidden rounded-lg border border-sand-200"
              style={{
                background: `linear-gradient(135deg, ${form.frontend_primary_color || '#27584c'}, ${form.frontend_primary_color || '#27584c'}cc)`,
              }}
            >
              <div className="px-4 py-3 text-sm font-semibold text-white">Preview · booking header</div>
              <div className="bg-white/95 px-4 py-3">
                <span
                  className="inline-flex rounded-lg px-3 py-1.5 text-xs font-semibold text-white"
                  style={{ backgroundColor: form.frontend_primary_color || '#27584c' }}
                >
                  Search rooms
                </span>
              </div>
            </div>
          </div>
          <Button
            onClick={async () => {
              const savedSettings = await api('/settings', { method: 'POST', body: form });
              setForm(savedSettings);
              setSaved(true);
              setTimeout(() => setSaved(false), 2000);
            }}
          >
            Save settings
          </Button>
          {saved && <p className="text-sm text-emerald-700">Settings saved.</p>}
          <p className="text-xs text-brand-500">
            Frontend shortcode: <code>[shmpp_search]</code>
            <br />
            Online card payments: <strong>Payment settings</strong> in the admin menu.
          </p>
        </Card>

        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Discount codes</h3>
          <Input label="Code" value={discount.code} onChange={(e) => setDiscount({ ...discount, code: e.target.value })} />
          <Input label="Name" value={discount.name} onChange={(e) => setDiscount({ ...discount, name: e.target.value })} />
          <Select label="Type" value={discount.discount_type} onChange={(e) => setDiscount({ ...discount, discount_type: e.target.value })}>
            <option value="percent">Percent</option>
            <option value="fixed">Fixed</option>
          </Select>
          <Input label="Value" type="number" value={discount.discount_value} onChange={(e) => setDiscount({ ...discount, discount_value: e.target.value })} />
          <Input label="Min nights" type="number" value={discount.min_nights} onChange={(e) => setDiscount({ ...discount, min_nights: e.target.value })} />
          <Button
            onClick={async () => {
              await api('/discounts', { method: 'POST', body: discount });
              setDiscount({ code: '', name: '', discount_type: 'percent', discount_value: '', min_nights: 1 });
              setDiscounts(await api('/discounts'));
            }}
          >
            Create discount
          </Button>
          <ul className="space-y-2 text-sm">
            {discounts.map((d) => (
              <li key={d.id} className="flex items-center justify-between gap-2 rounded-lg bg-sand-50 px-3 py-2">
                <span>
                  <strong>{d.code}</strong> — {d.name} ({d.discount_type === 'percent' ? `${d.discount_value}%` : money(d.discount_value, form)})
                </span>
                <Button
                  variant="ghost"
                  className="!px-2 !py-1 text-xs"
                  onClick={async () => {
                    if (!window.confirm(`Move discount ${d.code} to trash?`)) return;
                    await api(`/discounts/${d.id}`, { method: 'DELETE' });
                    setDiscounts(await api('/discounts'));
                  }}
                >
                  Trash
                </Button>
              </li>
            ))}
          </ul>
        </Card>
      </div>

      <Card className="mt-6 space-y-3 p-5">
        <h3 className="font-display text-lg font-semibold">Demo data</h3>
        <p className="text-sm text-brand-600">
          Load a realistic sample hotel dataset: room types with photos, amenities, guests, bookings, restaurants,
          staff, salaries, and billing. Only works when no room types exist yet.
        </p>
        <Button variant="secondary" onClick={loadDemo} disabled={seeding}>
          {seeding ? 'Loading demo data…' : 'Load demo hotel data'}
        </Button>
        {seedMsg && <p className="text-sm text-emerald-700">{seedMsg}</p>}
        {seedErr && <p className="text-sm text-red-600">{seedErr}</p>}
      </Card>
    </div>
  );
}
