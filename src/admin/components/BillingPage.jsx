import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';
import { cfg } from './config';

export default function BillingPage() {
  const [tab, setTab] = useState('room');
  const [overview, setOverview] = useState(null);
  const [rows, setRows] = useState([]);
  const [period, setPeriod] = useState('month');
  const [restaurants, setRestaurants] = useState([]);
  const [guests, setGuests] = useState([]);
  const [form, setForm] = useState({});
  const settings = cfg.settings || {};

  const endpoints = {
    room: '/billing/room',
    restaurant: '/billing/restaurant',
    'non-border': '/billing/non-border-restaurant',
    laundry: '/billing/laundry',
    damage: '/billing/damage',
  };

  const load = () => {
    api(`/billing/overview?period=${period}`).then(setOverview);
    api(endpoints[tab]).then(setRows);
  };

  useEffect(() => {
    api('/restaurants').then(setRestaurants);
    api('/guests').then(setGuests);
  }, []);

  useEffect(load, [tab, period]);

  const submit = async () => {
    const status = form.payment_status || (tab === 'non-border' ? 'paid' : 'unpaid');
    if (status === 'paid' && !(form.payment_reference || '').trim()) {
      window.alert('Payment reference is required for paid bills (cheque number, card transaction ID, transfer reference, etc.).');
      return;
    }
    await api(endpoints[tab], {
      method: 'POST',
      body: {
        ...form,
        payment_status: status,
        payment_reference: (form.payment_reference || '').trim() || undefined,
      },
    });
    setForm({});
    load();
  };

  return (
    <div>
      <PageHeader
        title="Billing"
        subtitle="Rooms, restaurants, laundry and damage charges"
        actions={
          <div className="inline-flex rounded-lg border border-sand-200 bg-white p-1">
            {['day', 'week', 'month'].map((p) => (
              <button key={p} type="button" onClick={() => setPeriod(p)} className={`rounded-md px-3 py-1.5 text-sm font-semibold capitalize ${period === p ? 'bg-brand-700 text-white' : 'text-brand-700'}`}>
                {p}
              </button>
            ))}
          </div>
        }
      />

      {overview && (
        <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <Stat label="Room billing" value={money(overview.sums.room, settings)} />
          <Stat label="Restaurant" value={money(overview.sums.restaurant, settings)} />
          <Stat label="Walk-in restaurant" value={money(overview.sums.non_border_restaurant, settings)} />
          <Stat label="Laundry" value={money(overview.sums.laundry, settings)} />
          <Stat label="Damage" value={money(overview.sums.damage, settings)} />
          <Stat label="Total" value={money(overview.sums.total, settings)} />
        </div>
      )}

      <div className="mb-4 flex flex-wrap gap-2">
        {[
          ['room', 'Room bills'],
          ['restaurant', 'Restaurant'],
          ['non-border', 'Walk-in'],
          ['laundry', 'Laundry'],
          ['damage', 'Damage'],
        ].map(([id, label]) => (
          <button key={id} type="button" onClick={() => setTab(id)} className={`rounded-lg px-3 py-2 text-sm font-semibold ${tab === id ? 'bg-brand-700 text-white' : 'bg-white text-brand-700 border border-sand-200'}`}>
            {label}
          </button>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Add {tab} bill</h3>
          {tab === 'room' && (
            <>
              <Select label="Guest" value={form.guest_id || ''} onChange={(e) => setForm({ ...form, guest_id: e.target.value })}>
                <option value="">Select guest</option>
                {guests.map((g) => (
                  <option key={g.id} value={g.id}>{g.first_name} {g.last_name}</option>
                ))}
              </Select>
              <Input label="Booking ID" value={form.booking_id || ''} onChange={(e) => setForm({ ...form, booking_id: e.target.value })} />
              <Input label="Description" value={form.description || ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
              <Input label="Amount" type="number" value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
            </>
          )}
          {tab === 'restaurant' && (
            <>
              <Select label="Restaurant" value={form.restaurant_id || ''} onChange={(e) => setForm({ ...form, restaurant_id: e.target.value })}>
                <option value="">Select</option>
                {restaurants.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
              </Select>
              <Select label="Guest" value={form.guest_id || ''} onChange={(e) => setForm({ ...form, guest_id: e.target.value })}>
                <option value="">Select guest</option>
                {guests.map((g) => <option key={g.id} value={g.id}>{g.first_name} {g.last_name}</option>)}
              </Select>
              <Input label="Subtotal" type="number" value={form.subtotal || ''} onChange={(e) => setForm({ ...form, subtotal: e.target.value })} />
            </>
          )}
          {tab === 'non-border' && (
            <>
              <Select label="Restaurant" value={form.restaurant_id || ''} onChange={(e) => setForm({ ...form, restaurant_id: e.target.value })}>
                <option value="">Select</option>
                {restaurants.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
              </Select>
              <Input label="Guest name" value={form.guest_name || ''} onChange={(e) => setForm({ ...form, guest_name: e.target.value })} />
              <Input label="Phone" value={form.guest_phone || ''} onChange={(e) => setForm({ ...form, guest_phone: e.target.value })} />
              <Input label="Subtotal" type="number" value={form.subtotal || ''} onChange={(e) => setForm({ ...form, subtotal: e.target.value })} />
            </>
          )}
          {tab === 'laundry' && (
            <>
              <Select label="Guest" value={form.guest_id || ''} onChange={(e) => setForm({ ...form, guest_id: e.target.value })}>
                <option value="">Select guest</option>
                {guests.map((g) => <option key={g.id} value={g.id}>{g.first_name} {g.last_name}</option>)}
              </Select>
              <Textarea label="Items" value={form.items_description || ''} onChange={(e) => setForm({ ...form, items_description: e.target.value })} />
              <Input label="Amount" type="number" value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
            </>
          )}
          {tab === 'damage' && (
            <>
              <Select label="Guest" value={form.guest_id || ''} onChange={(e) => setForm({ ...form, guest_id: e.target.value })}>
                <option value="">Select guest</option>
                {guests.map((g) => <option key={g.id} value={g.id}>{g.first_name} {g.last_name}</option>)}
              </Select>
              <Textarea label="Damage description" value={form.damage_description || ''} onChange={(e) => setForm({ ...form, damage_description: e.target.value })} />
              <Input label="Amount" type="number" value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
            </>
          )}
          <Select
            label="Payment status"
            value={form.payment_status || (tab === 'non-border' ? 'paid' : 'unpaid')}
            onChange={(e) => setForm({ ...form, payment_status: e.target.value })}
          >
            <option value="unpaid">Unpaid</option>
            <option value="paid">Paid</option>
          </Select>
          <Input
            label="Payment reference"
            value={form.payment_reference || ''}
            onChange={(e) => setForm({ ...form, payment_reference: e.target.value })}
            placeholder="Cheque #, card txn ID, transfer ref…"
          />
          <p className="text-xs text-brand-500">Required when payment status is Paid.</p>
          <Button onClick={submit}>Save bill</Button>
        </Card>

        <div className="lg:col-span-2">
          <Table
            columns={[
              { key: 'id', label: 'ID' },
              { key: 'bill_number', label: 'Bill #', render: (r) => r.bill_number || r.description || '—' },
              { key: 'bill_date', label: 'Date' },
              { key: 'total_amount', label: 'Total', render: (r) => money(r.total_amount, settings) },
              { key: 'payment_status', label: 'Payment', render: (r) => <Badge tone={r.payment_status === 'paid' ? 'success' : 'warning'}>{r.payment_status}</Badge> },
              {
                key: 'payment_reference',
                label: 'Reference',
                render: (r) => r.payment_reference || '—',
              },
              {
                key: 'actions',
                label: '',
                render: (r) => (
                  <Button
                    variant="ghost"
                    className="!px-2 !py-1 text-xs"
                    onClick={async () => {
                      if (!window.confirm('Move this bill to trash?')) return;
                      await api(`${endpoints[tab]}/${r.id}`, { method: 'DELETE' });
                      load();
                    }}
                  >
                    Trash
                  </Button>
                ),
              },
            ]}
            rows={rows}
          />
        </div>
      </div>
    </div>
  );
}
