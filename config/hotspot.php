<?php

return [
    // Kredensial & target router MikroTik (isi di .env lokal — jangan di-commit)
    'host' => env('HOTSPOT_MT_HOST', '192.168.90.1'),
    'port' => (int) env('HOTSPOT_MT_PORT', 8728),
    'user' => env('HOTSPOT_MT_USER', ''),
    'pass' => env('HOTSPOT_MT_PASS', ''),

    // Nama address-list & komentar aturan firewall (konsisten dengan hasil-hermes)
    'list_block'     => 'blocklist',
    'comment_block'  => 'hasil-hermes-block',
    'comment_dns'    => 'hasil-hermes-dns-lock',
    'comment_dns2'   => 'hasil-hermes-dns-lock2',
    'log_prefix'     => 'hh-access',
    'comment_access' => 'hasil-hermes-access',
];