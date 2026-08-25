<x-filament-panels::page>
    @php
        $rombel = $this->getRombelRows();
        $mapel = $this->getSubjectColumns();
        $guru = $this->getTeacherOptions();
        $ringkas = $this->getRingkasan();
        $pratinjau = $this->getPratinjauSalin();
    @endphp

    <div class="asmt-matrix">
        {{-- Pemilih semester + ringkasan sisa pekerjaan --}}
        <section class="asmt-matrix__bar">
            <div class="asmt-matrix__field">
                <label for="mtx-semester">Semester</label>
                <select id="mtx-semester" wire:model.live="semesterId">
                    <option value="">— pilih semester —</option>
                    @foreach ($this->getSemesterOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="asmt-matrix__stats">
                <span><strong>{{ $ringkas['terisi'] }}</strong> / {{ $ringkas['total_sel'] }} sel terisi</span>
                @if ($ringkas['kosong'] > 0)
                    <span class="asmt-badge asmt-badge--warning">{{ $ringkas['kosong'] }} kosong</span>
                @else
                    <span class="asmt-badge asmt-badge--success">lengkap</span>
                @endif
                @if ($ringkas['rombel_tanpa_wali'] > 0)
                    <span class="asmt-badge asmt-badge--danger">{{ $ringkas['rombel_tanpa_wali'] }} rombel tanpa wali</span>
                @endif
            </div>

            <div class="asmt-matrix__tabs">
                <button type="button" wire:click="pilihTab('mengajar')" @class(['is-active' => $tab === 'mengajar'])>Mengajar</button>
                <button type="button" wire:click="pilihTab('wali')" @class(['is-active' => $tab === 'wali'])>Wali Kelas</button>
            </div>
        </section>

        {{-- Salin dari semester lain: pratinjau dulu, baru simpan --}}
        <section class="asmt-matrix__copy">
            <div class="asmt-matrix__field">
                <label for="mtx-sumber">Salin penugasan dari semester</label>
                <select id="mtx-sumber" wire:model.live="sumberSalinId">
                    <option value="">— pilih sumber —</option>
                    @foreach ($this->getSumberSalinOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($pratinjau)
                <p class="asmt-matrix__preview">
                    Akan disalin <strong>{{ $pratinjau['mengajar'] }}</strong> penugasan mengajar dan
                    <strong>{{ $pratinjau['wali'] }}</strong> wali kelas.
                    @if ($pratinjau['dilewati_guru_hilang'] > 0)
                        {{ $pratinjau['dilewati_guru_hilang'] }} baris dilewati karena gurunya sudah tidak ada.
                    @endif
                    @if ($pratinjau['tujuan_terisi'])
                        <span class="asmt-matrix__warn">
                            Semester tujuan sudah berisi penugasan — yang sama akan diperbarui, yang lain tetap.
                        </span>
                    @endif
                    Hasil salinan masih dapat diubah.
                </p>
            @endif

            <x-filament::button
                wire:click="salinDariSemester"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-o-document-duplicate"
                :disabled="blank($sumberSalinId)"
            >Salin</x-filament::button>
        </section>

        @if (blank($semesterId))
            <p class="asmt-matrix__empty">Pilih semester lebih dulu.</p>
        @elseif ($rombel === [])
            <p class="asmt-matrix__empty">Belum ada rombel aktif. Aktifkan rombel lebih dulu.</p>
        @elseif ($tab === 'mengajar' && $mapel === [])
            <p class="asmt-matrix__empty">Belum ada mata pelajaran aktif.</p>
        @elseif ($tab === 'mengajar')
            {{-- MATRIKS kelas x mapel: satu layar, bukan form satu-satu --}}
            <div class="asmt-matrix__scroll">
                <table class="asmt-matrix__table">
                    <thead>
                        <tr>
                            <th class="asmt-matrix__corner">Kelas</th>
                            @foreach ($mapel as $m)
                                <th title="{{ $m['kelompok'] }}">
                                    <span class="asmt-matrix__subject">{{ $m['nama'] }}</span>
                                    <span class="asmt-matrix__group">{{ $m['kelompok'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rombel as $r)
                            <tr>
                                <th class="asmt-matrix__rowhead">{{ $r['nama'] }}</th>
                                @foreach ($mapel as $m)
                                    <td @class(['is-empty' => blank($matriks[$r['id']][$m['id']] ?? null)])>
                                        <select wire:model="matriks.{{ $r['id'] }}.{{ $m['id'] }}">
                                            <option value="">—</option>
                                            @foreach ($guru as $gid => $gnama)
                                                <option value="{{ $gid }}">{{ $gnama }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="asmt-matrix__hint">
                Sel yang dikosongkan akan <strong>menonaktifkan</strong> penugasan, bukan menghapusnya —
                riwayat nilai yang menempel padanya tetap dapat dilacak.
            </p>
        @else
            {{-- Tab wali kelas: satu guru per rombel --}}
            <div class="asmt-matrix__wali">
                @foreach ($rombel as $r)
                    <div class="asmt-matrix__waliRow {{ blank($wali[$r['id']] ?? null) ? 'is-empty' : '' }}">
                        <span class="asmt-matrix__waliName">{{ $r['nama'] }}</span>
                        <select wire:model="wali.{{ $r['id'] }}">
                            <option value="">— belum ada wali —</option>
                            @foreach ($guru as $gid => $gnama)
                                <option value="{{ $gid }}">{{ $gnama }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        @endif

        @if (filled($semesterId) && $rombel !== [])
            <div class="asmt-matrix__actions">
                <x-filament::button wire:click="simpan" wire:loading.attr="disabled" icon="heroicon-o-check">
                    Simpan Penugasan
                </x-filament::button>
                <x-filament::button wire:click="muatData" wire:loading.attr="disabled" color="gray" icon="heroicon-o-arrow-path">
                    Batalkan Perubahan
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
