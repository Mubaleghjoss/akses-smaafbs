<?php

namespace App\Http\Controllers;

use App\Models\Survei;
use App\Models\SurveiQuestion;
use App\Models\SurveiSubmission;
use App\Models\SurveiTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SurveiPublicController extends Controller
{
    public function show(string $token): View
    {
        $target = $this->resolveTarget($token);
        $survei = $target->survei;

        return view('survei.show', [
            'title' => $survei->title,
            'target' => $target,
            'survei' => $survei,
            'submission' => $target->submission,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $target = $this->resolveTarget($token);
        $survei = $target->survei;

        abort_if(! $survei->is_active, 404);
        abort_if($survei->opens_at && $survei->opens_at->isFuture(), 404);
        abort_if($survei->closes_at && $survei->closes_at->isPast(), 404);

        if ($target->submission) {
            return redirect()
                ->route('survei.public.show', $target->access_token)
                ->with('status', 'Jawaban survei ini sudah pernah dikirim.');
        }

        $questions = $survei->questions()->get();
        $validated = $request->validate($this->validationRules($questions), [], $this->validationAttributes($questions));

        $answers = [];
        foreach ($questions as $question) {
            $answers[(string) $question->getKey()] = data_get($validated, 'answers.'.$question->getKey());
        }

        SurveiSubmission::query()->create([
            'survei_id' => $survei->getKey(),
            'survei_target_id' => $target->getKey(),
            'answers' => $answers,
            'submitted_ip' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'submitted_at' => now(),
        ]);

        $target->markSubmitted();

        return redirect()
            ->route('survei.public.show', $target->access_token)
            ->with('status', 'Terima kasih. Jawaban survei berhasil dikirim.');
    }

    protected function resolveTarget(string $token): SurveiTarget
    {
        return SurveiTarget::query()
            ->where('access_token', $token)
            ->with([
                'survei' => fn ($query) => $query->with('questions'),
                'submission',
            ])
            ->firstOrFail();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SurveiQuestion>  $questions
     * @return array<string, mixed>
     */
    protected function validationRules($questions): array
    {
        $rules = [];

        foreach ($questions as $question) {
            $base = $question->is_required ? ['required'] : ['nullable'];

            $rules['answers.'.$question->getKey()] = match ($question->question_type) {
                SurveiQuestion::TYPE_LONG_TEXT => array_merge($base, ['string', 'max:4000']),
                SurveiQuestion::TYPE_SINGLE_CHOICE => array_merge($base, ['string', Rule::in($question->normalizedOptions())]),
                SurveiQuestion::TYPE_RATING => array_merge($base, ['integer', 'between:1,5']),
                default => array_merge($base, ['string', 'max:1000']),
            };
        }

        return $rules;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SurveiQuestion>  $questions
     * @return array<string, string>
     */
    protected function validationAttributes($questions): array
    {
        return $questions->mapWithKeys(fn (SurveiQuestion $question): array => [
            'answers.'.$question->getKey() => $question->prompt,
        ])->all();
    }
}
