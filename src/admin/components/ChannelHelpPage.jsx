import { Button, Card, PageHeader } from '../../shared/ui';
import { cfg } from './config';

const channelsUrl = `${cfg.adminUrl || 'admin.php'}?page=staynexus-hotel-manager-channels`;

function Step({ n, title, children }) {
  return (
    <div className="flex gap-4">
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-700 text-sm font-bold text-white">
        {n}
      </div>
      <div className="min-w-0 flex-1 space-y-2 pb-6">
        <h3 className="font-display text-lg font-semibold text-brand-950">{title}</h3>
        <div className="space-y-2 text-sm leading-relaxed text-brand-700">{children}</div>
      </div>
    </div>
  );
}

function Callout({ tone = 'info', children }) {
  const styles = {
    info: 'bg-sky-50 text-sky-950 border-sky-200',
    warn: 'bg-amber-50 text-amber-950 border-amber-200',
    ok: 'bg-emerald-50 text-emerald-950 border-emerald-200',
  };
  return <div className={`rounded-lg border px-4 py-3 text-sm ${styles[tone]}`}>{children}</div>;
}

export default function ChannelHelpPage() {
  return (
    <div>
      <PageHeader
        title="Channex help"
        subtitle="Connect StayNexus to Booking.com, Agoda, and other OTAs through Channex"
        actions={
          <Button onClick={() => { window.location.href = channelsUrl; }}>
            Open Channels setup
          </Button>
        }
      />

      <Card className="mb-6 space-y-3 p-5">
        <h2 className="font-display text-xl font-semibold text-brand-950">How it works</h2>
        <p className="text-sm text-brand-700">
          StayNexus is your hotel system (rooms, availability, bookings). Channex is the channel manager.
          Booking.com and Agoda connect to <strong>Channex</strong>, not directly to WordPress.
        </p>
        <div className="overflow-x-auto rounded-lg bg-sand-50 p-4 font-mono text-xs text-brand-800">
          Website / StayNexus  ↔  Channex  ↔  Booking.com / Agoda / …
        </div>
        <ul className="list-disc space-y-1 pl-5 text-sm text-brand-700">
          <li>Guest books on your site → StayNexus lowers availability → Channex updates OTAs.</li>
          <li>Guest books on Booking.com → Channex notifies StayNexus → booking appears in Bookings and website rooms drop.</li>
        </ul>
      </Card>

      <Card className="mb-6 p-5">
        <h2 className="mb-4 font-display text-xl font-semibold text-brand-950">Setup checklist</h2>

        <Step n={1} title="Create a Channex account and property">
          <p>
            Use <a className="font-semibold text-brand-800 underline" href="https://staging.channex.io" target="_blank" rel="noreferrer">staging.channex.io</a> for testing,
            or the live Channex app for production.
          </p>
          <p>Create a <strong>Property</strong>, then create <strong>Room Types</strong> and <strong>Rate Plans</strong> that match your hotel.</p>
        </Step>

        <Step n={2} title="Create an API key">
          <p>In Channex: account / user settings → <strong>API Keys</strong> → create a key → copy it.</p>
          <p>Paste that key into StayNexus → Channels → <strong>API key</strong>.</p>
        </Step>

        <Step n={3} title="Connect Booking.com / Agoda inside Channex">
          <p>In Channex: Property → Channels → Create Channel.</p>
          <Callout tone="warn">
            <strong>Hotel ID</strong> must be Booking.com’s <strong>numeric</strong> hotel ID (e.g. <code>10745030</code>),
            or a green staging test ID. Do <strong>not</strong> paste a Channex UUID here.
          </Callout>
          <p>Finish Mapping in Channex (Channex rooms ↔ OTA rooms), then activate the channel.</p>
          <p>Repeat for Agoda if needed.</p>
        </Step>

        <Step n={4} title="Fill StayNexus → Channels connection">
          <div className="overflow-x-auto">
            <table className="w-full border-collapse text-left text-sm">
              <thead>
                <tr className="border-b border-sand-200 text-brand-800">
                  <th className="py-2 pr-3 font-semibold">Field</th>
                  <th className="py-2 font-semibold">What to enter</th>
                </tr>
              </thead>
              <tbody className="text-brand-700">
                <tr className="border-b border-sand-100">
                  <td className="py-2 pr-3 font-medium">Environment</td>
                  <td className="py-2">Staging for test keys; Live only with a live Channex key</td>
                </tr>
                <tr className="border-b border-sand-100">
                  <td className="py-2 pr-3 font-medium">API key</td>
                  <td className="py-2">Key from step 2</td>
                </tr>
                <tr className="border-b border-sand-100">
                  <td className="py-2 pr-3 font-medium">Property ID</td>
                  <td className="py-2">Channex property UUID (from property URL / details), e.g. <code>356c0723-…</code></td>
                </tr>
                <tr>
                  <td className="py-2 pr-3 font-medium">Sync enabled</td>
                  <td className="py-2">Turn on after rooms are mapped</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p>Click <strong>Save connection</strong>, then <strong>Load from Channex</strong>.</p>
        </Step>

        <Step n={5} title="Map rooms (required before sync)">
          <p>Under <strong>Room mapping</strong>, for each StayNexus room type:</p>
          <ul className="list-disc space-y-1 pl-5">
            <li>Choose the matching <strong>Channex room type</strong> (or paste its UUID).</li>
            <li>Optionally choose a <strong>rate plan</strong> so prices push too.</li>
          </ul>
          <Callout tone="warn">
            You must click <strong>Save maps</strong>. If you skip this, sync shows:
            “Map at least one room type before syncing”.
          </Callout>
        </Step>

        <Step n={6} title="Push inventory and pull bookings">
          <ol className="list-decimal space-y-1 pl-5">
            <li><strong>Push availability &amp; rates</strong> — sends free rooms (and rates) to Channex → OTAs.</li>
            <li><strong>Pull bookings now</strong> — imports new/changed OTA reservations into StayNexus Bookings.</li>
            <li>Optional: copy the <strong>Webhook URL</strong> into Channex webhooks for near-live booking delivery.</li>
          </ol>
          <Callout tone="ok">
            Automatic backup: StayNexus also polls Channex about every 15 minutes and does a full ARI refresh daily.
          </Callout>
        </Step>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="space-y-3 p-5">
          <h2 className="font-display text-lg font-semibold text-brand-950">Common mistakes</h2>
          <ul className="list-disc space-y-2 pl-5 text-sm text-brand-700">
            <li>Channex UUID in Booking.com <strong>Hotel ID</strong> → channel will not create. Use a numeric ID.</li>
            <li>Booking.com Hotel ID in StayNexus <strong>Property ID</strong> → wrong. Property ID must be Channex UUID.</li>
            <li>Staging API key with Live environment (or the reverse) → unauthorized errors.</li>
            <li>Pushing sync before <strong>Save maps</strong> → “Map at least one room type…”.</li>
            <li>No local room types in StayNexus → create rooms under Rooms first.</li>
          </ul>
        </Card>

        <Card className="space-y-3 p-5">
          <h2 className="font-display text-lg font-semibold text-brand-950">Where bookings appear</h2>
          <ul className="list-disc space-y-2 pl-5 text-sm text-brand-700">
            <li>OTA bookings show under <strong>Bookings</strong> with source like <code>booking.com</code> or <code>agoda</code>.</li>
            <li>Codes often start with <code>CHX-</code>.</li>
            <li>Website search uses the same inventory, so those dates become unavailable (or lower count).</li>
          </ul>
          <h2 className="pt-2 font-display text-lg font-semibold text-brand-950">Useful links</h2>
          <ul className="list-disc space-y-1 pl-5 text-sm">
            <li>
              <a className="text-brand-800 underline" href="https://docs.channex.io/guides/pms-integration-guide" target="_blank" rel="noreferrer">
                Channex PMS integration guide
              </a>
            </li>
            <li>
              <a className="text-brand-800 underline" href="https://staging.channex.io" target="_blank" rel="noreferrer">
                Channex staging
              </a>
            </li>
            <li>
              <a className="text-brand-800 underline" href={channelsUrl}>
                StayNexus Channels setup
              </a>
            </li>
          </ul>
        </Card>
      </div>
    </div>
  );
}
