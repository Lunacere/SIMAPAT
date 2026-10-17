import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const chat = document.querySelector('[data-meeting-chat]');
if (chat) {
    const list = chat.querySelector('[data-chat-messages]');
    const status = chat.querySelector('[data-chat-status]');
    const error = chat.querySelector('[data-chat-error]');
    const form = chat.querySelector('form');
    const input = form.querySelector('textarea');
    const send = form.querySelector('button');
    const older = chat.querySelector('[data-chat-older]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const messages = new Map();
    let echo;
    let revoked = false;
    let syncing = false;
    let resync = false;
    let pending;
    let sending = false;
    let canSend = !input.disabled;

    const revoke = () => {
        revoked = true;
        canSend = false;
        send.disabled = true;
        input.disabled = true;
        messages.clear();
        list.replaceChildren();
        status.textContent = 'Akses chat berakhir. Muat ulang atau masuk kembali.';
        echo?.disconnect();
        clearInterval(accessCheck);
    };

    const render = (scrollToBottom) => {
        const nodes = [...messages.values()].sort((a, b) => a.id - b.id).map((message) => {
            const item = document.createElement('article');
            item.className = 'chat-bubble' + (String(message.sender_id) === chat.dataset.userId ? ' mine' : '');
            const heading = document.createElement('strong');
            heading.textContent = message.sender_name;
            const body = document.createElement('p');
            body.textContent = message.body;
            const time = document.createElement('small');
            time.textContent = new Intl.DateTimeFormat('id-ID', {timeZone: 'Asia/Jakarta', dateStyle: 'short', timeStyle: 'short'}).format(new Date(message.created_at)) + ' WIB';
            item.append(heading, body, time);
            return item;
        });
        if (!nodes.length) {
            const empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = 'Belum ada pesan. Mulai diskusi jadwal di sini.';
            nodes.push(empty);
        }
        list.replaceChildren(...nodes);
        if (scrollToBottom) list.scrollTop = list.scrollHeight;
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {...options, headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers}});
        if (response.status === 401 || response.status === 403 || response.status === 419) {
            revoke();
            throw new Error('Akses chat berubah. Muat ulang atau masuk kembali.');
        }
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Permintaan gagal. Coba lagi.');
        return data;
    };

    const sync = async (mode = 'new') => {
        if (revoked) return;
        if (syncing) {
            if (mode !== 'older') resync = true;
            return;
        }
        syncing = true;
        older.disabled = true;
        const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
        try {
            let more;
            do {
                const url = new URL(chat.dataset.url, window.location.origin);
                const ids = [...messages.keys()];
                if (mode === 'older' && ids.length) url.searchParams.set('before_id', Math.min(...ids));
                else if (mode === 'new' && ids.length) url.searchParams.set('after_id', Math.max(...ids));
                const data = await request(url);
                if (revoked) return;
                canSend = data.can_send;
                input.disabled = !canSend;
                send.disabled = sending || !canSend;
                data.messages.forEach((message) => messages.set(message.id, message));
                render(mode === 'initial' || (mode === 'new' && atBottom));
                if (mode !== 'new' || !ids.length) older.hidden = !data.has_more;
                more = mode === 'new' && ids.length && data.has_more;
            } while (more && !revoked);
        } catch (failure) {
            if (!revoked) error.textContent = 'Riwayat belum tersinkron: ' + failure.message;
        } finally {
            syncing = false;
            older.disabled = false;
            if (resync && !revoked) {
                resync = false;
                sync();
            }
        }
    };

    older.addEventListener('click', () => sync('older'));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (revoked || !canSend || send.disabled) return;
        const body = input.value.trim();
        if (!body) return;
        if (!pending || pending.body !== body) pending = {body, client_message_id: crypto.randomUUID()};
        sending = true;
        send.disabled = true;
        error.textContent = '';
        try {
            const data = await request(chat.dataset.url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(pending)});
            if (revoked) return;
            messages.set(data.message.id, data.message);
            render(true);
            input.value = '';
            pending = null;
            error.textContent = 'Pesan tersimpan.';
        } catch (failure) {
            error.textContent = 'Pesan belum terkonfirmasi: ' + failure.message + ' Kirim ulang untuk mencoba kembali.';
        } finally {
            sending = false;
            send.disabled = revoked || !canSend;
        }
    });

    // Event hanya memberi sinyal; isi pesan selalu diambil dengan pemeriksaan akses HTTP terbaru.
    const accessCheck = setInterval(() => sync(), 30000);
    sync('initial');
    if (import.meta.env.VITE_REVERB_APP_KEY) {
        window.Pusher = Pusher;
        echo = new Echo({broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080), wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https', enabledTransports: ['ws', 'wss'],
            authEndpoint: chat.dataset.authUrl, auth: {headers: {'X-CSRF-TOKEN': csrf}}});
        echo.private(chat.dataset.channel).subscribed(() => {
            status.textContent = 'Terhubung secara real time';
            sync();
        }).listen('.chat.message', () => sync()).error(() => {
            status.textContent = 'Koneksi belum terotorisasi. Memeriksa akses kembali…';
            sync();
        });
        echo.connector.pusher.connection.bind('state_change', ({current}) => {
            if (current !== 'connected' && !revoked) status.textContent = 'Koneksi terputus; pesan tersimpan dan riwayat akan disinkronkan kembali.';
        });
    } else {
        status.textContent = 'Koneksi real time belum dikonfigurasi. Riwayat tetap dapat dibaca.';
    }
    window.addEventListener('pagehide', () => { clearInterval(accessCheck); echo?.disconnect(); });
}


