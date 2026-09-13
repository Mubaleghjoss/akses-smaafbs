<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionSet;
use App\Filament\Pages\Assessment\QuestionBankBuilderPage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuestionSetController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeExamModule();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'subject' => ['required', 'string', 'max:100'],
            'exam_type' => ['required', 'in:ASTS,ASAS,ASAT,Lainnya'], 'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:Ganjil,Genap'], 'instructions' => ['nullable', 'string'],
        ]);
        $set = QuestionSet::create($data + ['teacher_id' => $request->user()->id, 'status' => 'draft']);
        return back()->with('exam_notice', "Paket {$set->title} dibuat.");
    }

    public function update(Request $request, QuestionSet $questionSet): RedirectResponse
    {
        $this->authorizeExamModule();
        $this->authorizeOwner($request, $questionSet);
        $questionSet->update($request->validate([
            'title' => ['required', 'string', 'max:255'], 'subject' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:draft,review,published'], 'instructions' => ['nullable', 'string'],
        ]));
        return back()->with('exam_notice', 'Paket diperbarui.');
    }

    public function addQuestion(Request $request, QuestionSet $questionSet): RedirectResponse
    {
        $this->authorizeExamModule();
        $this->authorizeOwner($request, $questionSet);
        $data = $request->validate([
            'type' => ['required', 'in:multiple_choice,multiple_response,true_false,essay'], 'prompt' => ['required', 'string'],
            'options_text' => ['nullable', 'string'], 'answer_key_text' => ['nullable', 'string'], 'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'cognitive_level' => ['required', 'in:HOTS,MOTS,LOTS'], 'explanation' => ['nullable', 'string'], 'rubric' => ['nullable', 'string'],
        ]);
        $questionSet->questions()->create([
            ...collect($data)->except(['options_text', 'answer_key_text'])->all(),
            'options' => $this->lines($data['options_text'] ?? ''), 'answer_key' => $this->lines($data['answer_key_text'] ?? ''),
            'position' => ((int) $questionSet->questions()->max('position')) + 1,
        ]);
        return back()->with('exam_notice', 'Butir soal ditambahkan.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $this->authorizeExamModule();
        $this->authorizeOwner($request, $question->questionSet);
        $data = $request->validate(['prompt' => ['required', 'string'], 'answer_key_text' => ['nullable', 'string'], 'weight' => ['required', 'numeric', 'min:0', 'max:100'], 'explanation' => ['nullable', 'string'], 'rubric' => ['nullable', 'string']]);
        $question->update([
            ...collect($data)->except('answer_key_text')->all(),
            'answer_key' => $this->lines($data['answer_key_text'] ?? ''),
        ]);
        return back()->with('exam_notice', 'Soal diperbarui.');
    }

    public function preview(Request $request, QuestionSet $questionSet, string $version = 'student'): Response
    {
        $this->authorizeExamModule();
        $this->authorizeOwner($request, $questionSet);
        abort_unless(in_array($version, ['student', 'teacher'], true), 404);
        $questionSet->load('questions', 'teacher');
        return Pdf::loadView('exam.pdf.question-set', compact('questionSet', 'version'))->setPaper('a4')->stream("{$questionSet->title}-{$version}.pdf");
    }

    private function authorizeExamModule(): void
    {
        abort_unless(QuestionBankBuilderPage::canAccess(), 403);
    }

    private function authorizeOwner(Request $request, QuestionSet $set): void
    {
        abort_unless($set->isOwnedBy($request->user()), 403);
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $text) ?: []), fn ($value) => $value !== ''));
    }
}
