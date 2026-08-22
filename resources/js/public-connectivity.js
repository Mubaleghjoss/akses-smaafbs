const CLIENT_KEY = 'akses:connectivity:client-id:v1';
const QUEUE_KEY = 'akses:connectivity:events:v1';
const DAILY_SEEN_PREFIX = 'akses:connectivity:seen:';
const ENDPOINT = '/api/v1/monitoring/public-connectivity';
const MAX_QUEUE_SIZE = 20;

let flushInProgress = false;

const uuid = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
};

const clientId = () => {
    try {
        const existing = window.localStorage.getItem(CLIENT_KEY);

        if (existing) {
            return existing;
        }

        const created = uuid();
        window.localStorage.setItem(CLIENT_KEY, created);

        return created;
    } catch (_) {
        return uuid();
    }
};

const readQueue = () => {
    try {
        const value = JSON.parse(window.localStorage.getItem(QUEUE_KEY) || '[]');

        return Array.isArray(value) ? value.slice(-MAX_QUEUE_SIZE) : [];
    } catch (_) {
        return [];
    }
};

const writeQueue = (events) => {
    try {
        window.localStorage.setItem(QUEUE_KEY, JSON.stringify(events.slice(-MAX_QUEUE_SIZE)));
    } catch (_) {
        // Telemetry is optional and never blocks the public page.
    }
};

const routeGroup = (path) => {
    if (path === '/') {
        return 'home';
    }

    if (path === '/admin/login' || path === '/login') {
        return 'login';
    }

    if (path === '/perpustakaan/program-literasi-numerasi') {
        return 'literacy_list';
    }

    if (path.startsWith('/perpustakaan/program-literasi-numerasi/')) {
        return 'literacy_material';
    }

    return 'other_public';
};

const enqueueDailySession = () => {
    const day = new Date().toISOString().slice(0, 10);
    const key = DAILY_SEEN_PREFIX + day;

    try {
        if (window.localStorage.getItem(key) === '1') {
            return;
        }

        window.localStorage.setItem(key, '1');
    } catch (_) {
        return;
    }

    const events = readQueue();
    events.push({
        event_uuid: uuid(),
        event_type: 'session_seen',
        route_group: routeGroup(window.location.pathname),
        http_status: 200,
        service_worker_version: 'public-shell-v7',
        occurred_at: new Date().toISOString(),
        recovered_at: null,
    });
    writeQueue(events);
};

const flush = async () => {
    if (flushInProgress || !window.navigator.onLine) {
        return;
    }

    const queued = readQueue();

    if (!queued.length) {
        return;
    }

    flushInProgress = true;
    const recoveredAt = new Date().toISOString();
    const batch = queued.slice(0, 10).map((event) => ({
        ...event,
        recovered_at: event.event_type === 'session_seen'
            ? null
            : (event.recovered_at || recoveredAt),
    }));

    try {
        const response = await window.fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ client_id: clientId(), events: batch }),
            cache: 'no-store',
            credentials: 'omit',
            keepalive: true,
        });

        if (response.ok) {
            const sent = new Set(batch.map((event) => event.event_uuid));
            writeQueue(readQueue().filter((event) => !sent.has(event.event_uuid)));
        }
    } catch (_) {
        // The queue remains local and is retried after a later successful page load.
    } finally {
        flushInProgress = false;
    }
};

const bootConnectivity = () => {
    if (document.documentElement.dataset.pwaShell !== 'public') {
        return;
    }

    enqueueDailySession();
    flush();
    window.addEventListener('online', flush);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootConnectivity, { once: true });
} else {
    bootConnectivity();
}
