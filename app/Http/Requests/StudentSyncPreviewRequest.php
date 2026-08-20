<?php

namespace App\Http\Requests;

use App\Support\StudentSync\StudentSyncPreviewService;
use Closure;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StudentSyncPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxBatch = max(1, (int) config('student_sync.security.max_batch', 250));
        $scalar = static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_scalar($value)) {
                $fail("The {$attribute} field must be a scalar value or null.");
            }
        };

        return [
            'payload_checksum' => ['required', 'string', 'size:64', 'regex:/\A[0-9a-f]{64}\z/i'],
            'students' => ['required', 'array', 'min:1', 'max:'.$maxBatch],
            'students.*' => ['required', 'array:source_id,identity,fields,source_checksum,context'],
            'students.*.source_id' => ['required', 'integer', 'min:1'],
            'students.*.identity' => ['required', 'array'],
            'students.*.identity.*' => [$scalar],
            'students.*.fields' => ['required', 'array'],
            'students.*.fields.*' => [$scalar],
            'students.*.source_checksum' => ['required', 'string', 'size:64', 'regex:/\A[0-9a-f]{64}\z/i'],
            'students.*.context' => ['sometimes', 'nullable', 'array'],
            'students.*.context.*' => [$scalar],
        ];
    }

    protected function failedValidation(ValidatorContract $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('students') || $validator->errors()->has('payload_checksum')) {
                return;
            }

            $students = $this->input('students');
            $submitted = $this->input('payload_checksum');

            if (! is_array($students) || ! is_string($submitted)) {
                return;
            }

            $computed = StudentSyncPreviewService::payloadChecksum($students);

            if (! hash_equals($computed, strtolower($submitted))) {
                $validator->errors()->add('payload_checksum', 'The payload checksum does not match the submitted students.');
            }
        }];
    }
}
