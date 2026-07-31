import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';
import { cfg } from './config';

export default function Dashboard() {
  const [period, setPeriod] = useState('week');
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const settings = cfg.settings || {};

  useEffect(() => {
    setLoading(true);
    api(`/dashboard?period=${period}`)
      .then((d) => {
        const billing = d.billing_overview || {};
        billing.total =
          (billing.rooms || 0) +
          (billing.restaurants || 0) +
          (billing.non_border_restaurants || 0) +
          (billing.laundry || 0) +
          (billing.damage || 0);
        setData({ ...d, billing_overview: billing });
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [period]);

  if (loading) return <Loading />;
  if (!data) return <Empty title="Unable to load dashboard" />;

  const g = data.guest_overview;
  const b = data.billing_overview;

  return (
    <div>
      <PageHeader
        title="Operations overview"
        subtitle={`${data.from} → ${data.to}`}
        actions={
          <div className="inline-flex rounded-lg border border-sand-200 bg-white p-1">
            {['day', 'week', 'month'].map((p) => (
              <button
                key={p}
                type="button"
                onClick={() => setPeriod(p)}
                className={`rounded-md px-3 py-1.5 text-sm font-semibold capitalize ${
                  period === p ? 'bg-brand-700 text-white' : 'text-brand-700'
                }`}
              >
                {p}
              </button>
            ))}
          </div>
        }
      />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Stat label="Arrivals" value={g.arrivals} hint="Check-ins in period" />
        <Stat label="Departures" value={g.departures} hint="Check-outs in period" />
        <Stat label="In house" value={g.in_house} hint="Currently checked in" />
        <Stat label="Expected guests" value={g.expected} />
        <Stat label="Guest nights" value={g.total_guests} />
        <Stat label="New bookings" value={g.new_bookings} />
      </div>

      <div className="mb-6 grid gap-4 lg:grid-cols-2">
        <Card className="p-5">
          <h3 className="font-display text-xl font-semibold">Billing breakdown</h3>
          <div className="mt-4 space-y-3">
            {[
              ['Room bills', b.rooms],
              ['Restaurant', b.restaurants],
              ['Walk-in restaurant', b.non_border_restaurants],
              ['Laundry', b.laundry],
              ['Damage', b.damage],
            ].map(([label, value]) => (
              <div key={label} className="flex items-center justify-between border-b border-sand-100 pb-2 text-sm">
                <span className="text-brand-700">{label}</span>
                <span className="font-semibold">{money(value, settings)}</span>
              </div>
            ))}
            <div className="flex items-center justify-between pt-2 text-base font-bold">
              <span>Total revenue</span>
              <span className="text-brand-800">{money(b.total, settings)}</span>
            </div>
          </div>
        </Card>

        <Card className="p-5">
          <h3 className="font-display text-xl font-semibold">Occupancy</h3>
          <p className="mt-4 font-display text-5xl font-bold text-brand-800">{data.occupancy.rate}%</p>
          <p className="mt-2 text-sm text-brand-600">
            {data.occupancy.booked_nights} booked nights / {data.occupancy.capacity} capacity
          </p>
          <div className="mt-6 h-3 overflow-hidden rounded-full bg-sand-100">
            <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${Math.min(100, data.occupancy.rate)}%` }} />
          </div>
        </Card>
      </div>

      <Card className="p-5">
        <h3 className="mb-4 font-display text-xl font-semibold">Daily guests & billing</h3>
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          {data.daily_series.map((day) => (
            <div key={day.date} className="rounded-lg bg-sand-50 p-3">
              <p className="text-xs font-semibold text-brand-500">{day.date.slice(5)}</p>
              <p className="mt-1 text-lg font-bold">{day.guests} guests</p>
              <p className="text-xs text-brand-600">Rooms {money(day.room_billing, settings)}</p>
              <p className="text-xs text-brand-600">Rest. {money(day.restaurant_billing, settings)}</p>
            </div>
          ))}
        </div>
      </Card>

      <div className="mt-6">
        <h3 className="mb-3 font-display text-xl font-semibold">Recent bookings</h3>
        <Table
          columns={[
            { key: 'booking_code', label: 'Code' },
            { key: 'guest', label: 'Guest', render: (r) => `${r.first_name || ''} ${r.last_name || ''}` },
            { key: 'room_name', label: 'Room' },
            { key: 'check_in', label: 'Check-in' },
            { key: 'check_out', label: 'Check-out' },
            { key: 'total_amount', label: 'Total', render: (r) => money(r.total_amount, settings) },
            {
              key: 'booking_status',
              label: 'Status',
              render: (r) => <Badge tone="info">{r.booking_status}</Badge>,
            },
          ]}
          rows={data.recent_bookings}
        />
      </div>
    </div>
  );
}
