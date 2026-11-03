<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // tighten later

// --- config (use your cPanel MySQL values) ---
$host = 'localhost';
$db   = 'YOUR_DB_NAME';
$user = 'YOUR_DB_USER';
$pass = 'YOUR_DB_PASSWORD';
$table = 'YOUR_EVENTS_TABLE'; // change to real table name

$apiKey = 'CHANGE_ME_LONG_RANDOM'; // require ?key= or header
$given  = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!hash_equals($apiKey, (string)$given)) {
  http_response_code(401);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

$since = $_GET['since'] ?? null; // optional ISO/datetime for incremental

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Adjust column names to match your MySQL schema
  $sql = "SELECT
            event_id,
            title,
            location,
            event_date,
            last_updated
          FROM `$table`";
  $params = [];
  if ($since) {
    $sql .= " WHERE last_updated > :since";
    $params[':since'] = $since;
  }
  $sql .= " ORDER BY last_updated DESC LIMIT 5000";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'id'           => $r['event_id'],
      'event_id'     => $r['event_id'],
      'title'        => $r['title'],
      'location'     => $r['location'],
      'event_date'   => $r['event_date'],
      'last_updated' => $r['last_updated'],
    ];
  }
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'server_error']);
  // error_log($e->getMessage());
}
