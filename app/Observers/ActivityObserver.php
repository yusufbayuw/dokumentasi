<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityObserver
{
    private const IGNORED = [
        'created_at',
        'updated_at',
        'last_checked_at',
        'last_seen_at',
        'last_latency_ms',
        'password',
        'username',
        'remember_token',
    ];

    public function created(Model $model): void
    {
        $values = $this->sanitize($model->getAttributes());

        app(ActivityLogger::class)->log(
            $this->action($model, 'created'),
            $model,
            [],
            $values,
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->sanitize($model->getChanges());

        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        app(ActivityLogger::class)->log(
            $this->action($model, 'updated'),
            $model,
            $this->sanitize($old),
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        app(ActivityLogger::class)->log(
            $this->action($model, 'deleted'),
            $model,
            $this->sanitize($model->getAttributes()),
        );
    }

    public function restored(Model $model): void
    {
        app(ActivityLogger::class)->log(
            $this->action($model, 'restored'),
            $model,
        );
    }

    private function sanitize(array $values): array
    {
        return Arr::except($values, self::IGNORED);
    }

    private function action(Model $model, string $event): string
    {
        return Str::snake(class_basename($model)) . '.' . $event;
    }
}
