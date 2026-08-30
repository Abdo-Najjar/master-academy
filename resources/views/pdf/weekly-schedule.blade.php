<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ __('Weekly Schedule') }}</title>
<style>
  /* A wall timetable, not a data dump: rooms down the side in walking order,
     the seven days across, one week per landscape page. */
  @page { margin: 10mm 8mm 12mm; }
  body { font-family: tajawal, sans-serif; font-size: 8.5pt; color: #1e293b; }

  /* mPDF never applies a descendant CSS rule to an `img`, so the mark is sized
     with attributes on the tag itself — left alone it draws at 512px. */
  table.masthead { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
  table.masthead td { vertical-align: middle; padding: 0 0 6pt; border-bottom: 2pt solid #b91c1c; }
  td.mark { width: 34pt; }
  .brand { font-size: 14pt; font-weight: bold; color: #0f172a; }
  .doc { text-align: left; font-size: 8.5pt; color: #475569; }
  .doc .week { display: block; font-size: 11pt; font-weight: bold; color: #b91c1c; margin-bottom: 1pt; }
  .doc .scope { display: block; font-size: 7.5pt; color: #64748b; margin-top: 2pt; }

  table.week { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.week th, table.week td { border: 0.6pt solid #dbe2ea; vertical-align: top; padding: 3pt; }

  table.week thead th {
    background: #f1f5f9; color: #334155; font-size: 8pt; font-weight: bold;
    text-align: center; padding: 5pt 3pt; border-color: #cbd5e1;
  }
  th.dayhead .w { display: block; }
  th.dayhead .d { display: block; font-size: 7pt; font-weight: normal; color: #64748b; margin-top: 1pt; }

  /* The room column is the spine of the sheet, so it gets the accent. */
  th.roomhead { width: 11%; }
  td.room {
    width: 11%; background: #f8fafc; text-align: center; vertical-align: middle;
    font-weight: bold; font-size: 9pt; color: #0f172a;
  }

  table.week tbody tr { page-break-inside: avoid; }

  /* One lesson: time on its own line, then what and who. A rule in the
     subject's colour so a room's day reads as a stack of blocks rather than a
     paragraph. mPDF mirrors physical borders inside an RTL block, so
     `border-left` is what actually lands on the leading edge here. */
  .lesson {
    border-left: 2.5pt solid #94a3b8;
    background: #f2f6fb;
    padding: 2.5pt 4pt;
    margin-bottom: 2.5pt;
  }
  .lesson:last-child { margin-bottom: 0; }
  .lesson .t { font-weight: bold; font-size: 8pt; color: #0f172a; }
  .lesson .n { display: block; font-size: 7.5pt; color: #1e293b; }
  .lesson .m { display: block; font-size: 7pt; color: #64748b; }
  .empty { color: #cbd5e1; font-size: 8pt; text-align: center; }

  .printed { margin-top: 8pt; font-size: 7pt; color: #a8b3c0; }
</style>
</head>
<body>
@foreach ($weeks as $index => $week)
  {{-- Each week is its own sheet of paper: that is what "print the schedule"
       means to whoever pins it up. --}}
  <div @if (! $loop->first) style="page-break-before: always;" @endif>
    <table class="masthead">
      <tr>
        @if ($logo)
          <td class="mark"><img src="{{ $logo }}" alt="" width="30" height="30"></td>
        @endif
        <td><span class="brand">{{ \App\Support\AppBranding::appName() }}</span></td>
        <td class="doc">
          <span class="week">
            {{ $week['start']->translatedFormat('j F') }} – {{ $week['end']->translatedFormat('j F Y') }}
          </span>
          {{ __('Weekly Schedule') }}
          @if ($filters !== [])
            <span class="scope">{{ implode(' · ', $filters) }}</span>
          @endif
        </td>
      </tr>
    </table>

    <table class="week">
      <thead>
        <tr>
          <th class="roomhead">{{ __('Room') }}</th>
          @foreach ($week['days'] as $day)
            <th class="dayhead">
              <span class="w">{{ $dayLabels[strtolower($day->format('l'))] ?? $day->translatedFormat('l') }}</span>
              <span class="d">{{ $day->format('d/m') }}</span>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($week['rooms'] as $room)
          <tr>
            <td class="room">{{ $room['label'] }}</td>
            @foreach ($week['days'] as $day)
              @php $lessons = $room['cells'][$day->toDateString()] ?? []; @endphp
              <td>
                @forelse ($lessons as $lesson)
                  <div class="lesson" @if ($lesson['color']) style="border-right-color: {{ $lesson['color'] }};" @endif>
                    <span class="t">{{ $lesson['time'] }}</span>
                    <span class="n">{{ $lesson['section'] }}</span>
                    <span class="m">
                      {{ collect([$lesson['subject'], $lesson['trainer'], $lesson['branch']])->filter()->implode(' · ') ?: '—' }}
                    </span>
                  </div>
                @empty
                  <div class="empty">—</div>
                @endforelse
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="printed">{{ $printedAt->translatedFormat('d F Y H:i') }}</div>
  </div>
@endforeach
</body>
</html>
