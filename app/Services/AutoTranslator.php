<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * Fills a missing English translation of a translatable attribute from its
 * Arabic value using Google Translate, so editors only have to type Arabic.
 */
class AutoTranslator
{
    private const SOURCE_LOCALE = 'ar';

    private const TARGET_LOCALE = 'en';

    public function fillMissing(Model $model, string $field): void
    {
        $translations = $model->getTranslations($field);

        $source = trim((string) ($translations[self::SOURCE_LOCALE] ?? ''));
        $target = trim((string) ($translations[self::TARGET_LOCALE] ?? ''));

        if ($source === '' || $target !== '') {
            return;
        }

        try {
            $translated = (new GoogleTranslate(self::TARGET_LOCALE))
                ->setSource(self::SOURCE_LOCALE)
                ->translate($source);
        } catch (Throwable) {
            return;
        }

        $translated = trim((string) $translated);

        if ($translated !== '') {
            $model->setTranslation($field, self::TARGET_LOCALE, $translated);
        }
    }
}
