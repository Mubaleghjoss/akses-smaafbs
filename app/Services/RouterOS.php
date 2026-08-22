<?php

namespace App\Services;

/**
 * RouterOS API Client — port dari hasil-hermes/libs/RouterOS.php (sudah teruji).
 * Protokol binary RouterOS API murni socket (fsockopen), tanpa dependensi eksternal.
 * Mendukung login token sesi (RouterOS 6.43+/7.x) dan login challenge MD5 (router lama).
 *
 * Contoh:
 *   $ros = new RouterOS('192.168.88.1', 8728, 'admin', 'rahasia');
 *   if ($ros->connect()) { $rows = $ros->command('/ip/hotspot/user/print'); $ros->close(); }
 */
class RouterOS
{
    private $socket = null;
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $lastError = '';
    private string $token = '';

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    /** Hubungkan + login. Return true jika berhasil. */
    public function connect(): bool
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if ($this->socket === false) {
            $this->lastError = "Tidak dapat terhubung ke {$this->host}:{$this->port} ({$errstr})";
            return false;
        }
        stream_set_timeout($this->socket, 10);
        $this->token = '';
        return $this->loginModern() || $this->loginChallenge();
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @$this->writeSentence(['/quit']);
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    // ---------- Perintah siap pakai ----------

    /** Info perangkat: identity, versi RouterOS, uptime. */
    public function systemInfo(): array
    {
        $res = $this->command('/system/resource/print');
        $info = ['identity' => '?', 'version' => '?', 'uptime' => '?', 'board-name' => '?'];
        if ($res['ok'] && isset($res['rows'][0])) {
            $row = $res['rows'][0];
            $info['version'] = $row['version'] ?? '?';
            $info['uptime'] = $row['uptime'] ?? '?';
            $info['board-name'] = $row['board-name'] ?? '?';
        }
        $id = $this->command('/system/identity/print');
        if ($id['ok'] && isset($id['rows'][0]['name'])) {
            $info['identity'] = $id['rows'][0]['name'];
        }
        return $info;
    }

    /** User hotspot yang sedang aktif login. */
    public function hotspotActive(): array
    {
        return $this->command('/ip/hotspot/active/print');
    }

    /** Semua akun hotspot di router. */
    public function hotspotUsers(): array
    {
        return $this->command('/ip/hotspot/user/print');
    }

    /** Tambah user hotspot di router. */
    public function hotspotUserAdd(string $name, string $password, string $profile, string $comment = '', ?string $limitUptime = null): array
    {
        $params = ['name' => $name, 'password' => $password, 'profile' => $profile, 'comment' => $comment];
        if ($limitUptime !== null && $limitUptime !== '') $params['limit-uptime'] = $limitUptime;
        return $this->command('/ip/hotspot/user/add', $params);
    }

    /** Update user hotspot (fields: nama, password, profile, comment, limit-uptime, dst). */
    public function hotspotUserSet(string $id, array $fields): array
    {
        $params = ['.id' => $id];
        foreach ($fields as $k => $v) {
            if ($v !== null && $v !== '') $params[$k] = $v;
        }
        return $this->command('/ip/hotspot/user/set', $params);
    }

    /** Hapus user hotspot. */
    public function hotspotUserRemove(string $id): array
    {
        return $this->command('/ip/hotspot/user/remove', ['.id' => $id]);
    }

    public function hotspotUserEnable(string $id): array
    {
        return $this->command('/ip/hotspot/user/enable', ['.id' => $id]);
    }

    public function hotspotUserDisable(string $id): array
    {
        return $this->command('/ip/hotspot/user/disable', ['.id' => $id]);
    }

    /** Semua profil hotspot (grup bandwidth). */
    public function hotspotProfilesAll(): array
    {
        $res = $this->command('/ip/hotspot/user/profile/print');
        return $res['ok'] ? $res['rows'] : [];
    }

    /** Tambah profil user hotspot (grup bandwidth) di router. */
    public function hotspotProfileAdd(string $name, ?string $rateLimit = null, ?int $sharedUsers = null): array
    {
        $params = ['name' => $name];
        if ($rateLimit !== null && $rateLimit !== '') $params['rate-limit'] = $rateLimit;
        if ($sharedUsers !== null && $sharedUsers > 0) $params['shared-users'] = (string) $sharedUsers;
        return $this->command('/ip/hotspot/user/profile/add', $params);
    }

    /** Update profil user hotspot di router. */
    public function hotspotProfileSet(string $id, array $fields): array
    {
        $params = ['.id' => $id];
        foreach ($fields as $k => $v) {
            if ($v !== null && $v !== '') $params[$k] = $v;
        }
        return $this->command('/ip/hotspot/user/profile/set', $params);
    }

