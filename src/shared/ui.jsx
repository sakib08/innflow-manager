import { useEffect, useState } from 'react';
import clsx from 'clsx';

export function Card({ children, className }) {
  return (
    <div className={clsx('rounded-xl border border-sand-200 bg-white shadow-sm', className)}>
      {children}
    </div>
  );
}

export function Button({ children, variant = 'primary', className, ...props }) {
  const styles = {
    primary: 'bg-brand-700 text-white hover:bg-brand-800',
    secondary: 'bg-sand-100 text-brand-900 hover:bg-sand-200',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    ghost: 'bg-transparent text-brand-700 hover:bg-brand-50',
  };
  return (
    <button
      type="button"
      className={clsx(
        'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-50',
        styles[variant],
        className
      )}
      {...props}
    >
      {children}
    </button>
  );
}

export function Input({ label, className, ...props }) {
  return (
    <label className="block text-sm">
      {label && <span className="mb-1 block font-medium text-brand-800">{label}</span>}
      <input
        className={clsx(
          'block w-full rounded-lg border-sand-200 shadow-sm focus:border-brand-500 focus:ring-brand-500',
          className
        )}
        {...props}
      />
    </label>
  );
}

export function Select({ label, children, className, ...props }) {
  return (
    <label className="block text-sm">
      {label && <span className="mb-1 block font-medium text-brand-800">{label}</span>}
      <select
        className={clsx(
          'block w-full rounded-lg border-sand-200 shadow-sm focus:border-brand-500 focus:ring-brand-500',
          className
        )}
        {...props}
      >
        {children}
      </select>
    </label>
  );
}

export function Textarea({ label, className, ...props }) {
  return (
    <label className="block text-sm">
      {label && <span className="mb-1 block font-medium text-brand-800">{label}</span>}
      <textarea
        className={clsx(
          'block w-full rounded-lg border-sand-200 shadow-sm focus:border-brand-500 focus:ring-brand-500',
          className
        )}
        {...props}
      />
    </label>
  );
}

