import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api, money, todayISO, addDaysISO, getFrontendConfig } from '../shared/api';
import { Badge, Button, Card, Empty, ImageSlider, Input, Loading, Modal, Select } from '../shared/ui';

function defaultStay() {
  return {
    check_in: todayISO(),
    check_out: addDaysISO(2),
    adults: 2,
    children: 0,
    rooms: 1,
  };
}

function nightsBetween(checkIn, checkOut) {
  const a = new Date(checkIn);
  const b = new Date(checkOut);
  return Math.max(1, Math.round((b - a) / 86400000));
}

function RoomCard({
  room,
  settings,
  mode,
  nights,
  stay,
  onStayChange,
  onDetails,
  onBook,
  booking,
}) {
  const pricePerNight = room.price_per_night ?? room.base_price;
  const totalPrice = room.total_price ?? Number(room.base_price || 0) * nights * Number(stay?.rooms || 1);

  return (
    <Card className="overflow-hidden">
      <div className="flex flex-col gap-0 lg:flex-row">
        <button
          type="button"
          onClick={() => onDetails(room)}
          className="group relative block h-48 w-full shrink-0 lg:h-auto lg:w-64"
        >
          {room.image_url ? (
            <img src={room.image_url} alt={room.name} className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full min-h-[12rem] items-center justify-center bg-brand-100 text-brand-500">Room preview</div>
          )}
          {(room.gallery || []).length > 0 && (
            <span className="absolute bottom-2 right-2 rounded-full bg-black/60 px-2.5 py-1 text-xs font-semibold text-white opacity-90 transition group-hover:opacity-100">
              {room.gallery.length + (room.image_url ? 1 : 0)} photos · View
            </span>
          )}
        </button>

        <div className="flex flex-1 flex-col justify-between gap-4 p-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 flex-1">
              <button type="button" onClick={() => onDetails(room)} className="text-left">
                <h3 className="font-display text-2xl font-semibold text-brand-950 hover:text-brand-700">{room.name}</h3>
              </button>
              <p className="mt-1 max-w-xl text-sm text-brand-600">{room.description}</p>
              <div className="mt-3 flex flex-wrap gap-1">
                {(room.amenities || []).map((a) => (
                  <Badge key={a.id}>{a.name}</Badge>
                ))}
              </div>
              <p className="mt-3 text-sm text-brand-600">
                {room.available_rooms != null
                  ? `${room.available_rooms} available`
                  : `${room.total_rooms} rooms`}{' '}
                · up to {room.max_adults} adults
              </p>
            </div>

            <div className="shrink-0 text-left sm:text-right">
              <p className="text-sm text-brand-500">{money(pricePerNight, settings)} / night</p>
              {mode === 'results' && (
                <>
                  <p className="font-display text-3xl font-bold text-brand-800">{money(totalPrice, settings)}</p>
                  <p className="text-xs text-brand-500">
                    total for {nights} night{nights > 1 ? 's' : ''}
                  </p>
                </>
              )}
              {mode === 'browse' && (
                <p className="mt-1 text-xs text-brand-500">Choose your dates to book</p>
              )}
            </div>
          </div>

          {mode === 'browse' && stay && onStayChange && (
            <div className="grid gap-3 rounded-xl border border-sand-200 bg-sand-50/80 p-3 sm:grid-cols-2 lg:grid-cols-5">
              <Input
                label="Check-in"
                type="date"
                value={stay.check_in}
                onChange={(e) => onStayChange({ ...stay, check_in: e.target.value })}
              />
              <Input
                label="Check-out"
                type="date"
                value={stay.check_out}
                onChange={(e) => onStayChange({ ...stay, check_out: e.target.value })}
              />
              <Input
                label="Adults"
                type="number"
                min="1"
                value={stay.adults}
                onChange={(e) => onStayChange({ ...stay, adults: e.target.value })}
              />
              <Input
                label="Children"
                type="number"
                min="0"
                value={stay.children}
                onChange={(e) => onStayChange({ ...stay, children: e.target.value })}
              />
              <Input
                label="Rooms"
                type="number"
                min="1"
                value={stay.rooms}
                onChange={(e) => onStayChange({ ...stay, rooms: e.target.value })}
              />
            </div>
          )}

          <div className="flex flex-wrap justify-end gap-2">
            <Button variant="secondary" onClick={() => onDetails(room)}>
              View details
            </Button>
            <Button onClick={() => onBook(room)} disabled={booking === room.id}>
              {booking === room.id ? 'Checking…' : 'Book now'}
            </Button>
          </div>
        </div>
      </div>
    </Card>
  );
}

