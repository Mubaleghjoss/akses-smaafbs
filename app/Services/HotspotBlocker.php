<?php

namespace App\Services;

use App\Models\BlockedDomain;

/**
 * Blokir situs via address-list MikroTik + aturan firewall + kesehatan perangkat.
 * Database lokal (blocked_domains) = sumber kebenaran; disinkronkan ke router.
 */
class HotspotBlocker
{
    private ?RouterOS $ros = null;

    private string $lastError = '';

    public function __construct(?RouterOS $ros = null)
    {
        $this->ros = $ros;
    }

    public function error(): string
    {
        return $this->lastError;
    }

    public function ros(): ?RouterOS
    {
        return $this->ros;
    }

    /** Nama domain yang sudah ada di address-list blocklist router. */
    public function routerNames(): array
    {
        if ($this->ros === null) {
            return [];
        }
        $names = [];
        $res = $this->ros->addressListAll(config('hotspot.list_block'));
        if ($res['ok']) {
            foreach ($res['rows'] as $r) {
                if (($r['dynamic'] ?? 'false') !== 'true' && ($r['address'] ?? '') !== '') {
                    $names[(string) $r['address']] = true;
                }
            }
        }

        return $names;
    }

    /** Push satu domain ke router (dengan verifikasi nyata masuk + retry 3x). */
    public function pushDomain(string $domain): bool
    {
        if ($this->ros === null) {
            return false;
        }
        for ($i = 0; $i < 3; $i++) {
            $r = $this->ros->addressListAdd((string) config('hotspot.list_block'), $domain);
            if (! $r['ok']) {
                return false;
            }
            $names = $this->routerNames();
            if (isset($names[$domain])) {
                return true;
            }
        }

        return false;
    }

