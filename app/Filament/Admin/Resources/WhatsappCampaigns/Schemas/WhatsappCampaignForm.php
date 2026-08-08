<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Schemas;

use App\Models\Section;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\WhatsappCampaign;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WhatsappCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('target_type')
                            ->label(__('Send To'))
                            ->options(WhatsappCampaign::targetTypeOptions())
                            ->default(WhatsappCampaign::TARGET_GROUP)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('target_id', null);
                                $set('student_group_id', null);
                            })
                            ->columnSpanFull(),
                        Select::make('student_group_id')
                            ->label(__('Student Group'))
                            ->relationship('studentGroup', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('target_type') === WhatsappCampaign::TARGET_GROUP)
                            ->visible(fn (Get $get): bool => $get('target_type') === WhatsappCampaign::TARGET_GROUP)
                            ->columnSpanFull(),
                        Select::make('target_id')
                            ->label(fn (Get $get): string => WhatsappCampaign::targetTypeOptions()[$get('target_type')] ?? __('Target'))
                            ->options(fn (Get $get): array => self::targetOptions($get('target_type')))
                            ->searchable()
                            ->required(fn (Get $get): bool => in_array($get('target_type'), [
                                WhatsappCampaign::TARGET_SECTION,
                                WhatsappCampaign::TARGET_SUBJECT,
                                WhatsappCampaign::TARGET_TRAINER,
                            ], true))
                            ->visible(fn (Get $get): bool => in_array($get('target_type'), [
                                WhatsappCampaign::TARGET_SECTION,
                                WhatsappCampaign::TARGET_SUBJECT,
                                WhatsappCampaign::TARGET_TRAINER,
                            ], true))
                            ->columnSpanFull(),
                        Textarea::make('message')
                            ->label(__('Message'))
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<int, string> */
    protected static function targetOptions(?string $targetType): array
    {
        $locale = app()->getLocale();

        return match ($targetType) {
            WhatsappCampaign::TARGET_SECTION => Section::query()
                ->with('subject')
                ->orderByDesc('id')
                ->get()
                ->mapWithKeys(fn (Section $s) => [
                    $s->id => $s->name.($s->subject
                        ? ' — '.$s->subject->getTranslation('name', $locale, false)
                        : ''),
                ])
                ->all(),
            WhatsappCampaign::TARGET_SUBJECT => Subject::query()
                ->get()
                ->mapWithKeys(fn (Subject $s) => [$s->id => $s->getTranslation('name', $locale, false)])
                ->all(),
            WhatsappCampaign::TARGET_TRAINER => Trainer::query()
                ->get()
                ->mapWithKeys(fn (Trainer $t) => [$t->id => $t->getTranslation('name', $locale, false)])
                ->all(),
            default => [],
        };
    }
}