    /** Hapus profil user hotspot di router. */
    public function hotspotProfileRemove(string $id): array
    {
        return $this->command('/ip/hotspot/user/profile/remove', ['.id' => $id]);
    }

    // ---------- Address-list & firewall (blokir situs) ----------

    /** Semua entri address-list (opsional filter per list). */
    public function addressListAll(string $list = ''): array
    {
        $params = $list !== '' ? ['?list' => $list] : [];
        return $this->command('/ip/firewall/address-list/print', $params);
    }

    public function addressListAdd(string $list, string $address): array
    {
        return $this->command('/ip/firewall/address-list/add', ['list' => $list, 'address' => $address]);
    }

    public function addressListRemove(string $id): array
    {
        return $this->command('/ip/firewall/address-list/remove', ['.id' => $id]);
    }

    /** Cari rule firewall filter berdasarkan komentar eksak. */
    public function firewallFind(string $comment): array
    {
        $res = $this->command('/ip/firewall/filter/print');
        if (!$res['ok']) return $res;
        $rows = array_values(array_filter($res['rows'], fn ($r) => ($r['comment'] ?? '') === $comment));
        return ['ok' => true, 'rows' => $rows];
    }

    /** Aturan: drop semua traffic menuju address-list (blokir situs). */
    public function firewallAddDropToList(string $listName, string $comment): array
    {
        return $this->command('/ip/firewall/filter/add', [
            'chain' => 'forward',
            'dst-address-list' => $listName,
            'action' => 'drop',
            'comment' => $comment,
        ]);
    }

    /** Kunci DNS: blokir DNS keluar (udp+tcp port 53). */
    public function firewallAddDnsLock(string $comment): array
    {
        $r = $this->command('/ip/firewall/filter/add', [
            'chain' => 'forward', 'protocol' => 'udp', 'dst-port' => '53',
            'action' => 'drop', 'comment' => $comment,
        ]);
        if (!$r['ok']) return $r;
        return $this->command('/ip/firewall/filter/add', [
            'chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '53',
            'action' => 'drop', 'comment' => $comment,
        ]);
    }

    /** Blokir jalur DNS bypass: DoT (853) + DoH publik (443 ke IP DoH). */
    public function firewallAddDnsLock2(string $comment): array
    {
        $specs = [
            ['chain' => 'forward', 'protocol' => 'udp', 'dst-port' => '853'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '853'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '8.8.8.8'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '8.8.4.4'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '1.1.1.1'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '1.0.0.1'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '9.9.9.9'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '149.112.112.112'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '208.67.222.222'],
            ['chain' => 'forward', 'protocol' => 'tcp', 'dst-port' => '443', 'dst-address' => '208.67.220.220'],
        ];
        $added = 0;
        $errors = [];
        foreach ($specs as $s) {
            $r = $this->command('/ip/firewall/filter/add', $s + [
                'action' => 'drop',
                'comment' => $comment,
            ]);
            if ($r['ok']) $added++;
            else $errors[] = $r['error'];
        }
        return ['added' => $added, 'errors' => $errors];
    }

    // ---------- Inti protokol ----------

    /**
     * Kirim perintah API.
     * $params: ['key' => 'value', ...]; kunci diawali '?' menjadi query filter.
     * Return: ['ok' => true, 'rows' => [...]] atau ['ok' => false, 'error' => 'pesan'].
     */
    public function command(string $cmd, array $params = []): array
    {
        $res = $this->execCommand($cmd, $params, false);
        if (!$res['ok'] && $this->token !== ''
            && stripos($res['error'], 'not logged in') !== false) {
            // Router mode token sesi (6.43-6.48) -> ulangi dengan =token=
            return $this->execCommand($cmd, $params, true);
        }
        return $res;
    }

    private function execCommand(string $cmd, array $params, bool $withToken): array
    {
        $words = [$cmd];
        if ($withToken && $this->token !== '') {
            $words[] = '=token=' . $this->token;
        }
        foreach ($params as $k => $v) {
            $words[] = $k !== '' && $k[0] === '?' ? $k . '=' . $v : '=' . $k . '=' . $v;
        }
        $this->writeSentence($words);

        $rows = [];
        while (true) {
            $sent = $this->readSentence();
            if ($sent === null) {
                return ['ok' => false, 'error' => $this->lastError !== '' ? $this->lastError : 'Koneksi terputus / timeout saat membaca respon.'];
            }
            $p = $this->parseSentence($sent);
            $t = $p['!'] ?? '';
            unset($p['!']);
            if ($t === 're') {
                $rows[] = $p;
            } elseif ($t === 'done') {
                return ['ok' => true, 'rows' => $rows];
            } elseif ($t === 'trap' || $t === 'fatal') {
                return ['ok' => false, 'error' => $p['message'] ?? 'Perintah ditolak router.'];
            }
        }
    }

