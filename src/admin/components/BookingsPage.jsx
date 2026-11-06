import { useState, useEffect } from 'react';
import { api, money } from '../../shared/api';
import { Badge, Button, Input, Loading, Modal, PageHeader, Select, Table } from '../../shared/ui';
import { cfg } from './config';

function paymentTone(status) {
  if (status === 'paid') return 'success';
  if (status === 'awaiting_payment') return 'warning';
  return 'neutral';
}

function methodLabel(method) {
  if (method === 'stripe') return 'Stripe';
  if (method === 'manual') return 'Manual';
  if (method === 'channel') return 'Channel';
  return 'Pay at hotel';
}

export default function BookingsPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [paymentTypes, setPaymentTypes] = useState([]);
  const [payModal, setPayModal] = useState(null);
  const [payTypeId, setPayTypeId] = useState('');
  const [payReference, setPayReference] = useState('');
  const [payError, setPayError] = useState('');
  const [paying, setPaying] = useState(false);
  const settings = cfg.settings || {};

  const load = () => {
    setLoading(true);
    Promise.all([api('/bookings'), api('/payment-types')])
      .then(([bookings, types]) => {
        setRows(bookings);
        setPaymentTypes(Array.isArray(types) ? types : []);
      })
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const act = async (id, action) => {
    await api(`/bookings/${id}/${action}`, { method: 'POST', body: { room_number: action === 'checkin' ? '101' : undefined } });
    load();
  };

  const confirmMarkPaid = async () => {
    if (!payModal) return;
    if (!payReference.trim()) {
      setPayError('Payment reference is required (cheque number, card transaction ID, transfer reference, etc.).');
      return;
    }
    setPaying(true);
    setPayError('');
    try {
      await api(`/bookings/${payModal.id}/mark-paid`, {
        method: 'POST',
        body: {
          payment_reference: payReference.trim(),
          offline_payment_type_id: payTypeId ? Number(payTypeId) : undefined,
        },
      });
      setPayModal(null);
      setPayTypeId('');
      setPayReference('');
      load();
    } catch (err) {
      setPayError(err.message || 'Could not mark paid');
    } finally {
      setPaying(false);
    }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader title="Bookings" subtitle="Confirm, check-in, check-out, and record payments" />
      <Table
        columns={[
          { key: 'booking_code', label: 'Code' },
          { key: 'guest', label: 'Guest', render: (r) => `${r.first_name || ''} ${r.last_name || ''}` },
          { key: 'room_name', label: 'Room' },
          {
            key: 'source',
            label: 'Source',
            render: (r) => <Badge tone={r.source && r.source !== 'direct' ? 'info' : 'neutral'}>{r.source || 'direct'}</Badge>,
          },
          { key: 'check_in', label: 'In' },
          { key: 'check_out', label: 'Out' },
          { key: 'total_amount', label: 'Total', render: (r) => money(r.total_amount, settings) },
          {
            key: 'payment_status',
            label: 'Payment',
            render: (r) => (
              <div className="space-y-0.5">
                <Badge tone={paymentTone(r.payment_status)}>{r.payment_status || 'pending'}</Badge>
                <p className="text-[11px] text-brand-500">{methodLabel(r.payment_method)}</p>
                {r.payment_reference && (
                  <p className="max-w-[10rem] truncate text-[11px] text-brand-600" title={r.payment_reference}>
                    Ref: {r.payment_reference}
                  </p>
                )}
              </div>
            ),
          },
          {
            key: 'booking_status',
            label: 'Status',
            render: (r) => <Badge tone={r.booking_status === 'checked_in' ? 'success' : 'info'}>{r.booking_status}</Badge>,
          },
          {
            key: 'actions',
            label: 'Actions',
            render: (r) => (
              <div className="flex flex-wrap gap-2">
                {r.payment_status !== 'paid' && (
                  <Button
                    variant="secondary"
                    className="!px-2 !py-1 text-xs"
                    onClick={() => {
                      setPayModal(r);
                      setPayTypeId(r.offline_payment_type_id ? String(r.offline_payment_type_id) : '');
                      setPayReference(r.payment_reference || '');
                      setPayError('');
                    }}
                  >
                    Mark paid
                  </Button>
                )}
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

      <Modal
        open={!!payModal}
        onClose={() => {
          if (!paying) {
            setPayModal(null);
            setPayError('');
          }
        }}
        className="max-w-md"
      >
        {payModal && (
          <div className="space-y-4 p-6">
            <h3 className="font-display text-xl font-semibold text-brand-950">Mark booking paid</h3>
            <p className="text-sm text-brand-600">
              {payModal.booking_code} · {money(payModal.total_amount, settings)}
              {payModal.payment_method === 'manual' ? ' · Manual / bank transfer' : ''}
            </p>
            <Input
              label="Payment reference *"
              value={payReference}
              onChange={(e) => setPayReference(e.target.value)}
              placeholder="Cheque #, card txn ID, bank transfer ref…"
              required
            />
            <Select label="Payment method (optional)" value={payTypeId} onChange={(e) => setPayTypeId(e.target.value)}>
              <option value="">— Select —</option>
              {paymentTypes.map((pt) => (
                <option key={pt.id} value={pt.id}>
                  {pt.name}
                </option>
              ))}
            </Select>
            {payError && <p className="text-sm text-red-600">{payError}</p>}
            <div className="flex justify-end gap-2">
              <Button variant="ghost" disabled={paying} onClick={() => setPayModal(null)}>
                Cancel
              </Button>
              <Button onClick={confirmMarkPaid} disabled={paying || !payReference.trim()}>
                {paying ? 'Saving…' : 'Confirm paid'}
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
