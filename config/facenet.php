<?php

return [
    'python' => env('FACENET_PYTHON', 'python'),
    'python_path' => env('FACENET_PYTHONPATH', ''),
    'timeout' => (int) env('FACENET_TIMEOUT', 60),
    'model_path' => env('FACENET_MODEL_PATH', base_path('scripts/facenet-master/models/facenet_20180402_114759_vggface2.pth')),
];
