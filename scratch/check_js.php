<?php
$code = file_get_contents('c:\laragon\www\Absen\resources\views\pages\qa_panel.blade.php');
preg_match('/<script>(.*?)<\/script>/s', $code, $matches);
if (isset($matches[1])) {
    // Strip PHP tags for JS validation
    $js = preg_replace('/<\?php.*?\?>/', '0', $matches[1]);
    file_put_contents('c:\laragon\www\Absen\scratch\qa_panel_test.js', $js);
    exec('node -c c:\laragon\www\Absen\scratch\qa_panel_test.js 2>&1', $out, $ret);
    echo implode("\n", $out);
} else {
    echo 'No script tag found.';
}