    /** Login modern (RouterOS 6.43+ / 7.x): kredensial polos, simpan token sesi (=ret=). */
    private function loginModern(): bool
    {
        $this->writeSentence(['/login', '=name=' . $this->user, '=password=' . $this->pass]);
        $resp = $this->readSentence();
        if ($resp === null) return false;
        $p = $this->parseSentence($resp);
        if (($p['!'] ?? '') === 'done') return true;
        if (isset($p['ret'])) {
            $this->token = (string) $p['ret'];
            return true;
        }
        return false;
    }

    /** Login challenge MD5 (RouterOS < 6.43): hash(password + challenge). */
    private function loginChallenge(): bool
    {
        $this->writeSentence(['/login', '=name=' . $this->user]);
        $resp = $this->readSentence();
        if ($resp === null) return false;
        $p = $this->parseSentence($resp);
        $challenge = $p['ret'] ?? null;
        if ($challenge === null || $challenge === '') {
            $this->lastError = $p['message'] ?? 'Login gagal (tidak ada challenge).';
            return false;
        }
        $hash = md5("\x00" . $this->pass . pack('H*', (string) $challenge));
        $this->writeSentence(['/login', '=name=' . $this->user, '=response=00' . $hash]);
        $resp = $this->readSentence();
        if ($resp === null) return false;
        $p = $this->parseSentence($resp);
        if (($p['!'] ?? '') === 'done') return true;
        $this->lastError = $p['message'] ?? 'Login gagal.';
        return false;
    }

    // ---------- Encode/decode protokol ----------

    private function writeSentence(array $words): void
    {
        if ($this->socket === null) return;
        foreach ($words as $w) {
            $this->writeWord((string) $w);
        }
        $this->writeWord('');
    }

    private function writeWord(string $w): void
    {
        $len = strlen($w);
        fwrite($this->socket, $this->encodeLen($len) . $w);
    }

    private function readSentence(): ?array
    {
        if ($this->socket === null) return null;
        $words = [];
        while (true) {
            $w = $this->readWord();
            if ($w === null) return null;
            if ($w === '') break;
            $words[] = $w;
        }
        return $words;
    }

    private function readWord(): ?string
    {
        if ($this->socket === null) return null;
        $len = $this->readLen();
        if ($len === null) return null;
        if ($len === 0) return '';
        $w = stream_get_contents($this->socket, $len);
        if ($w === false || strlen($w) !== $len) return null;
        return $w;
    }

    private function readLen(): ?int
    {
        $b = fread($this->socket, 1);
        if ($b === false || $b === '') return null;
        $b = ord($b);
        if ($b < 128) return $b;
        if ($b === 128) return 0;
        if ($b === 129) {
            $v = fread($this->socket, 1);
            return $v === false ? null : ord($v) + 128;
        }
        if ($b === 130) {
            $v = fread($this->socket, 2);
            if ($v === false || strlen($v) !== 2) return null;
            return unpack('n', $v)[1] + 128;
        }
        if ($b === 131) {
            $v = fread($this->socket, 4);
            if ($v === false || strlen($v) !== 4) return null;
            return unpack('N', $v)[1] + 128;
        }
        return null;
    }

    private function encodeLen(int $len): string
    {
        if ($len < 128) return chr($len);
        if ($len < 16384) return chr(($len >> 8) + 129) . chr($len & 0xFF);
        if ($len < 0x400000) {
            return chr(($len >> 16) + 130) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        }
        return chr(131) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }

    private function parseSentence(array $words): array
    {
        $out = [];
        foreach ($words as $w) {
            if ($w === '' || $w[0] !== '=') {
                // Kata jenis kalimat ('!re', '!done', '!trap', ...) atau nilai polos
                if (!isset($out['!'])) {
                    $out['!'] = ltrim($w, '!');
                } else {
                    $out[] = $w;
                }
                continue;
            }
            // Kata atribut: format '=key=value' — key di antara '=' pertama dan kedua
            $pos = strpos($w, '=', 1);
            if ($pos === false) {
                $out[substr($w, 1)] = ''; // '=key' tanpa nilai
            } else {
                $out[substr($w, 1, $pos - 1)] = substr($w, $pos + 1);
            }
        }
        return $out;
    }
}