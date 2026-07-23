<?php

namespace App\Models\Concerns;

use App\Services\AutoTranslator;

/**
 * Auto-fills the English translation of each $translatable attribute from
 * its Arabic value whenever the model is saved with English left empty.
 */
trait AutoTranslatesMissing
{
    protected static function bootAutoTranslatesMissing(): void
    {
        static::saving(function (self $model): void {
            $translator = app(AutoTranslator::class);

            foreach ($model->translatable as $field) {
                $translator->fillMissing($model, $field);
            }
        });
    }
}
