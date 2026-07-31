import { useState, useEffect, useRef, useMemo } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import {
  Badge,
  Button,
  Card,
  Empty,
  GalleryPicker,
  ImageSlider,
  Input,
  Loading,
  MediaPicker,
  Modal,
  PageHeader,
  Pagination,
  Textarea,
} from '../../shared/ui';
import { cfg } from './config';

const PAGE_SIZE = 10;

function emptyRoomForm() {
  return {
    id: null,
    name: '',
    description: '',
    base_price: '',
    max_adults: 2,
    max_children: 1,
    total_rooms: 1,
    image_url: '',
    gallery_urls: [],
    amenity_ids: [],
    status: 'active',
  };
}

export default function RoomsPage() {
  const [rooms, setRooms] = useState([]);
  const [amenities, setAmenities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState(emptyRoomForm());
  const [saving, setSaving] = useState(false);
  const [page, setPage] = useState(1);
  const [galleryFor, setGalleryFor] = useState(null);
  const [exportInfo, setExportInfo] = useState(null);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState(null);
  const [importError, setImportError] = useState('');
  const [copied, setCopied] = useState(false);
  const fileInputRef = useRef(null);
  const settings = cfg.settings || {};
  const editing = !!form.id;

  const load = () => {
    setLoading(true);
    Promise.all([api('/rooms'), api('/amenities')])
      .then(([r, a]) => {
        setRooms(r);
        setAmenities(a);
      })
      .finally(() => setLoading(false));
  };

  useEffect(load, []);
  useEffect(() => {
    api('/rooms/export-token').then(setExportInfo).catch(() => {});
  }, []);

  useEffect(() => {
    setPage(1);
  }, [rooms.length]);

  const pagedRooms = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return rooms.slice(start, start + PAGE_SIZE);
  }, [rooms, page]);

  const exportUrl = (format) => buildApiUrl('/rooms/export', { format });

  const regenerateToken = async () => {
    const info = await api('/rooms/export-token', { method: 'POST' });
    setExportInfo(info);
  };

  const copySheetsFormula = async () => {
    if (!exportInfo) return;
    const formula = `=IMPORTDATA("${exportInfo.export_url}")`;
    try {
      await navigator.clipboard.writeText(formula);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // eslint-disable-next-line no-alert
      window.prompt('Copy this formula into a Google Sheets cell:', formula);
    }
  };

  const handleImportFile = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImporting(true);
    setImportError('');
    setImportResult(null);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const result = await apiUpload('/rooms/import', formData);
      setImportResult(result);
      load();
    } catch (err) {
      setImportError(err.message);
    } finally {
      setImporting(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const startEdit = (room) => {
    setForm({
      id: room.id,
      name: room.name || '',
      description: room.description || '',
      base_price: room.base_price ?? '',
      max_adults: room.max_adults ?? 2,
      max_children: room.max_children ?? 0,
      total_rooms: room.total_rooms ?? 1,
      image_url: room.image_url || '',
      gallery_urls: (room.gallery || []).map((g) => g.image_url),
      amenity_ids: (room.amenities || []).map((a) => Number(a.id)),
      status: room.status || 'active',
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const cancelEdit = () => setForm(emptyRoomForm());

  const save = async () => {
    if (!form.name) return;
    setSaving(true);
    try {
      const body = {
        name: form.name,
        description: form.description,
        base_price: form.base_price,
        max_adults: form.max_adults,
        max_children: form.max_children,
        total_rooms: form.total_rooms,
        image_url: form.image_url,
        gallery_urls: form.gallery_urls,
        amenity_ids: form.amenity_ids,
        status: form.status || 'active',
      };
      if (editing) {
        await api(`/rooms/${form.id}`, { method: 'POST', body });
      } else {
        await api('/rooms', { method: 'POST', body });
      }
      setForm(emptyRoomForm());
      load();
    } finally {
      setSaving(false);
    }
  };

  const patchRoom = async (room, changes) => {
    setRooms((prev) => prev.map((r) => (r.id === room.id ? { ...r, ...changes } : r)));
    return api(`/rooms/${room.id}`, {
      method: 'POST',
      body: {
        name: room.name,
        description: room.description,
        base_price: room.base_price,
        max_adults: room.max_adults,
        max_children: room.max_children,
        total_rooms: room.total_rooms,
        image_url: room.image_url,
        status: room.status,
        amenity_ids: (room.amenities || []).map((a) => Number(a.id)),
        gallery_urls: (room.gallery || []).map((g) => g.image_url),
        ...changes,
      },
    });
  };

  const updateImage = (room, url) => patchRoom(room, { image_url: url });

  const updateGallery = (room, urls) =>
    patchRoom(room, { gallery_urls: urls, gallery: urls.map((u) => ({ image_url: u })) });

  const openMediaFor = (room) => {
    if (typeof window === 'undefined' || !window.wp || !window.wp.media) {
      // eslint-disable-next-line no-alert
      window.alert('WordPress media library is not available on this page.');
      return;
    }
    const frame = window.wp.media({
      title: 'Select or upload room image',
      button: { text: 'Use this image' },
      library: { type: 'image' },
      multiple: false,
    });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url;
      updateImage(room, url);
    });
    frame.open();
  };

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader title="Room types" subtitle="Manage inventory, pricing and amenities" />

      <Card className="mb-6 p-5">
        <h3 className="font-display text-lg font-semibold">Import & export</h3>
        <p className="mt-1 text-sm text-brand-600">
          Manage room type data in bulk via CSV, Excel, or Google Sheets. Exported files include name, pricing,
          capacity, image, gallery photos and amenities.
        </p>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <div className="rounded-lg border border-sand-200 p-4">
            <p className="mb-2 text-sm font-semibold text-brand-800">Export</p>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => window.location.assign(exportUrl('csv'))}>
                Export CSV
              </Button>
              <Button variant="secondary" onClick={() => window.location.assign(exportUrl('xlsx'))}>
                Export Excel (.xlsx)
              </Button>
            </div>
            <p className="mt-2 text-xs text-brand-500">CSV opens directly in Excel, Numbers or Google Sheets.</p>
          </div>

          <div className="rounded-lg border border-sand-200 p-4">
            <p className="mb-2 text-sm font-semibold text-brand-800">Import from CSV</p>
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,text/csv"
              onChange={handleImportFile}
              className="block w-full text-xs text-brand-700 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-800"
              disabled={importing}
            />
            <p className="mt-2 text-xs text-brand-500">
              Include an <code>id</code> or matching <code>slug</code> column to update existing room types; rows without a
              match are created new.
            </p>
            {importing && <p className="mt-2 text-xs text-brand-600">Importing…</p>}
            {importError && <p className="mt-2 text-xs text-red-600">{importError}</p>}
            {importResult && (
              <p className="mt-2 text-xs text-emerald-700">
                Imported: {importResult.created} created, {importResult.updated} updated
                {importResult.errors?.length ? `, ${importResult.errors.length} skipped` : ''}.
              </p>
            )}
          </div>

          <div className="rounded-lg border border-sand-200 p-4">
            <p className="mb-2 text-sm font-semibold text-brand-800">Google Sheets</p>
            <p className="text-xs text-brand-600">Paste this formula into any cell of a Google Sheet to pull live data:</p>
            <div className="mt-2 flex gap-2">
              <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={copySheetsFormula} disabled={!exportInfo}>
                {copied ? 'Copied!' : 'Copy formula'}
              </Button>
              <Button variant="ghost" className="!px-2 !py-1 text-xs" onClick={regenerateToken}>
                Regenerate link
              </Button>
            </div>
            <p className="mt-2 break-all text-[10px] text-brand-400">{exportInfo?.export_url}</p>
          </div>
        </div>
      </Card>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="space-y-3 p-5 lg:col-span-1">
          <div className="flex items-center justify-between gap-2">
            <h3 className="font-display text-lg font-semibold">{editing ? 'Edit room type' : 'Add room type'}</h3>
            {editing && (
              <Button variant="ghost" className="!px-2 !py-1 text-xs" onClick={cancelEdit}>
                Cancel
              </Button>
            )}
          </div>
          {editing && (
            <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
              Editing room type #{form.id}. Changes will update the existing record.
            </p>
          )}
          <Input label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Textarea label="Description" rows={3} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          <Input label="Base price / night" type="number" value={form.base_price} onChange={(e) => setForm({ ...form, base_price: e.target.value })} />
          <div className="grid grid-cols-3 gap-2">
            <Input label="Adults" type="number" value={form.max_adults} onChange={(e) => setForm({ ...form, max_adults: e.target.value })} />
            <Input label="Children" type="number" value={form.max_children} onChange={(e) => setForm({ ...form, max_children: e.target.value })} />
            <Input label="Rooms" type="number" value={form.total_rooms} onChange={(e) => setForm({ ...form, total_rooms: e.target.value })} />
          </div>
          <MediaPicker
            label="List image"
            value={form.image_url}
            onChange={(url) => setForm({ ...form, image_url: url })}
          />
          <GalleryPicker
            label="Gallery photos (shown as a slider on room details)"
            value={form.gallery_urls}
            onChange={(urls) => setForm({ ...form, gallery_urls: urls })}
          />
          <div>
            <p className="mb-1 text-sm font-medium text-brand-800">Amenities</p>
            <div className="flex flex-wrap gap-2">
              {amenities.map((a) => {
                const on = form.amenity_ids.includes(Number(a.id));
                return (
                  <button
                    key={a.id}
                    type="button"
                    onClick={() =>
                      setForm({
                        ...form,
                        amenity_ids: on
                          ? form.amenity_ids.filter((id) => id !== Number(a.id))
                          : [...form.amenity_ids, Number(a.id)],
                      })
                    }
                    className={`rounded-full px-3 py-1 text-xs font-semibold ${on ? 'bg-brand-700 text-white' : 'bg-sand-100 text-brand-800'}`}
                  >
                    {a.name}
                  </button>
                );
              })}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button onClick={save} disabled={saving || !form.name}>
              {saving ? 'Saving…' : editing ? 'Update room type' : 'Save room type'}
            </Button>
            {editing && (
              <Button variant="secondary" onClick={cancelEdit}>
                Cancel edit
              </Button>
            )}
          </div>
        </Card>

        <div className="space-y-4 lg:col-span-2">
          {pagedRooms.map((room) => (
            <Card key={room.id} className="flex flex-col gap-4 p-5 sm:flex-row">
              <div className="flex w-full shrink-0 flex-col gap-2 sm:w-40">
                <div className="h-28 w-full overflow-hidden rounded-lg bg-brand-100">
                  {room.image_url ? (
                    <img src={room.image_url} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex h-full items-center justify-center text-brand-500">No image</div>
                  )}
                </div>
                <div className="flex gap-1">
                  <Button variant="secondary" className="!px-2 !py-1 flex-1 text-xs" onClick={() => openMediaFor(room)}>
                    {room.image_url ? 'Change' : 'Add image'}
                  </Button>
                  {room.image_url && (
                    <Button variant="ghost" className="!px-2 !py-1 text-xs" onClick={() => updateImage(room, '')}>
                      Remove
                    </Button>
                  )}
                </div>
              </div>
              <div className="flex-1">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h3 className="font-display text-xl font-semibold">{room.name}</h3>
                    <p className="text-sm text-brand-600">{room.description}</p>
                  </div>
                  <div className="flex flex-col items-end gap-2">
                    <p className="font-semibold text-brand-800">{money(room.base_price, settings)}/night</p>
                    <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => startEdit(room)}>
                      Edit
                    </Button>
                    <Button
                      variant="ghost"
                      className="!px-2 !py-1 text-xs"
                      onClick={async () => {
                        if (!window.confirm(`Move “${room.name}” to trash?`)) return;
                        await api(`/rooms/${room.id}`, { method: 'DELETE' });
                        load();
                      }}
                    >
                      Trash
                    </Button>
                  </div>
                </div>
                <p className="mt-2 text-sm text-brand-600">
                  {room.total_rooms} rooms · up to {room.max_adults} adults · {room.max_children} children
                </p>
                <div className="mt-2 flex flex-wrap gap-1">
                  {(room.amenities || []).map((a) => (
                    <Badge key={a.id}>{a.name}</Badge>
                  ))}
                </div>
                <div className="mt-3 flex items-center gap-3">
                  <div className="flex -space-x-2">
                    {(room.gallery || []).slice(0, 4).map((g, i) => (
                      <img
                        key={g.id || i}
                        src={g.image_url}
                        alt=""
                        className="h-9 w-9 rounded-full border-2 border-white object-cover shadow"
                      />
                    ))}
                  </div>
                  <span className="text-xs text-brand-500">
                    {(room.gallery || []).length} gallery photo{(room.gallery || []).length === 1 ? '' : 's'}
                  </span>
                  <Button variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => setGalleryFor(room)}>
                    Manage gallery
                  </Button>
                </div>
              </div>
            </Card>
          ))}
          {!rooms.length && <Empty title="No room types yet" description="Create your first room type to start taking bookings." />}
          <Pagination page={page} pageSize={PAGE_SIZE} total={rooms.length} onChange={setPage} />
        </div>
      </div>

      <Modal open={!!galleryFor} onClose={() => setGalleryFor(null)} className="max-w-xl">
        {galleryFor && (
          <div className="p-6">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h3 className="font-display text-xl font-semibold">Gallery · {galleryFor.name}</h3>
                <p className="text-sm text-brand-600">These photos appear as a slider on the room details view.</p>
              </div>
              <Button variant="ghost" onClick={() => setGalleryFor(null)}>Close</Button>
            </div>
            <GalleryPicker
              value={(galleryFor.gallery || []).map((g) => g.image_url)}
              onChange={(urls) => {
                const nextGallery = urls.map((u) => ({ image_url: u }));
                setGalleryFor({ ...galleryFor, gallery: nextGallery });
                updateGallery(galleryFor, urls);
              }}
            />
            {(galleryFor.gallery || []).length > 0 && (
              <div className="mt-6">
                <p className="mb-2 text-sm font-medium text-brand-800">Slider preview</p>
                <ImageSlider images={(galleryFor.gallery || []).map((g) => g.image_url)} className="rounded-xl" aspect="aspect-video" />
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
