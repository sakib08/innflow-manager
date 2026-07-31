import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';
import { cfg } from './config';

export default function BookingsPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const settings = cfg.settings || {};

  const load = () => {
    setLoading(true);
    api('/bookings')
      .then(setRows)
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const act = async (id, action) => {
    await api(`/bookings/${id}/${action}`, { method: 'POST', body: { room_number: action === 'checkin' ? '101' : undefined } });
    load();
  };

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader title="Bookings" subtitle="Confirm, check-in and check-out guests" />
      <Table
        columns={[
          { key: 'booking_code', label: 'Code' },
          { key: 'guest', label: 'Guest', render: (r) => `${r.first_name || ''} ${r.last_name || ''}` },
          { key: 'room_name', label: 'Room' },
          { key: 'check_in', label: 'In' },
          { key: 'check_out', label: 'Out' },
          { key: 'total_amount', label: 'Total', render: (r) => money(r.total_amount, settings) },
          {
            key: 'booking_status',
            label: 'Status',
            render: (r) => <Badge tone={r.booking_status === 'checked_in' ? 'success' : 'info'}>{r.booking_status}</Badge>,
          },
          {
            key: 'actions',
            label: 'Actions',
            render: (r) => (
              <div className="flex gap-2">
                {r.booking_status === 'confirmed' && (
                  <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => act(r.id, 'checkin')}>
                    Check-in
                  </Button>
                )}
                {r.booking_status === 'checked_in' && (
                  <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => act(r.id, 'checkout')}>
                    Check-out
                  </Button>
                )}
                <Button
                  variant="ghost"
                  className="!px-2 !py-1 text-xs"
                  onClick={async () => {
                    if (!window.confirm(`Move booking ${r.booking_code} to trash?`)) return;
                    await api(`/bookings/${r.id}`, { method: 'DELETE' });
                    load();
                  }}
                >
                  Trash
                </Button>
              </div>
            ),
          },
        ]}
        rows={rows}
      />
    </div>
  );
}
