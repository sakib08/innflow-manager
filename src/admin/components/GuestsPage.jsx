import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';
import { cfg } from './config';

export default function GuestsPage() {
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api(`/guests?search=${encodeURIComponent(search)}`)
      .then(setRows)
      .finally(() => setLoading(false));
  }, [search]);

  const trashGuest = async (id, name) => {
    if (!window.confirm(`Move guest “${name}” to trash?`)) return;
    await api(`/guests/${id}`, { method: 'DELETE' });
    setRows((prev) => prev.filter((r) => r.id !== id));
  };

  return (
    <div>
      <PageHeader
        title="Guests"
        subtitle="Guest information registry"
        actions={<Input placeholder="Search guests…" value={search} onChange={(e) => setSearch(e.target.value)} className="w-64" />}
      />
      {loading ? (
        <Loading />
      ) : (
        <Table
          columns={[
            { key: 'name', label: 'Name', render: (r) => `${r.first_name} ${r.last_name}` },
            { key: 'email', label: 'Email' },
            { key: 'phone', label: 'Phone' },
            { key: 'city', label: 'City' },
            { key: 'country', label: 'Country' },
            { key: 'id_number', label: 'ID' },
            {
              key: 'actions',
              label: '',
              render: (r) => (
                <Button
                  variant="ghost"
                  className="!px-2 !py-1 text-xs"
                  onClick={() => trashGuest(r.id, `${r.first_name} ${r.last_name}`)}
                >
                  Trash
                </Button>
              ),
            },
          ]}
          rows={rows}
        />
      )}
    </div>
  );
}
