import { createRoot } from 'react-dom/client';
import App from './components/App';

const rootEl = document.getElementById('ifmpp-admin-root');
if (rootEl) {
  createRoot(rootEl).render(<App />);
}
