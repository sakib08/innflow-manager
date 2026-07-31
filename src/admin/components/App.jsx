import { useMemo, useState } from 'react';
import { pageFromSlug } from '../../shared/api';
import { cfg } from './config';
import Shell from './Shell';
import Dashboard from './Dashboard';
import RoomsPage from './RoomsPage';
import BookingsPage from './BookingsPage';
import GuestsPage from './GuestsPage';
import BillingPage from './BillingPage';
import StaffPage from './StaffPage';
import RestaurantsPage from './RestaurantsPage';
import SettingsPage from './SettingsPage';
import TrashPage from './TrashPage';

export default function App() {
  const initial = useMemo(() => pageFromSlug(cfg.page), []);
  const [page, setPage] = useState(initial);

  const view = {
    dashboard: <Dashboard />,
    rooms: <RoomsPage />,
    bookings: <BookingsPage />,
    guests: <GuestsPage />,
    billing: <BillingPage />,
    staff: <StaffPage />,
    restaurants: <RestaurantsPage />,
    trash: <TrashPage />,
    settings: <SettingsPage />,
  }[page] || <Dashboard />;

  return (
    <Shell page={page} setPage={setPage}>
      {view}
    </Shell>
  );
}
