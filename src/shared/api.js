function readRootConfig() {
  if (typeof document === 'undefined') return null;
  const el = document.getElementById('ifmpp-frontend-root') || document.getElementById('ifmpp-admin-root');
  if (!el || !el.dataset) return null;

  let settings = {};
  if (el.dataset.settings) {
    try {
      settings = JSON.parse(el.dataset.settings);
    } catch {
      settings = {};
    }
  }

  if (!el.dataset.apiUrl) return null;

  return {
    apiUrl: el.dataset.apiUrl,
    nonce: el.dataset.nonce || '',
    title: el.dataset.title || '',
    settings,
  };
}

function fallbackConfig() {
  if (typeof window === 'undefined' || !window.location) return {};
  return {
    apiUrl: `${window.location.origin}/wp-json/innflow-manager/v1`,
    nonce: '',
    settings: {},
  };
}

const API = () => {
  if (typeof window === 'undefined') return {};
  if (window.ifmppAdmin && window.ifmppAdmin.apiUrl) return window.ifmppAdmin;
  if (window.ifmppFrontend && window.ifmppFrontend.apiUrl) return window.ifmppFrontend;
  const fromDom = readRootConfig();
  if (fromDom) {
    // Cache onto window so later calls are cheap / consistent.
    window.ifmppFrontend = { ...(window.ifmppFrontend || {}), ...fromDom };
    return window.ifmppFrontend;
  }
  return fallbackConfig();
};

function isAdminContext() {
  return typeof window !== 'undefined' && !!window.ifmppAdmin;
}

function localISODate(date = new Date()) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export function todayISO() {
  return localISODate(new Date());
}

export function addDaysISO(days) {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + days);
  return localISODate(d);
}

export async function api(path, options = {}) {
  const cfg = API();
  if (!cfg.apiUrl) {
    throw new Error('Booking API is not configured on this page. Please reload.');
  }

  const method = (options.method || 'GET').toUpperCase();
  const url = `${cfg.apiUrl}${path}`;
  const headers = { ...(options.headers || {}) };

  if (options.body !== undefined && options.body !== null) {
    headers['Content-Type'] = 'application/json';
  }

  // Admin REST routes need a nonce. Frontend routes are public — sending a stale
  // X-WP-Nonce makes WordPress return 403 rest_cookie_invalid_nonce.
  if (cfg.nonce && isAdminContext()) {
    headers['X-WP-Nonce'] = cfg.nonce;
  }

  const res = await fetch(url, {
    ...options,
    method,
    headers,
    body: options.body !== undefined && options.body !== null ? JSON.stringify(options.body) : undefined,
    credentials: 'same-origin',
  });

  const text = await res.text();
  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = {};
    }
  }

  if (!res.ok) {
    const message =
      (data && (data.message || data.code)) ||
      (text && text.length < 200 ? text : '') ||
      `Request failed (${res.status})`;
    throw new Error(typeof message === 'string' ? message : 'Request failed');
  }

  return data;
}

export async function apiUpload(path, formData) {
  const cfg = API();
  if (!cfg.apiUrl) {
    throw new Error('Booking API is not configured on this page. Please reload.');
  }

  const headers = {};
  if (cfg.nonce && isAdminContext()) {
    headers['X-WP-Nonce'] = cfg.nonce;
  }

  const res = await fetch(`${cfg.apiUrl}${path}`, {
    method: 'POST',
    headers,
    body: formData,
    credentials: 'same-origin',
  });

  const text = await res.text();
  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = {};
    }
  }

  if (!res.ok) {
    throw new Error((data && (data.message || data.code)) || `Request failed (${res.status})`);
  }
  return data;
}

export function buildApiUrl(path, params = {}) {
  const cfg = API();
  const query = new URLSearchParams({
    ...params,
    ...(cfg.nonce && isAdminContext() ? { _wpnonce: cfg.nonce } : {}),
  }).toString();
  return `${cfg.apiUrl}${path}?${query}`;
}

export function money(amount, settings = {}) {
  const symbol = settings.currency_symbol || '$';
  const n = Number(amount || 0);
  return `${symbol}${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function pageFromSlug(slug) {
  const map = {
    'innflow-manager': 'dashboard',
    'innflow-manager-rooms': 'rooms',
    'innflow-manager-bookings': 'bookings',
    'innflow-manager-guests': 'guests',
    'innflow-manager-billing': 'billing',
    'innflow-manager-staff': 'staff',
    'innflow-manager-restaurants': 'restaurants',
    'innflow-manager-trash': 'trash',
    'innflow-manager-settings': 'settings',
  };
  return map[slug] || 'dashboard';
}

export function getFrontendConfig() {
  return API();
}
