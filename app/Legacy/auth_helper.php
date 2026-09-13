<?php

use App\Legacy\ResponseSignal;

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function jsonResponse($data, $status = 200)
{
    throw new ResponseSignal($data, $status);
}

function requireAuth(): void
{
    if (! isset($_SESSION['user'])) {
        header('Location: ?page=login');
        exit;
    }
}

function isAdmin(): bool
{
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
function isPegawai(): bool
{
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'pegawai';
}
