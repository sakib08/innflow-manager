import { useState, useEffect } from 'react';
import { api } from '../../shared/api';
import { Button, Card, Input, PageHeader, Textarea } from '../../shared/ui';
import { cfg } from './config';

export default function PaymentSettingsPage() {
  const [form, setForm] = useState(cfg.settings || {});
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [paymentTypes, setPaymentTypes] = useState([]);
  const [newType, setNewType] = useState({ name: '', description: '' });

  const load = async () => {
    setForm(await api('/settings'));
    setPaymentTypes(await api('/payment-types'));
  };

  useEffect(() => {
    load().catch((err) => setError(err.message));
  }, []);

  const savePayments = async () => {
    setError('');
    try {
      const savedSettings = await api('/settings', {
        method: 'POST',
        body: {
          stripe_enabled: !!form.stripe_enabled,
          stripe_publishable_key: form.stripe_publishable_key || '',
          stripe_secret_key: form.stripe_secret_key || '',
          stripe_webhook_secret: form.stripe_webhook_secret || '',
          manual_payment_enabled: !!form.manual_payment_enabled,
          manual_payment_title: form.manual_payment_title || '',
          manual_payment_instructions: form.manual_payment_instructions || '',
        },
      });
      setForm((prev) => ({ ...prev, ...savedSettings }));
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div>
      <PageHeader title="Payment settings" subtitle="Online, manual, and offline payment methods" />

      {error && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Stripe payments</h3>
          <p className="text-sm text-brand-600">
            Enable card payments on the public booking form. Guests are redirected to Stripe Checkout, then return to
            the booking page.
          </p>
          <label className="flex items-center gap-2 text-sm font-medium text-brand-800">
            <input
              type="checkbox"
              className="rounded border-sand-300 text-brand-700 focus:ring-brand-500"
              checked={!!form.stripe_enabled}
              onChange={(e) => setForm({ ...form, stripe_enabled: e.target.checked })}
            />
            Enable Stripe on site checkout
          </label>
          <Input
            label="Publishable key"
            value={form.stripe_publishable_key || ''}
            onChange={(e) => setForm({ ...form, stripe_publishable_key: e.target.value })}
            placeholder="pk_live_… or pk_test_…"
          />
          <Input
            label="Secret key"
            type="password"
            value={form.stripe_secret_key || ''}
            onChange={(e) => setForm({ ...form, stripe_secret_key: e.target.value })}
            placeholder="sk_live_… or sk_test_…"
            autoComplete="new-password"
          />
          <Input
            label="Webhook signing secret (optional)"
            type="password"
            value={form.stripe_webhook_secret || ''}
            onChange={(e) => setForm({ ...form, stripe_webhook_secret: e.target.value })}
            placeholder="whsec_…"
            autoComplete="new-password"
          />
          {form.stripe_webhook_url && (
            <p className="break-all text-xs text-brand-500">
              Webhook endpoint: <code>{form.stripe_webhook_url}</code>
              <br />
              Events: <code>checkout.session.completed</code>
            </p>
          )}
        </Card>

        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Manual payment</h3>
          <p className="text-sm text-brand-600">
            Let guests book online and pay later by bank transfer or another manual method. Show your payment
            instructions after they confirm. Mark the booking paid in Bookings when funds arrive.
          </p>
          <label className="flex items-center gap-2 text-sm font-medium text-brand-800">
            <input
              type="checkbox"
              className="rounded border-sand-300 text-brand-700 focus:ring-brand-500"
              checked={!!form.manual_payment_enabled}
              onChange={(e) => setForm({ ...form, manual_payment_enabled: e.target.checked })}
            />
            Enable manual payment on site checkout
          </label>
          <Input
            label="Title on checkout"
            value={form.manual_payment_title || ''}
            onChange={(e) => setForm({ ...form, manual_payment_title: e.target.value })}
            placeholder="Bank transfer / Manual payment"
          />
          <Textarea
            label="Payment instructions"
            rows={6}
            value={form.manual_payment_instructions || ''}
            onChange={(e) => setForm({ ...form, manual_payment_instructions: e.target.value })}
            placeholder={'Bank name: …\nAccount name: …\nAccount number: …\nReference: use your booking code'}
          />
        </Card>

        <Card className="space-y-3 p-5 lg:col-span-2">
          <Button onClick={savePayments}>Save payment settings</Button>
          {saved && <p className="text-sm text-emerald-700">Payment settings saved.</p>}
        </Card>

        <Card className="space-y-3 p-5 lg:col-span-2">
          <h3 className="font-display text-lg font-semibold">Offline payment methods</h3>
          <p className="text-sm text-brand-600">
            Labels used when staff mark a booking paid (cash, card terminal, bank transfer, and so on).
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Name"
              value={newType.name}
              onChange={(e) => setNewType({ ...newType, name: e.target.value })}
              placeholder="e.g. Cash"
            />
            <Input
              label="Description"
              value={newType.description}
              onChange={(e) => setNewType({ ...newType, description: e.target.value })}
            />
          </div>
          <Button
            onClick={async () => {
              if (!newType.name.trim()) return;
              setError('');
              try {
                await api('/payment-types', { method: 'POST', body: newType });
                setNewType({ name: '', description: '' });
                setPaymentTypes(await api('/payment-types'));
              } catch (err) {
                setError(err.message);
              }
            }}
          >
            Add payment method
          </Button>
          <ul className="space-y-2 text-sm">
            {paymentTypes.map((pt) => (
              <li key={pt.id} className="flex items-center justify-between gap-2 rounded-lg bg-sand-50 px-3 py-2">
                <span>
                  <strong>{pt.name}</strong>
                  {pt.description ? ` — ${pt.description}` : ''}
                </span>
                <Button
                  variant="ghost"
                  className="!px-2 !py-1 text-xs"
                  onClick={async () => {
                    if (!window.confirm(`Move payment method “${pt.name}” to trash?`)) return;
                    setError('');
                    try {
                      await api(`/payment-types/${pt.id}`, { method: 'DELETE' });
                      setPaymentTypes(await api('/payment-types'));
                    } catch (err) {
                      setError(err.message);
                    }
                  }}
                >
                  Trash
                </Button>
              </li>
            ))}
            {!paymentTypes.length && (
              <li className="text-sm text-brand-500">No offline payment methods yet.</li>
            )}
          </ul>
        </Card>
      </div>
    </div>
  );
}
