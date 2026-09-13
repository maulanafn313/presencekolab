<?php

return [
    // Existing deployment accepts optional images/landmarks. Enable only after
    // accounts have server embeddings and device verification has passed UAT.
    'require_face_proof' => env('ATTENDANCE_REQUIRE_FACE_PROOF', false),
    'face_proof_seconds' => 120,
];
