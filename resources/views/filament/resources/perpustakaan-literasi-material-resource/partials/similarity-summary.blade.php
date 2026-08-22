<div class="literasi-similarity-summary">
    <div class="literasi-similarity-summary__intro">
        <strong>Ringkasan pemeriksaan kemiripan</strong>
        <p>
            Setiap jawaban hanya menyimpan satu pembanding terdahulu yang paling kuat mulai
            {{ number_format((float) $summary['threshold'], 0, ',', '.') }}%. Hasil ini merupakan indikasi untuk ditinjau guru, bukan vonis plagiasi.
        </p>
    </div>

    <dl class="literasi-similarity-summary__grid">
        <div>
            <dt>Siswa terindikasi</dt>
            <dd>{{ number_format((int) $summary['students'], 0, ',', '.') }}</dd>
        </div>
        <div>
            <dt>Jawaban terindikasi</dt>
            <dd>{{ number_format((int) $summary['answers'], 0, ',', '.') }}</dd>
        </div>
        <div class="is-warning">
            <dt>Belum ditinjau</dt>
            <dd>{{ number_format((int) $summary['suspected'], 0, ',', '.') }}</dd>
        </div>
        <div class="is-success">
            <dt>Aman</dt>
            <dd>{{ number_format((int) $summary['cleared'], 0, ',', '.') }}</dd>
        </div>
        <div class="is-danger">
            <dt>Dikonfirmasi</dt>
            <dd>{{ number_format((int) $summary['confirmed'], 0, ',', '.') }}</dd>
        </div>
    </dl>
</div>