function SearchApp() {
  const cfg = getFrontendConfig();
  const settings = cfg.settings || {};
  const [query, setQuery] = useState(defaultStay);
  const [catalog, setCatalog] = useState([]);
  const [catalogLoading, setCatalogLoading] = useState(true);
  const [roomStays, setRoomStays] = useState({});
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [bookingRoomId, setBookingRoomId] = useState(null);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState(null);
  const [guest, setGuest] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    discount_code: '',
  });
  const [booking, setBooking] = useState(null);
  const [step, setStep] = useState('browse');
  const [detailsRoom, setDetailsRoom] = useState(null);

  const nights = useMemo(
    () => nightsBetween(query.check_in, query.check_out),
    [query.check_in, query.check_out]
  );

  useEffect(() => {
    let cancelled = false;
    setCatalogLoading(true);
    api('/rooms')
      .then((rows) => {
        if (cancelled) return;
        const list = Array.isArray(rows) ? rows.filter((r) => r.status !== 'inactive') : [];
        setCatalog(list);
        const stays = {};
        list.forEach((room) => {
          stays[room.id] = defaultStay();
        });
        setRoomStays(stays);
      })
      .catch((err) => {
        if (!cancelled) setError(err.message || 'Could not load rooms');
      })
      .finally(() => {
        if (!cancelled) setCatalogLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const getRoomStay = (roomId) => roomStays[roomId] || defaultStay();

  const setRoomStay = (roomId, stay) => {
    setRoomStays((prev) => ({ ...prev, [roomId]: stay }));
  };

  const search = async (e) => {
    e?.preventDefault();
    setLoading(true);
    setError('');
    setBooking(null);
    setSelected(null);
    try {
      const params = new URLSearchParams(query).toString();
      const data = await api(`/rooms/search?${params}`);
      setResults(data);
      setStep('results');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const bookFromBrowse = async (room) => {
    const stay = getRoomStay(room.id);
    if (!stay.check_in || !stay.check_out || new Date(stay.check_out) <= new Date(stay.check_in)) {
      setError('Please choose a valid check-in and check-out date for this room.');
      return;
    }

    setBookingRoomId(room.id);
    setError('');
    try {
      const params = new URLSearchParams({
        check_in: stay.check_in,
        check_out: stay.check_out,
        adults: stay.adults,
        children: stay.children,
        rooms: stay.rooms,
      }).toString();
      const data = await api(`/rooms/search?${params}`);
      const match = (data.results || []).find((r) => Number(r.id) === Number(room.id));
      if (!match) {
        setError(`${room.name} is not available for those dates. Try different dates or fewer rooms.`);
        return;
      }
      setQuery({ ...stay });
      setSelected(match);
      setResults(data);
      setStep('book');
    } catch (err) {
      setError(err.message);
    } finally {
      setBookingRoomId(null);
    }
  };

  const bookFromResults = (room) => {
    setSelected(room);
    setStep('book');
  };

  const book = async () => {
    if (!selected) return;
    setLoading(true);
    setError('');
    try {
      const data = await api('/bookings', {
        method: 'POST',
        body: {
          ...guest,
          room_type_id: selected.id,
          check_in: query.check_in,
          check_out: query.check_out,
          adults: query.adults,
          children: query.children,
          rooms_count: query.rooms,
          discount_code: guest.discount_code || undefined,
        },
      });
      setBooking(data);
      setStep('success');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const backToBrowse = () => {
    setStep('browse');
    setResults(null);
    setSelected(null);
    setBooking(null);
    setError('');
  };

  const detailsForModal = detailsRoom
    ? {
        ...detailsRoom,
        price_per_night: detailsRoom.price_per_night ?? detailsRoom.base_price,
        total_price:
          detailsRoom.total_price ??
          Number(detailsRoom.base_price || 0) * nights * Number(query.rooms || 1),
        available_rooms: detailsRoom.available_rooms ?? detailsRoom.total_rooms,
      }
    : null;

  return (
    <div className="ifmpp-root ifmpp-frontend-app mx-auto max-w-5xl">
      <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 text-white shadow-xl">
        <div className="relative px-6 py-10 sm:px-10">
          <div
            className="absolute inset-0 opacity-30"
            style={{
              backgroundImage:
                'radial-gradient(circle at 20% 20%, rgba(255,255,255,.25), transparent 40%), radial-gradient(circle at 80% 0%, rgba(187,221,208,.35), transparent 35%)',
            }}
          />
          <div className="relative">
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-brand-200">Reservations</p>
            <h1 className="mt-2 font-display text-4xl font-bold sm:text-5xl">
              {cfg.title || settings.hotel_name || 'Find Your Stay'}
            </h1>
            <p className="mt-3 max-w-xl text-brand-100">
              Browse room types below, or search by dates to see what’s available for your stay.
            </p>
          </div>
        </div>

        <form
          onSubmit={search}
          className="relative grid gap-3 border-t border-white/10 bg-white/95 p-4 text-brand-950 backdrop-blur sm:grid-cols-2 lg:grid-cols-6"
        >
          <Input
            label="Check-in"
            type="date"
            value={query.check_in}
            onChange={(e) => setQuery({ ...query, check_in: e.target.value })}
          />
          <Input
            label="Check-out"
            type="date"
            value={query.check_out}
            onChange={(e) => setQuery({ ...query, check_out: e.target.value })}
          />
          <Input
            label="Adults"
            type="number"
            min="1"
            value={query.adults}
            onChange={(e) => setQuery({ ...query, adults: e.target.value })}
          />
          <Input
            label="Children"
            type="number"
            min="0"
            value={query.children}
            onChange={(e) => setQuery({ ...query, children: e.target.value })}
          />
          <Input
            label="Rooms"
            type="number"
            min="1"
            value={query.rooms}
            onChange={(e) => setQuery({ ...query, rooms: e.target.value })}
          />
          <div className="flex items-end">
            <Button type="submit" className="w-full" disabled={loading}>
              {loading && step !== 'book' ? 'Searching…' : 'Search rooms'}
            </Button>
          </div>
        </form>
      </div>

      {error && (
        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      )}

      {loading && step !== 'success' && step !== 'book' && <Loading />}

      {/* Browse catalog — hidden after a Reservations search */}
      {step === 'browse' && (
        <div className="mt-8 space-y-4">
          <div>
            <h2 className="font-display text-2xl font-bold text-brand-950">Available rooms</h2>
            <p className="text-sm text-brand-600">
              Pick dates on a room to reserve, or use the search box above to filter by availability.
            </p>
          </div>

          {catalogLoading && <Loading />}

          {!catalogLoading && !catalog.length && (
            <Empty title="No rooms listed" description="Room types will appear here once they are published." />
          )}

          {!catalogLoading &&
            catalog.map((room) => (
              <RoomCard
                key={room.id}
                room={room}
                settings={settings}
                mode="browse"
                nights={nightsBetween(getRoomStay(room.id).check_in, getRoomStay(room.id).check_out)}
                stay={getRoomStay(room.id)}
                onStayChange={(stay) => setRoomStay(room.id, stay)}
                onDetails={setDetailsRoom}
                onBook={bookFromBrowse}
                booking={bookingRoomId}
              />
            ))}
        </div>
      )}

      {/* Search results — same flow as before */}
      {step === 'results' && results && !loading && (
        <div className="mt-8 space-y-4">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="font-display text-2xl font-bold text-brand-950">Available rooms</h2>
              <p className="text-sm text-brand-600">
                {results.nights} night{results.nights > 1 ? 's' : ''} · {results.results.length} option
                {results.results.length !== 1 ? 's' : ''} for {query.check_in} → {query.check_out}
              </p>
            </div>
            <Button variant="ghost" onClick={backToBrowse}>
              Browse all rooms
            </Button>
          </div>

          {!results.results.length && (
            <Empty title="No rooms available" description="Try different dates or fewer rooms." />
          )}

          {results.results.map((room) => (
            <RoomCard
              key={room.id}
              room={room}
              settings={settings}
              mode="results"
              nights={nights}
              onDetails={setDetailsRoom}
              onBook={bookFromResults}
            />
          ))}
        </div>
      )}

      {step === 'book' && selected && (
        <Card className="mt-8 p-6">
          <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="font-display text-2xl font-bold">Guest details</h2>
              <p className="text-sm text-brand-600">
                {selected.name} · {query.check_in} → {query.check_out} · {money(selected.total_price, settings)}
              </p>
            </div>
            <Button
              variant="ghost"
              onClick={() => setStep(results ? 'results' : 'browse')}
            >
              Back
            </Button>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="First name"
              value={guest.first_name}
              onChange={(e) => setGuest({ ...guest, first_name: e.target.value })}
            />
            <Input
              label="Last name"
              value={guest.last_name}
              onChange={(e) => setGuest({ ...guest, last_name: e.target.value })}
            />
            <Input
              label="Email"
              type="email"
              value={guest.email}
              onChange={(e) => setGuest({ ...guest, email: e.target.value })}
            />
            <Input
              label="Phone"
              value={guest.phone}
              onChange={(e) => setGuest({ ...guest, phone: e.target.value })}
            />
            <Input
              label="Address"
              className="sm:col-span-2"
              value={guest.address}
              onChange={(e) => setGuest({ ...guest, address: e.target.value })}
            />
            <Input
              label="Discount code"
              value={guest.discount_code}
              onChange={(e) => setGuest({ ...guest, discount_code: e.target.value })}
            />
            <Select
              label="Rooms"
              value={query.rooms}
              onChange={(e) => setQuery({ ...query, rooms: e.target.value })}
            >
              {Array.from({ length: Math.max(1, selected.available_rooms || 1) }, (_, i) => i + 1).map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </Select>
          </div>
          <div className="mt-6 flex justify-end">
            <Button onClick={book} disabled={loading || !guest.first_name || !guest.last_name}>
              {loading ? 'Booking…' : 'Confirm booking'}
            </Button>
          </div>
        </Card>
      )}

      {step === 'success' && booking && (
        <Card className="mt-8 border-emerald-200 bg-emerald-50/50 p-8 text-center">
          <p className="text-sm font-semibold uppercase tracking-widest text-emerald-700">Booking confirmed</p>
          <h2 className="mt-2 font-display text-3xl font-bold text-brand-950">{booking.booking_code}</h2>
          <p className="mt-2 text-brand-700">
            {booking.first_name} {booking.last_name} · {booking.room_name}
          </p>
          <p className="mt-1 text-sm text-brand-600">
            {booking.check_in} → {booking.check_out} · {money(booking.total_amount, settings)}
          </p>
          <Button className="mt-6" variant="secondary" onClick={backToBrowse}>
            Make another booking
          </Button>
        </Card>
      )}

      <Modal open={!!detailsForModal} onClose={() => setDetailsRoom(null)} className="max-w-2xl">
        {detailsForModal && (
          <div>
            <ImageSlider
              images={[
                ...(detailsForModal.image_url ? [detailsForModal.image_url] : []),
                ...(detailsForModal.gallery || []).map((g) => g.image_url),
              ]}
              aspect="aspect-video"
            />
            <div className="p-6">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="font-display text-2xl font-bold text-brand-950">{detailsForModal.name}</h2>
                  <p className="mt-1 text-sm text-brand-600">
                    up to {detailsForModal.max_adults} adults · {detailsForModal.max_children} children ·{' '}
                    {detailsForModal.available_rooms} available
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-brand-500">
                    {money(detailsForModal.price_per_night, settings)} / night
                  </p>
                  {step === 'results' && (
                    <p className="font-display text-2xl font-bold text-brand-800">
                      {money(detailsForModal.total_price, settings)}
                    </p>
                  )}
                </div>
              </div>
              <p className="mt-4 text-brand-700">{detailsForModal.description}</p>
              <div className="mt-4 flex flex-wrap gap-1.5">
                {(detailsForModal.amenities || []).map((a) => (
                  <Badge key={a.id}>{a.name}</Badge>
                ))}
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <Button variant="ghost" onClick={() => setDetailsRoom(null)}>
                  Close
                </Button>
                {step === 'results' && (
                  <Button
                    onClick={() => {
                      setSelected(detailsForModal);
                      setDetailsRoom(null);
                      setStep('book');
                    }}
                  >
                    Book now
                  </Button>
                )}
                {step === 'browse' && (
                  <Button
                    onClick={() => {
                      setDetailsRoom(null);
                      bookFromBrowse(detailsForModal);
                    }}
                  >
                    Book with selected dates
                  </Button>
                )}
              </div>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}

const rootEl = document.getElementById('ifmpp-frontend-root');
if (rootEl) {
  createRoot(rootEl).render(<SearchApp />);
}
