import { cfg } from './config';

export default function Shell({ page, setPage, children }) {
  const nav = [
    { id: 'dashboard', label: 'Dashboard' },
    { id: 'rooms', label: 'Rooms' },
    { id: 'bookings', label: 'Bookings' },
    { id: 'guests', label: 'Guests' },
    { id: 'billing', label: 'Billing' },
    { id: 'staff', label: 'Staff' },
    { id: 'restaurants', label: 'Restaurants' },
    { id: 'trash', label: 'Trash' },
    { id: 'settings', label: 'Settings' },
  ];

  return (
    <div className="shmpp-root min-h-screen bg-gradient-to-br from-sand-50 via-white to-brand-50/40 -mx-5 px-5 py-4">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-sand-200 pb-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">StayNexus Hotel Manager</p>
          <h2 className="font-display text-2xl font-bold text-brand-950">{cfg.settings?.hotel_name || 'Grand Hotel'}</h2>
        </div>
        <nav className="flex flex-wrap gap-1">
          {nav.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setPage(item.id)}
              className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
                page === item.id ? 'bg-brand-700 text-white' : 'text-brand-700 hover:bg-brand-50'
              }`}
            >
              {item.label}
            </button>
          ))}
        </nav>
      </div>
      {children}
    </div>
  );
}
