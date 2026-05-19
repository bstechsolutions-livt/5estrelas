<?php

namespace App\Traits;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => self::auditEvent($m, 'created'));
        static::updated(fn ($m) => self::auditEvent($m, 'updated'));
        static::deleted(fn ($m) => self::auditEvent($m, 'deleted'));
    }

    protected static function auditEvent(Model $model, string $action): void
    {
        $events = property_exists($model, 'auditableEvents') ? $model->auditableEvents : ['created', 'updated', 'deleted'];
        if (!in_array($action, $events, true)) {
            return;
        }

        $module = property_exists($model, 'auditableModule') ? $model->auditableModule : 'sistema';
        $prefix = property_exists($model, 'auditableEventPrefix')
            ? $model->auditableEventPrefix
            : strtolower(class_basename($model)) . 's';

        $event = "{$module}.{$prefix}.{$action}";

        $except = property_exists($model, 'auditableExcept') ? $model->auditableExcept : [];
        $hidden = array_merge(['password', 'remember_token', 'created_at', 'updated_at'], $except);

        $old = null;
        $new = null;

        if ($action === 'created') {
            $new = Arr::except($model->getAttributes(), $hidden);
        } elseif ($action === 'updated') {
            $changes = Arr::except($model->getChanges(), $hidden);
            if (empty($changes)) {
                return;
            }
            $original = Arr::except($model->getOriginal(), $hidden);
            $new = $changes;
            $old = Arr::only($original, array_keys($changes));
        } elseif ($action === 'deleted') {
            $old = Arr::except($model->getOriginal(), $hidden);
        }

        $description = method_exists($model, 'auditDescription')
            ? $model->auditDescription($action)
            : null;

        AuditLogger::log(
            event: $event,
            module: $module,
            description: $description,
            auditable: $model,
            oldValues: $old,
            newValues: $new,
        );
    }
}
