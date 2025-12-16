<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . CORS_ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function json_out($data, $code=200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function require_key() {
  $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
  if ($key !== API_KEY) json_out(['ok'=>false,'error'=>'Unauthorized'], 401);
}

function read_store() {
  if (!file_exists(TASKS_FILE)) {
    $init = ['next_id'=>1,'tasks'=>[]];
    file_put_contents(TASKS_FILE, json_encode($init));
  }
  $raw = file_get_contents(TASKS_FILE);
  $data = json_decode($raw, true);
  if (!is_array($data) || !isset($data['tasks'])) {
    $data = ['next_id'=>1,'tasks'=>[]];
  }
  return $data;
}

function write_store($data) {
  $ok = file_put_contents(TASKS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
  if ($ok === false) json_out(['ok'=>false,'error'=>'Write failed'], 500);
}

function get_json_body() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

$action = $_GET['action'] ?? '';

if ($action === 'ping') {
  json_out([
    'ok' => true,
    'service' => 'PHP Tasks API',
    'time' => date('Y-m-d H:i:s'),
    'php' => phpversion()
  ]);
}

// Các action dưới yêu cầu API key
require_key();

if ($action === 'list') {
  $store = read_store();
  // sort newest first
  usort($store['tasks'], fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
  json_out(['ok'=>true,'tasks'=>$store['tasks']]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = get_json_body();
  $title = trim($body['title'] ?? '');
  $priority = strtoupper(trim($body['priority'] ?? 'MED'));
  $validP = ['LOW','MED','HIGH'];
  if ($title === '') json_out(['ok'=>false,'error'=>'Title is required'], 400);
  if (!in_array($priority, $validP, true)) $priority = 'MED';

  $store = read_store();
  $id = $store['next_id'];

  $task = [
    'id' => $id,
    'title' => mb_substr($title, 0, 80),
    'priority' => $priority,
    'done' => false,
    'created_at' => gmdate('c'),
    'updated_at' => gmdate('c')
  ];

  $store['next_id'] = $id + 1;
  $store['tasks'][] = $task;
  write_store($store);

  json_out(['ok'=>true,'task'=>$task], 201);
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = get_json_body();
  $id = intval($body['id'] ?? 0);
  if ($id <= 0) json_out(['ok'=>false,'error'=>'Invalid id'], 400);

  $store = read_store();
  $found = false;

  foreach ($store['tasks'] as &$t) {
    if ($t['id'] === $id) {
      $t['done'] = !$t['done'];
      $t['updated_at'] = gmdate('c');
      $found = true;
      $task = $t;
      break;
    }
  }

  if (!$found) json_out(['ok'=>false,'error'=>'Not found'], 404);
  write_store($store);
  json_out(['ok'=>true,'task'=>$task]);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = get_json_body();
  $id = intval($body['id'] ?? 0);
  $title = trim($body['title'] ?? '');
  $priority = strtoupper(trim($body['priority'] ?? 'MED'));
  $validP = ['LOW','MED','HIGH'];

  if ($id <= 0) json_out(['ok'=>false,'error'=>'Invalid id'], 400);
  if ($title === '') json_out(['ok'=>false,'error'=>'Title is required'], 400);
  if (!in_array($priority, $validP, true)) $priority = 'MED';

  $store = read_store();
  $found = false;

  foreach ($store['tasks'] as &$t) {
    if ($t['id'] === $id) {
      $t['title'] = mb_substr($title, 0, 80);
      $t['priority'] = $priority;
      $t['updated_at'] = gmdate('c');
      $found = true;
      $task = $t;
      break;
    }
  }

  if (!$found) json_out(['ok'=>false,'error'=>'Not found'], 404);
  write_store($store);
  json_out(['ok'=>true,'task'=>$task]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = get_json_body();
  $id = intval($body['id'] ?? 0);
  if ($id <= 0) json_out(['ok'=>false,'error'=>'Invalid id'], 400);

  $store = read_store();
  $before = count($store['tasks']);
  $store['tasks'] = array_values(array_filter($store['tasks'], fn($t) => $t['id'] !== $id));
  $after = count($store['tasks']);

  if ($after === $before) json_out(['ok'=>false,'error'=>'Not found'], 404);

  write_store($store);
  json_out(['ok'=>true,'deleted_id'=>$id]);
}

if ($action === 'stats') {
  $store = read_store();
  $total = count($store['tasks']);
  $done = count(array_filter($store['tasks'], fn($t)=>$t['done']));
  $high = count(array_filter($store['tasks'], fn($t)=>$t['priority']==='HIGH'));
  json_out([
    'ok'=>true,
    'stats'=>[
      'total'=>$total,
      'done'=>$done,
      'todo'=>$total-$done,
      'high_priority'=>$high
    ]
  ]);
}

json_out(['ok'=>false,'error'=>'Unknown action'], 404);
