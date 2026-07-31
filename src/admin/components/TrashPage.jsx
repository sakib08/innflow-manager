import { useEffect, useMemo, useState } from 'react';
import { api } from '../../shared/api';
import { Badge, Button, Card, Empty, Loading, PageHeader, Select, Table } from '../../shared/ui';

export default function TrashPage() {
  const [data, setData] = useState({ items: [], counts: { total: 0, by_type: {} }, types: {} });
  const [type, setType] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = (selected = type) => {
    setLoading(true);
    setError('');
    const qs = selected ? `?type=${encodeURIComponent(selected)}` : '';
    api(`/trash${qs}`)
      .then(setData)
      .catch((e) => setError(e.message || 'Failed to load trash'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load('');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const typeOptions = useMemo(() => {
    const entries = Object.entries(data.types || {}).map(([id, meta]) => ({
      id,
      label: `${meta.label} (${data.counts?.by_type?.[id]?.count || 0})`,
    }));
    return [{ id: '', label: `All types (${data.counts?.total || 0})` }, ...entries];
  }, [data]);

  const restore = async (item) => {
    setBusy(true);
    try {
      await api(`/trash/${item.type}/${item.id}/restore`, { method: 'POST', body: {} });
      load(type);
    } catch (e) {
      setError(e.message || 'Restore failed');
    } finally {
      setBusy(false);
    }
  };

  const purge = async (item) => {
    if (!window.confirm(`Permanently delete “${item.title}”? This cannot be undone.`)) return;
    setBusy(true);
    try {
      await api(`/trash/${item.type}/${item.id}`, { method: 'DELETE' });
      load(type);
    } catch (e) {
      setError(e.message || 'Delete failed');
    } finally {
      setBusy(false);
    }
  };

  const emptyTrash = async () => {
    const label = type ? typeOptions.find((t) => t.id === type)?.label || type : 'all trash';
    if (!window.confirm(`Empty ${label}? Permanently deletes every matching item.`)) return;
    setBusy(true);
    try {
      await api(`/trash${type ? `?type=${encodeURIComponent(type)}` : ''}`, { method: 'DELETE' });
      load(type);
    } catch (e) {
      setError(e.message || 'Empty trash failed');
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader
        title="Trash"
        subtitle="Soft-deleted records. Restore them, or permanently delete from here only."
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              value={type}
              onChange={(e) => {
                const next = e.target.value;
                setType(next);
                load(next);
              }}
              className="min-w-[12rem]"
            >
              {typeOptions.map((opt) => (
                <option key={opt.id || 'all'} value={opt.id}>
                  {opt.label}
                </option>
              ))}
            </Select>
            <Button variant="secondary" onClick={() => load(type)} disabled={busy}>
              Refresh
            </Button>
            <Button variant="ghost" onClick={emptyTrash} disabled={busy || !(data.items || []).length}>
              Empty trash
            </Button>
          </div>
        }
      />

      {error && <p className="mb-4 text-sm text-red-600">{error}</p>}

      <Card className="mb-4 p-4">
        <div className="flex flex-wrap gap-2">
          {Object.entries(data.counts?.by_type || {})
            .filter(([, meta]) => meta.count > 0)
            .map(([id, meta]) => (
              <button
                key={id}
                type="button"
                onClick={() => {
                  setType(id);
                  load(id);
                }}
                className="rounded-lg bg-sand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 hover:bg-brand-50"
              >
                {meta.label} <Badge tone="neutral">{meta.count}</Badge>
              </button>
            ))}
          {!data.counts?.total && <p className="text-sm text-brand-500">Trash is empty.</p>}
        </div>
      </Card>

      {(data.items || []).length ? (
        <Table
          columns={[
            { key: 'label', label: 'Type' },
            { key: 'title', label: 'Item' },
            { key: 'id', label: 'ID' },
            { key: 'deleted_at', label: 'Trashed at' },
            {
              key: 'actions',
              label: 'Actions',
              render: (r) => (
                <div className="flex flex-wrap gap-2">
                  <Button variant="secondary" className="!px-2 !py-1 text-xs" disabled={busy} onClick={() => restore(r)}>
                    Restore
                  </Button>
                  <Button variant="ghost" className="!px-2 !py-1 text-xs text-red-700" disabled={busy} onClick={() => purge(r)}>
                    Delete forever
                  </Button>
                </div>
              ),
            },
          ]}
          rows={data.items}
        />
      ) : (
        <Empty title="Trash is empty" description="Deleted rooms, bookings, guests, bills, and other records appear here." />
      )}
    </div>
  );
}
