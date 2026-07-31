import { useState, useEffect } from 'react';
import { api, apiUpload, buildApiUrl, money } from '../../shared/api';
import { Badge, Button, Card, Empty, GalleryPicker, ImageSlider, Input, Loading, MediaPicker, Modal, PageHeader, Select, Stat, Table, Textarea } from '../../shared/ui';

export default function RestaurantsPage() {
  const [restaurants, setRestaurants] = useState([]);
  const [amenities, setAmenities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [restForm, setRestForm] = useState({ name: '', location: '', phone: '', opening_hours: '' });
  const [amenityForm, setAmenityForm] = useState({ name: '', icon: '', description: '' });

  const load = () => {
    setLoading(true);
    Promise.all([api('/restaurants'), api('/amenities')])
      .then(([rest, am]) => {
        setRestaurants(rest);
        setAmenities(am);
      })
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const saveRestaurant = async () => {
    await api('/restaurants', { method: 'POST', body: restForm });
    setRestForm({ name: '', location: '', phone: '', opening_hours: '' });
    load();
  };

  const deleteRestaurant = async (id) => {
    if (!window.confirm('Move this restaurant to trash?')) return;
    await api(`/restaurants/${id}`, { method: 'DELETE' });
    load();
  };

  const saveAmenity = async () => {
    await api('/amenities', { method: 'POST', body: amenityForm });
    setAmenityForm({ name: '', icon: '', description: '' });
    load();
  };

  const deleteAmenity = async (id) => {
    if (!window.confirm('Move this amenity to trash?')) return;
    await api(`/amenities/${id}`, { method: 'DELETE' });
    load();
  };

  if (loading) return <Loading />;

  return (
    <div>
      <PageHeader title="Restaurants & amenities" subtitle="Manage on-site restaurant outlets and room amenities" />
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-4">
          <Card className="space-y-3 p-5">
            <h3 className="font-display text-lg font-semibold">Add restaurant</h3>
            <Input label="Name" value={restForm.name} onChange={(e) => setRestForm({ ...restForm, name: e.target.value })} />
            <Input label="Location" value={restForm.location} onChange={(e) => setRestForm({ ...restForm, location: e.target.value })} />
            <Input label="Phone" value={restForm.phone} onChange={(e) => setRestForm({ ...restForm, phone: e.target.value })} />
            <Input label="Opening hours" value={restForm.opening_hours} onChange={(e) => setRestForm({ ...restForm, opening_hours: e.target.value })} />
            <Button onClick={saveRestaurant}>Save restaurant</Button>
          </Card>

          <Card className="p-5">
            <h3 className="mb-3 font-display text-lg font-semibold">Restaurant outlets</h3>
            {restaurants.length ? (
              <ul className="space-y-2 text-sm">
                {restaurants.map((r) => (
                  <li key={r.id} className="flex items-center justify-between gap-2 rounded-lg bg-sand-50 px-3 py-2">
                    <div>
                      <p className="font-semibold text-brand-900">{r.name}</p>
                      <p className="text-xs text-brand-500">
                        {r.location || '—'} {r.phone ? `· ${r.phone}` : ''} {r.opening_hours ? `· ${r.opening_hours}` : ''}
                      </p>
                    </div>
                    <Button variant="ghost" className="!px-2 !py-1 text-xs" onClick={() => deleteRestaurant(r.id)}>
                      Trash
                    </Button>
                  </li>
                ))}
              </ul>
            ) : (
              <Empty title="No restaurants yet" description="Add your first restaurant outlet." />
            )}
          </Card>
        </div>

        <div className="space-y-4">
          <Card className="space-y-3 p-5">
            <h3 className="font-display text-lg font-semibold">Add amenity</h3>
            <Input label="Amenity name" value={amenityForm.name} onChange={(e) => setAmenityForm({ ...amenityForm, name: e.target.value })} />
            <Input label="Icon (optional)" value={amenityForm.icon} onChange={(e) => setAmenityForm({ ...amenityForm, icon: e.target.value })} />
            <Textarea label="Description" rows={2} value={amenityForm.description} onChange={(e) => setAmenityForm({ ...amenityForm, description: e.target.value })} />
            <Button onClick={saveAmenity}>Save amenity</Button>
          </Card>

          <Card className="p-5">
            <h3 className="mb-3 font-display text-lg font-semibold">Amenities</h3>
            {amenities.length ? (
              <div className="flex flex-wrap gap-2">
                {amenities.map((a) => (
                  <span key={a.id} className="inline-flex items-center gap-2 rounded-full bg-sand-100 px-3 py-1.5 text-xs font-semibold text-brand-800">
                    {a.name}
                    <button type="button" onClick={() => deleteAmenity(a.id)} className="text-brand-400 hover:text-red-600">
                      ×
                    </button>
                  </span>
                ))}
              </div>
            ) : (
              <Empty title="No amenities yet" description="Add amenities to attach to your room types." />
            )}
          </Card>
        </div>
      </div>
    </div>
  );
}