    /** Hapus domain dari router: entri nama + semua IP hasil resolve-nya. */
    public function removeDomain(string $domain): int
    {
        if ($this->ros === null) {
            return 0;
        }
        $removed = 0;
        $res = $this->ros->addressListAll((string) config('hotspot.list_block'));
        if (! $res['ok']) {
            return 0;
        }
        foreach ($res['rows'] as $r) {
            if (($r['address'] ?? '') === $domain || ($r['comment'] ?? '') === $domain) {
                $del = $this->ros->addressListRemove((string) ($r['.id'] ?? ''));
                if ($del['ok']) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /** Sinkronkan SEMUA domain DB -> router. Return statistik. */
    public function syncAll(): array
    {
        $names = $this->routerNames();
        $added = $failed = $exists = 0;
        foreach (BlockedDomain::query()->orderBy('domain')->get() as $d) {
            if (isset($names[$d->domain])) {
                $exists++;
                continue;
            }
            if ($this->pushDomain($d->domain)) {
                $added++;
            } else {
                $failed++;
            }
        }

        return ['exists' => $exists, 'added' => $added, 'failed' => $failed];
    }

    /** Pastikan aturan firewall (drop ke blocklist / kunci DNS) ada. Idempoten. */
    public function ensureRule(string $comment, string $kind): bool
    {
        if ($this->ros === null) {
            return false;
        }
        $r = $this->ros->firewallFind($comment);
        if ($r['ok'] && count($r['rows']) > 0) {
            return true;
        }
        $r = $kind === 'drop'
            ? $this->ros->firewallAddDropToList((string) config('hotspot.list_block'), $comment)
            : $this->ros->firewallAddDnsLock($comment);

        return $r['ok'];
    }

    /** Status kunci DNS anti-bypass (DoT/DoH). */
    public function dnsLock2Active(): bool
    {
        if ($this->ros === null) {
            return false;
        }
        $r = $this->ros->firewallFind((string) config('hotspot.comment_dns2'));

        return $r['ok'] && count($r['rows']) > 0;
    }

    /** Aktifkan kunci DNS anti-bypass (idempoten). */
    public function enableDnsLock2(): array
    {
        if ($this->ros === null) {
            return ['ok' => false, 'msg' => 'Router tidak terhubung'];
        }
        if ($this->dnsLock2Active()) {
            return ['ok' => true, 'msg' => 'Sudah aktif (DoT 853 + DoH publik diblokir).'];
        }
        $r = $this->ros->firewallAddDnsLock2((string) config('hotspot.comment_dns2'));

        return ['ok' => empty($r['errors']), 'msg' => empty($r['errors']) ? "Aktif ({$r['added']} rule)." : implode(' | ', $r['errors'])];
    }

    // ---------- Kesehatan & status ----------

    /** Resource utama router: CPU, RAM, storage, uptime. */
    public function health(): array
    {
        $out = [
            'ok' => false, 'identity' => '?', 'version' => '?', 'uptime' => '?',
            'cpu' => 0, 'ram_used' => 0, 'ram_total' => 0, 'ram_pct' => 0,
            'hdd_used' => 0, 'hdd_total' => 0, 'hdd_pct' => 0,
            'active' => 0, 'blocked' => 0,
        ];
        if ($this->ros === null) {
            $out['error'] = $this->lastError ?: 'Router tidak terhubung';

            return $out;
        }
        $res = $this->ros->command('/system/resource/print');
        if (! $res['ok'] || ! isset($res['rows'][0])) {
            $out['error'] = $res['error'] ?? 'Gagal baca resource';

            return $out;
        }
        $row = $res['rows'][0];
        $memTotal = (int) ($row['total-memory'] ?? 0);
        $memFree = (int) ($row['free-memory'] ?? 0);
        $hddTotal = (int) ($row['total-hdd-space'] ?? 0);
        $hddFree = (int) ($row['free-hdd-space'] ?? 0);
        $out = [
            'ok' => true,
            'identity' => (string) ($row['identity'] ?? '?'),
            'version' => (string) ($row['version'] ?? '?'),
            'uptime' => (string) ($row['uptime'] ?? '?'),
            'cpu' => (int) ($row['cpu-load'] ?? 0),
            'ram_used' => max(0, $memTotal - $memFree),
            'ram_total' => $memTotal,
            'ram_pct' => $memTotal > 0 ? (int) round(($memTotal - $memFree) * 100 / $memTotal) : 0,
            'hdd_used' => max(0, $hddTotal - $hddFree),
            'hdd_total' => $hddTotal,
            'hdd_pct' => $hddTotal > 0 ? (int) round(($hddTotal - $hddFree) * 100 / $hddTotal) : 0,
            'active' => count($this->ros->hotspotActive()['rows'] ?? []),
            'blocked' => count($this->routerNames()),
        ];

        return $out;
    }

    /** Rate trafik realtime per interface (maks 15 interface berjalan). */
    public function trafficRates(): array
    {
        if ($this->ros === null) {
            return [];
        }
        $if = $this->ros->command('/interface/print');
        if (! $if['ok']) {
            return [];
        }
        $out = [];
        $n = 0;
        foreach ($if['rows'] as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || ($row['running'] ?? 'false') !== 'true' || ($row['disabled'] ?? 'false') === 'true') {
                continue;
            }
            $mt = $this->ros->command('/interface/monitor-traffic', ['once' => 'yes', 'interface' => $name]);
            $out[] = [
                'name' => $name,
                'type' => (string) ($row['type'] ?? ''),
                'rx' => $mt['ok'] ? (int) ($mt['rows'][0]['rx-bits-per-second'] ?? 0) : 0,
                'tx' => $mt['ok'] ? (int) ($mt['rows'][0]['tx-bits-per-second'] ?? 0) : 0,
                'rx_total' => (int) ($row['rx-byte'] ?? 0),
                'tx_total' => (int) ($row['tx-byte'] ?? 0),
            ];
            if (++$n >= 15) {
                break;
            }
        }

        return $out;
    }
}