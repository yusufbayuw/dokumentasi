<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): void {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'description' => $description,
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole()
                ? null
                : Str::limit((string) request()->userAgent(), 1000, ''),
        ]);
    }

    private function subjectLabel(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        foreach (['nama', 'name', 'address', 'title', 'label'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($subject) . ' #' . $subject->getKey();
    }
}