export function MediaPicker({ label, value, onChange, buttonLabel = 'Select image' }) {
  const openLibrary = () => {
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
      onChange(url);
    });

    frame.open();
  };

  return (
    <div>
      {label && <span className="mb-1 block text-sm font-medium text-brand-800">{label}</span>}
      <div className="flex items-center gap-3">
        <div className="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-sand-200 bg-sand-50">
          {value ? (
            <img src={value} alt="" className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full items-center justify-center text-[10px] text-brand-400">No image</div>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            className="flex-1"
            placeholder="Image URL"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
          />
          <Button type="button" variant="secondary" onClick={openLibrary}>
            {buttonLabel}
          </Button>
          {value && (
            <Button type="button" variant="ghost" onClick={() => onChange('')}>
              Remove
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

export function GalleryPicker({ label, value = [], onChange, buttonLabel = 'Add images' }) {
  const openLibrary = () => {
    if (typeof window === 'undefined' || !window.wp || !window.wp.media) {
      // eslint-disable-next-line no-alert
      window.alert('WordPress media library is not available on this page.');
      return;
    }

    const frame = window.wp.media({
      title: 'Select or upload gallery images',
      button: { text: 'Add to gallery' },
      library: { type: 'image' },
      multiple: true,
    });

    frame.on('select', () => {
      const attachments = frame.state().get('selection').toJSON();
      const urls = attachments.map((a) => (a.sizes && a.sizes.medium && a.sizes.medium.url) || a.url);
      onChange([...value, ...urls]);
    });

    frame.open();
  };

  const remove = (index) => {
    onChange(value.filter((_, i) => i !== index));
  };

  const move = (index, dir) => {
    const next = [...value];
    const target = index + dir;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    onChange(next);
  };

  return (
    <div>
      {label && <span className="mb-1 block text-sm font-medium text-brand-800">{label}</span>}
      <div className="flex flex-wrap gap-2">
        {value.map((url, i) => (
          <div key={`${url}-${i}`} className="group relative h-20 w-20 overflow-hidden rounded-lg border border-sand-200 bg-sand-50">
            <img src={url} alt="" className="h-full w-full object-cover" />
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5 bg-black/50 opacity-0 transition group-hover:opacity-100">
              <div className="flex gap-1">
                <button
                  type="button"
                  onClick={() => move(i, -1)}
                  className="rounded bg-white/90 px-1 text-[10px] font-bold text-brand-900 disabled:opacity-40"
                  disabled={i === 0}
                >
                  ←
                </button>
                <button
                  type="button"
                  onClick={() => move(i, 1)}
                  className="rounded bg-white/90 px-1 text-[10px] font-bold text-brand-900 disabled:opacity-40"
                  disabled={i === value.length - 1}
                >
                  →
                </button>
              </div>
              <button
                type="button"
                onClick={() => remove(i)}
                className="rounded bg-red-600/90 px-1.5 text-[10px] font-bold text-white"
              >
                Remove
              </button>
            </div>
          </div>
        ))}
        <button
          type="button"
          onClick={openLibrary}
          className="flex h-20 w-20 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-sand-300 text-brand-500 hover:border-brand-400 hover:text-brand-700"
        >
          <span className="text-xl leading-none">+</span>
          <span className="text-[10px] font-semibold">{buttonLabel}</span>
        </button>
      </div>
    </div>
  );
}

export function ImageSlider({ images = [], className, aspect = 'aspect-[4/3]' }) {
  const [index, setIndex] = useState(0);
  const slides = images.length ? images : [];

  if (!slides.length) {
    return (
      <div className={clsx('flex items-center justify-center bg-brand-100 text-brand-500', aspect, className)}>
        No image
      </div>
    );
  }

  const prev = (e) => {
    e?.stopPropagation();
    setIndex((i) => (i - 1 + slides.length) % slides.length);
  };
  const next = (e) => {
    e?.stopPropagation();
    setIndex((i) => (i + 1) % slides.length);
  };

  return (
    <div className={clsx('relative overflow-hidden bg-brand-100', aspect, className)}>
      <img src={slides[index]} alt="" className="h-full w-full object-cover transition" />
      {slides.length > 1 && (
        <>
          <button
            type="button"
            onClick={prev}
            className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-1.5 text-brand-900 shadow hover:bg-white"
          >
            ‹
          </button>
          <button
            type="button"
            onClick={next}
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-1.5 text-brand-900 shadow hover:bg-white"
          >
            ›
          </button>
          <div className="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-1.5">
            {slides.map((_, i) => (
              <button
                key={i}
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  setIndex(i);
                }}
                className={clsx('h-1.5 w-1.5 rounded-full transition', i === index ? 'bg-white' : 'bg-white/50')}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
}

export function Modal({ open, onClose, children, className }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
      <div
        className={clsx('ifmpp-root max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl', className)}
        onClick={(e) => e.stopPropagation()}
      >
        {children}
      </div>
    </div>
  );
}

export function Badge({ children, tone = 'neutral' }) {
  const tones = {
    neutral: 'bg-sand-100 text-brand-800',
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-800',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-sky-100 text-sky-800',
  };
  return (
    <span className={clsx('inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold', tones[tone])}>
      {children}
    </span>
  );
}

export function Stat({ label, value, hint }) {
  return (
    <Card className="p-5">
      <p className="text-sm font-medium text-brand-600">{label}</p>
      <p className="mt-2 font-display text-3xl font-bold text-brand-950">{value}</p>
      {hint && <p className="mt-1 text-xs text-brand-500">{hint}</p>}
    </Card>
  );
}

export function PageHeader({ title, subtitle, actions }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="font-display text-3xl font-bold text-brand-950">{title}</h1>
        {subtitle && <p className="mt-1 text-brand-600">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

export function Empty({ title, description }) {
  return (
    <div className="rounded-xl border border-dashed border-sand-300 bg-sand-50 px-6 py-12 text-center">
      <p className="font-display text-lg font-semibold text-brand-900">{title}</p>
      {description && <p className="mt-1 text-sm text-brand-600">{description}</p>}
    </div>
  );
}

export function Loading() {
  return (
    <div className="flex items-center justify-center py-16 text-brand-600">
      <div className="h-8 w-8 animate-spin rounded-full border-2 border-brand-200 border-t-brand-700" />
    </div>
  );
}

export function Pagination({ page, pageSize = 10, total, onChange }) {
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const currentPage = Math.min(page, totalPages);
  if (total <= pageSize) return null;

  const from = (currentPage - 1) * pageSize + 1;
  const to = Math.min(currentPage * pageSize, total);

  return (
    <div className="flex flex-col gap-2 rounded-xl border border-sand-200 bg-sand-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-xs text-brand-600">
        Showing {from}–{to} of {total}
      </p>
      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled={currentPage <= 1}
          onClick={() => onChange(Math.max(1, currentPage - 1))}
          className="rounded-lg border border-sand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-800 disabled:cursor-not-allowed disabled:opacity-40"
        >
          Previous
        </button>
        <span className="text-xs font-semibold text-brand-700">
          Page {currentPage} of {totalPages}
        </span>
        <button
          type="button"
          disabled={currentPage >= totalPages}
          onClick={() => onChange(Math.min(totalPages, currentPage + 1))}
          className="rounded-lg border border-sand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-800 disabled:cursor-not-allowed disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}

export function Table({ columns, rows, empty, rowClassName, pageSize = 10 }) {
  const [page, setPage] = useState(1);
  const total = rows?.length || 0;
  const size = pageSize > 0 ? pageSize : total || 1;
  const totalPages = Math.max(1, Math.ceil(total / size));
  const currentPage = Math.min(page, totalPages);

  useEffect(() => {
    setPage(1);
  }, [total, size]);

  if (!rows?.length) {
    return <Empty title={empty || 'No records yet'} />;
  }

  const start = (currentPage - 1) * size;
  const pageRows = rows.slice(start, start + size);
  const from = start + 1;
  const to = Math.min(start + size, total);
  const showPager = total > size;

  return (
    <div className="overflow-hidden rounded-xl border border-sand-200 bg-white">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-sand-200 text-sm">
          <thead className="bg-sand-50">
            <tr>
              {columns.map((c) => (
                <th key={c.key} className="px-4 py-3 text-left font-semibold text-brand-700">
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-sand-100">
            {pageRows.map((row, i) => (
              <tr key={row.id || start + i} className={clsx('hover:bg-sand-50/60', rowClassName?.(row))}>
                {columns.map((c) => (
                  <td key={c.key} className="px-4 py-3 text-brand-900">
                    {c.render ? c.render(row) : row[c.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {showPager && (
        <div className="flex flex-col gap-2 border-t border-sand-200 bg-sand-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-brand-600">
            Showing {from}–{to} of {total}
          </p>
          <div className="flex items-center gap-2">
            <button
              type="button"
              disabled={currentPage <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-sand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Previous
            </button>
            <span className="text-xs font-semibold text-brand-700">
              Page {currentPage} of {totalPages}
            </span>
            <button
              type="button"
              disabled={currentPage >= totalPages}
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              className="rounded-lg border border-sand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
