<?php
$files = array_merge(
    glob("resources/views/livewire/*.blade.php"),
    glob("resources/views/livewire/partials/*.blade.php"),
    glob("resources/views/components/notification-bell.blade.php"),
    glob("app/Livewire/*.php")
);
$strings = [];
foreach ($files as $f) {
    $content = file_get_contents($f);
    preg_match_all('/__\(\'((?:[^\'\\\\]|\\\\.)*)\'/', $content, $m);
    foreach ($m[1] as $s) {
        $strings[stripslashes($s)][] = $f;
    }
    preg_match_all('/__\("((?:[^"\\\\]|\\\\.)*)"/', $content, $m2);
    foreach ($m2[1] as $s) {
        $strings[stripslashes($s)][] = $f;
    }
}
$ar = json_decode(file_get_contents("lang/ar.json"), true);
$missing = [];
foreach ($strings as $s => $srcFiles) {
    if ($s === '') continue;
    if (!array_key_exists($s, $ar)) {
        $missing[$s] = $srcFiles;
    }
}
ksort($missing);
echo "Total unique strings found: " . count($strings) . "\n";
echo "Missing from ar.json: " . count($missing) . "\n\n";
foreach ($missing as $s => $srcFiles) {
    echo "- " . $s . "  [" . implode(', ', array_unique($srcFiles)) . "]\n";
}
