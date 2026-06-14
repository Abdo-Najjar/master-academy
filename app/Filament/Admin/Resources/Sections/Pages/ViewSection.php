<?php

namespace App\Filament\Admin\Resources\Sections\Pages;

use App\Filament\Admin\Resources\Sections\SectionResource;
use App\Models\Section;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewSection extends ViewRecord
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('notifyWhatsApp')
                ->label(__('Notify via WhatsApp'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->schema([
                    Select::make('contacts')
                        ->label(__('Send to'))
                        ->options([
                            'all' => __('Students and Parents'),
                            'parents' => __('Parents only'),
                            'students' => __('Students only'),
                        ])
                        ->default('all')
                        ->required(),
                    Textarea::make('message')
                        ->label(__('Message'))
                        ->rows(4)
                        ->required()
                        ->placeholder(__('Type your message…')),
                ])
                ->action(function (Section $record, array $data): StreamedResponse {
                    $allContacts = WhatsAppService::sectionContacts($record, $data['message']);

                    $contacts = match ($data['contacts']) {
                        'parents' => array_filter($allContacts, fn ($c) => $c['type'] === 'parent'),
                        'students' => array_filter($allContacts, fn ($c) => $c['type'] === 'student'),
                        default => $allContacts,
                    };

                    $sectionName = $record->getTranslation('name', app()->getLocale(), false);
                    $html = self::buildContactsHtml($sectionName, array_values($contacts), $data['message']);

                    return response()->streamDownload(
                        fn () => print($html),
                        'whatsapp-'.\Str::slug($sectionName).'.html',
                        ['Content-Type' => 'text/html; charset=utf-8']
                    );
                }),
            EditAction::make(),
        ];
    }

    private static function buildContactsHtml(string $sectionName, array $contacts, string $message): string
    {
        $count = count($contacts);
        $rows = '';
        foreach ($contacts as $i => $c) {
            $name = htmlspecialchars($c['name']);
            $phone = htmlspecialchars($c['phone']);
            $url = htmlspecialchars($c['url']);
            $num = $i + 1;
            $rows .= "<tr><td>{$num}</td><td>{$name}</td><td>{$phone}</td><td><a href=\"{$url}\" target=\"_blank\">&#128172; WhatsApp</a></td></tr>";
        }

        $msg = htmlspecialchars($message);
        $sec = htmlspecialchars($sectionName);
        $date = now()->format('Y-m-d H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WhatsApp Contacts – {$sec}</title>
<style>
  body{font-family:sans-serif;padding:1.5rem;background:#f8fafc;direction:rtl;}
  h1{font-size:1.4rem;margin-bottom:.25rem;}
  .meta{color:#64748b;font-size:.875rem;margin-bottom:1.5rem;}
  .msg{background:#fff;border:1px solid #e2e8f0;border-radius:.5rem;padding:1rem;margin-bottom:1.5rem;white-space:pre-wrap;font-size:.9rem;}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:.75rem;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);}
  th{background:#f1f5f9;padding:.75rem 1rem;text-align:right;font-size:.8rem;color:#475569;font-weight:600;}
  td{padding:.75rem 1rem;border-top:1px solid #f1f5f9;font-size:.875rem;vertical-align:middle;}
  a{color:#16a34a;font-weight:600;text-decoration:none;}
  a:hover{text-decoration:underline;}
  .count{display:inline-block;background:#dcfce7;color:#15803d;padding:.2rem .7rem;border-radius:9999px;font-weight:600;font-size:.85rem;}
</style>
</head>
<body>
<h1>{$sec}</h1>
<p class="meta">{$date} · <span class="count">{$count} جهة اتصال</span></p>
<div class="msg">{$msg}</div>
<table>
<thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>رابط</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
</body>
</html>
HTML;
    }
}
