<?php
$env = parse_ini_file('.env');

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
    putenv("$key=$value");
}
