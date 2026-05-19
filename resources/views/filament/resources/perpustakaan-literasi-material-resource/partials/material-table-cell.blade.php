@php
    /** @var \App\Models\PerpustakaanLiterasiMaterial $record */
    $record = $getRecord();

    $questions = (int) ($record->questions_count ?? 0);
    $responses = (int) ($record->responses_count ?? 0);
    $gradedResponses = (int) ($record->graded_responses_count ?? 0);
    $indications = (int) ($record->similarity_matches_count ?? 0);
    $confirmed = (int) ($record->confirmed_similarity_matches_count ?? 0);
@endphp

<div class="literasi-material-cell">
    <div class="literasi-material-cell__title">
        {{ $record->title }}
    </div>

    <div class="literasi-material-cell__meta" aria-label="Ringkasan materi">
        <span class="literasi-material-cell__pill">{{ number_format($questions, 0, ',', '.') }} soal</span>
        <span class="literasi-material-cell__pill">{{ number_format($responses, 0, ',', '.') }} responden</span>
        <span class="literasi-material-cell__pill">{{ number_format($gradedResponses, 0, ',', '.') }}/{{ number_format($responses, 0, ',', '.') }} dinilai</span>
        <span class="literasi-material-cell__pill {{ $indications > 0 ? 'is-danger' : '' }}">{{ number_format($indications, 0, ',', '.') }} indikasi</span>
        <span class="literasi-material-cell__pill {{ $confirmed > 0 ? 'is-danger' : '' }}">{{ number_format($confirmed, 0, ',', '.') }} konfirm</span>
    </div>
</div>
