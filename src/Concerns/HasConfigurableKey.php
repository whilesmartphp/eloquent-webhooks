<?php

namespace Whilesmart\Webhooks\Concerns;

use Illuminate\Support\Str;

trait HasConfigurableKey
{
    public static function bootHasConfigurableKey(): void
    {
        static::creating(function ($model) {
            if (config('webhooks.uuids', false) && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::orderedUuid();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return ! (bool) config('webhooks.uuids', false);
    }

    public function getKeyType(): string
    {
        return config('webhooks.uuids', false) ? 'string' : 'int';
    }
}
