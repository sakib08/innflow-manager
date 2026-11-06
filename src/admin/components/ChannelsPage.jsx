import { useEffect, useMemo, useState } from 'react';
import { api } from '../../shared/api';
import { Badge, Button, Card, Input, Loading, PageHeader, Select, Table } from '../../shared/ui';
import { cfg } from './config';

export default function ChannelsPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');
  const [connection, setConnection] = useState(null);
  const [rooms, setRooms] = useState([]);
  const [maps, setMaps] = useState([]);
  const [logs, setLogs] = useState([]);
  const [properties, setProperties] = useState([]);
  const [channexRooms, setChannexRooms] = useState([]);
  const [ratePlans, setRatePlans] = useState([]);
  const [form, setForm] = useState({
    api_key: '',
    property_id: '',
    environment: 'staging',
    is_active: false,
  });

  const mapByRoom = useMemo(() => {
    const out = {};
    maps.forEach((m) => {
      out[String(m.room_type_id)] = m;
    });
    return out;
  }, [maps]);

  const [draftMaps, setDraftMaps] = useState({});

  const load = async () => {
    setLoading(true);
    setErr('');
    try {
      const data = await api('/channels');
      setConnection(data.connection);
      setRooms(data.rooms || []);
      setMaps(data.maps || []);
      setLogs(data.logs || []);
      const nextDraft = {};
      (data.rooms || []).forEach((r) => {
        const existing = (data.maps || []).find((m) => String(m.room_type_id) === String(r.id));
        nextDraft[r.id] = {
          room_type_id: r.id,
          external_room_type_id: existing?.external_room_type_id || '',
          external_rate_plan_id: existing?.external_rate_plan_id || '',
        };
      });
      setDraftMaps(nextDraft);
      setForm({
        api_key: '',
        property_id: data.connection?.property_id || '',
        environment: data.connection?.environment || 'staging',
        is_active: !!Number(data.connection?.is_active),
      });
    } catch (e) {
      setErr(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const saveConnection = async () => {
    setSaving(true);
    setMsg('');
    setErr('');
    try {
      const body = {
        property_id: form.property_id,
        environment: form.environment,
        is_active: form.is_active,
      };
      if (form.api_key.trim()) body.api_key = form.api_key.trim();
      const res = await api('/channels', { method: 'POST', body });
      setConnection(res.connection);
      setMsg(res.message || 'Saved');
      setForm((f) => ({ ...f, api_key: '' }));
    } catch (e) {
      setErr(e.message);
    } finally {
      setSaving(false);
    }
  };

  const loadChannexMeta = async () => {
    setErr('');
    setMsg('');
    try {
      const props = await api('/channels/channex/properties');
      setProperties(props || []);
      if (form.property_id) {
        const [rt, rp] = await Promise.all([
          api(`/channels/channex/room-types?property_id=${encodeURIComponent(form.property_id)}`),
          api(`/channels/channex/rate-plans?property_id=${encodeURIComponent(form.property_id)}`),
        ]);
        setChannexRooms(rt || []);
        setRatePlans(rp || []);
      }
      setMsg('Loaded Channex properties / rooms / rates');
    } catch (e) {
      setErr(e.message);
    }
  };

  const mappedCount = maps.filter((m) => m.external_room_type_id).length;

  const saveMaps = async () => {
    setSaving(true);
    setErr('');
    setMsg('');
    try {
      const payload = Object.values(draftMaps).filter((m) => String(m.external_room_type_id || '').trim());
      if (!payload.length) {
        setErr('Select or paste at least one Channex room type UUID, then click Save maps.');
        setSaving(false);
        return;
      }
      const res = await api('/channels/maps', { method: 'POST', body: { maps: payload } });
      setMaps(res.maps || []);
      setMsg(`Room mapping saved (${(res.maps || []).length} room(s)). You can sync now.`);
    } catch (e) {
      setErr(e.message);
    } finally {
      setSaving(false);
    }
  };

  const runSync = async (kind) => {
    if (!mappedCount) {
      setErr('Map at least one room type below and click Save maps before syncing.');
      return;
    }
    setSaving(true);
    setErr('');
    setMsg('');
    try {
      const res = await api(`/channels/sync/${kind}`, { method: 'POST', body: {} });
      setMsg(res.message || 'Sync complete');
      const data = await api('/channels');
      setLogs(data.logs || []);
      setConnection(data.connection);
      setMaps(data.maps || []);
    } catch (e) {
      setErr(e.message);
    } finally {
      setSaving(false);
    }
  };

  const webhookUrl = connection?.webhook_url
    ? `${connection.webhook_url}?secret=${encodeURIComponent(connection.webhook_secret || '')}`
    : '';

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader
        title="Channels"
        subtitle="Sync inventory and bookings with Channex (Booking.com, Agoda, and more)"
        actions={
          <Button
            variant="secondary"
            onClick={() => {
              window.location.href = `${cfg.adminUrl || 'admin.php'}?page=staynexus-hotel-manager-channel-help`;
            }}
          >
            Channex help
          </Button>
        }
      />

      {msg && <p className="mb-3 text-sm text-emerald-700">{msg}</p>}
      {err && <p className="mb-3 text-sm text-red-600">{err}</p>}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Channex connection</h3>
          <p className="text-sm text-brand-600">
            Create an API key in Channex, connect Booking.com / Agoda there, then map rooms below.
          </p>
          <Select
            label="Environment"
            value={form.environment}
            onChange={(e) => setForm({ ...form, environment: e.target.value })}
          >
            <option value="staging">Staging (staging.channex.io)</option>
            <option value="live">Live (app.channex.io)</option>
          </Select>
          <Input
            label={connection?.api_key_set ? `API key (saved: ${connection.api_key_masked})` : 'API key'}
            type="password"
            placeholder={connection?.api_key_set ? 'Leave blank to keep current key' : 'Paste Channex API key'}
            value={form.api_key}
            onChange={(e) => setForm({ ...form, api_key: e.target.value })}
          />
          <Input
            label="Property ID"
            value={form.property_id}
            onChange={(e) => setForm({ ...form, property_id: e.target.value })}
            placeholder="Channex property UUID"
          />
          <label className="flex items-center gap-2 text-sm font-medium text-brand-800">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            />
            Sync enabled
          </label>
          <div className="flex flex-wrap gap-2">
            <Button onClick={saveConnection} disabled={saving}>
              Save connection
            </Button>
            <Button variant="secondary" onClick={loadChannexMeta} disabled={saving || !connection?.api_key_set}>
              Load from Channex
            </Button>
          </div>
          {connection?.last_error && (
            <p className="text-sm text-red-600">Last error: {connection.last_error}</p>
          )}
          <div className="grid grid-cols-2 gap-2 text-xs text-brand-600">
            <div>Last ARI sync: {connection?.last_ari_sync_at || '—'}</div>
            <div>Last booking sync: {connection?.last_booking_sync_at || '—'}</div>
          </div>
        </Card>

        <Card className="space-y-3 p-5">
          <h3 className="font-display text-lg font-semibold">Sync actions</h3>
          <p className="text-sm text-brand-600">
            First map rooms below and click <strong>Save maps</strong>, then push/pull here.
          </p>
          {!mappedCount && (
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
              No rooms mapped yet ({mappedCount} saved). Complete <strong>Room mapping</strong> first.
            </p>
          )}
          {mappedCount > 0 && (
            <p className="text-sm text-emerald-700">{mappedCount} room type(s) mapped and ready.</p>
          )}
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => runSync('ari')} disabled={saving || !form.is_active || !mappedCount}>
              Push availability & rates
            </Button>
            <Button variant="secondary" onClick={() => runSync('bookings')} disabled={saving || !form.is_active || !mappedCount}>
              Pull bookings now
            </Button>
          </div>
          {webhookUrl && (
            <div className="rounded-lg bg-sand-50 p-3 text-xs">
              <p className="mb-1 font-semibold text-brand-800">Webhook URL (paste into Channex)</p>
              <code className="break-all text-brand-700">{webhookUrl}</code>
            </div>
          )}
          {properties.length > 0 && (
            <Select
              label="Pick property from Channex"
              value={form.property_id}
              onChange={(e) => setForm({ ...form, property_id: e.target.value })}
            >
              <option value="">Select…</option>
              {properties.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name || p.title || p.id}
                </option>
              ))}
            </Select>
          )}
        </Card>
      </div>

      <Card className="mt-6 space-y-3 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h3 className="font-display text-lg font-semibold">Room mapping</h3>
            <p className="text-xs text-brand-500">{mappedCount} saved map(s)</p>
          </div>
          <Button onClick={saveMaps} disabled={saving || !rooms.length}>
            Save maps
          </Button>
        </div>
        <ol className="list-decimal space-y-1 pl-5 text-sm text-brand-600">
          <li>Click <strong>Load from Channex</strong> (or paste UUIDs from Channex room / rate pages).</li>
          <li>For each local room, choose a Channex room type (and rate plan if you want price push).</li>
          <li>Click <strong>Save maps</strong> — required before Push / Pull.</li>
        </ol>
        <div className="space-y-3">
          {rooms.map((room) => {
            const draft = draftMaps[room.id] || {
              room_type_id: room.id,
              external_room_type_id: mapByRoom[String(room.id)]?.external_room_type_id || '',
              external_rate_plan_id: mapByRoom[String(room.id)]?.external_rate_plan_id || '',
            };
            const updateDraft = (patch) =>
              setDraftMaps({
                ...draftMaps,
                [room.id]: { ...draft, ...patch },
              });
            return (
              <div key={room.id} className="space-y-2 rounded-lg border border-sand-200 p-3">
                <div>
                  <p className="text-sm font-semibold text-brand-900">{room.name}</p>
                  <p className="text-xs text-brand-500">
                    Local ID {room.id} · {room.total_rooms} rooms · base {room.base_price}
                  </p>
                </div>
                <div className="grid gap-2 md:grid-cols-2">
                  {channexRooms.length > 0 ? (
                    <Select
                      label="Channex room type"
                      value={draft.external_room_type_id}
                      onChange={(e) => updateDraft({ external_room_type_id: e.target.value })}
                    >
                      <option value="">Not mapped</option>
                      {channexRooms.map((r) => (
                        <option key={r.id} value={r.id}>
                          {r.name || r.title || r.id}
                        </option>
                      ))}
                    </Select>
                  ) : (
                    <Input
                      label="Channex room type UUID"
                      placeholder="Paste room type UUID from Channex"
                      value={draft.external_room_type_id}
                      onChange={(e) => updateDraft({ external_room_type_id: e.target.value.trim() })}
                    />
                  )}
                  {ratePlans.length > 0 ? (
                    <Select
                      label="Channex rate plan"
                      value={draft.external_rate_plan_id}
                      onChange={(e) => updateDraft({ external_rate_plan_id: e.target.value })}
                    >
                      <option value="">No rate push</option>
                      {ratePlans.map((rp) => (
                        <option key={rp.id} value={rp.id}>
                          {rp.name || rp.title || rp.id}
                        </option>
                      ))}
                    </Select>
                  ) : (
                    <Input
                      label="Channex rate plan UUID (optional)"
                      placeholder="Paste rate plan UUID"
                      value={draft.external_rate_plan_id}
                      onChange={(e) => updateDraft({ external_rate_plan_id: e.target.value.trim() })}
                    />
                  )}
                </div>
                {channexRooms.length > 0 && (
                  <Input
                    label="Or paste room type UUID"
                    placeholder="Overrides dropdown if filled"
                    value={draft.external_room_type_id}
                    onChange={(e) => updateDraft({ external_room_type_id: e.target.value.trim() })}
                  />
                )}
              </div>
            );
          })}
          {!rooms.length && (
            <p className="text-sm text-brand-500">Create room types under Rooms first, then return here to map them.</p>
          )}
        </div>
      </Card>

      <Card className="mt-6 p-5">
        <h3 className="mb-3 font-display text-lg font-semibold">Recent sync log</h3>
        <Table
          columns={[
            { key: 'created_at', label: 'When' },
            { key: 'direction', label: 'Dir' },
            { key: 'event_type', label: 'Event' },
            {
              key: 'status',
              label: 'Status',
              render: (r) => <Badge tone={r.status === 'ok' ? 'success' : 'danger'}>{r.status}</Badge>,
            },
            { key: 'message', label: 'Message' },
          ]}
          rows={logs}
        />
      </Card>
    </div>
  );
}
