<?php

$content = file_get_contents('resources/views/partials/_scripts_app.blade.php');
$content = preg_replace('/@if\s*\(.*?\)\s*/', '', $content);
$content = preg_replace('/@else\s*/', '', $content);
$content = preg_replace('/@endif\s*/', '', $content);
$content = preg_replace('/@elseif\s*\(.*?\)\s*/', '', $content);
file_put_contents('/tmp/test.js', $content);
echo "Done\n";
