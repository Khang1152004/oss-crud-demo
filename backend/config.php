<?php
// Đổi key này trước khi deploy
define('API_KEY', 'DEMO_KEY_123456');

// Cho phép FE gọi API (CORS)
define('CORS_ALLOW_ORIGIN', '*');

// File lưu dữ liệu
define('TASKS_FILE', __DIR__ . '/data/tasks.json');
