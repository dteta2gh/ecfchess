<?php
require_once 'db.php';
error_log("TEST LOG FROM GAME.PHP - TIME: " . date('Y-m-d H:i:s'));
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);  // still log to error log

header('Content-Type: application/json; charset=utf-8');

// === READ INPUT ===
$rawInput = file_get_contents('php://input');
$data     = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = [];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
	case 'GET':
		if (isset($_GET['last_game'])) {
			$stmt = $pdo->query("SELECT id, fen, status FROM games WHERE status = 'active' ORDER BY id DESC LIMIT 1");
			$game = $stmt->fetch();

		if ($game) {
			echo json_encode([
				'success' => true,
				'game_id' => $game['id'],
				'fen'     => $game['fen'],
				'status'  => $game['status']
			]);
		} else {
			echo json_encode([
				'success' => false,
				'message' => 'No active game found'
			]);
		}
		exit;
		}
}	

// Load history list
if (isset($_GET['history'])) {
	error_log("history requested - block entered");

	// Force immediate output to test
	echo '{"debug": "History block reached"}';

try {
	//error_log("Trying DB query");
	$stmt = $pdo->query("SELECT id, created_at, status FROM games ORDER BY id DESC LIMIT 20");
	$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
	//error_log("Query done - " . count($games) . " games");

	// Clean and output real JSON
	ob_clean(); // clear any buffered garbage
	echo json_encode([
		'success' => true,
		'games' => $games
	], JSON_PRETTY_PRINT);
	} catch (Exception $e) {
	//error_log("History DB error: " . $e->getMessage());
	ob_clean();
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => 'Database error: ' . $e->getMessage()
	], JSON_PRETTY_PRINT);
	}
	exit;
}

// Load specific game by ID
if (isset($_GET['game_id'])) {
	$id = (int)$_GET['game_id'];
	//error_log("game_id requested: $id");
	try {
		$stmt = $pdo->prepare("SELECT id, fen, status FROM games WHERE id = ?");
		$stmt->execute([$id]);
		$game = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($game) {
			echo json_encode([
				'success' => true,
				'game_id' => $game['id'],
				'fen'     => $game['fen'],
				'status'  => $game['status']
			], JSON_PRETTY_PRINT);
		} else {
			echo json_encode(['success' => false, 'message' => 'Game not found']);
		}
	} catch (Exception $e) {
		//error_log("game_id query failed: " . $e->getMessage());
		http_response_code(500);
		echo json_encode(['error' => 'Database error']);
	}
	exit;
}

// === NEW GAME BLOCK ===
if (isset($data['new_game']) && $data['new_game']) {

    require_once 'db.php';

    $initialFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    try {
        $stmt = $pdo->prepare("INSERT INTO games (fen, status) VALUES (?, 'active')");
        $stmt->execute([$initialFen]);

        $gameId = $pdo->lastInsertId();

        echo json_encode([
            'success'  => true,
            'game_id'  => $gameId,
            'fen'      => $initialFen,
            'status'   => 'active',
            'message'  => 'New game created'
        ], JSON_PRETTY_PRINT);

        exit;
    }
    catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error'   => 'Database error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// === MOVE HANDLING ===
$game_id   = isset($data['game_id'])   ? (int) $data['game_id']   : 0;
$from      = isset($data['from'])      ? trim($data['from'])      : '';
$to        = isset($data['to'])        ? trim($data['to'])        : '';
$promotion = $data['promotion']        ?? null;

if ($game_id < 1 || $from === '' || $to === '') {
    http_response_code(400);
    echo json_encode([
        'error'         => 'Invalid move request',
        'details'       => 'game_id, from and to are required',
        'received_data' => $data,
        'parsed'        => [
            'game_id'   => $game_id,
            'from'      => $from,
            'to'        => $to,
            'promotion' => $promotion
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Load current game state
require_once 'db.php';

$stmt = $pdo->prepare("SELECT fen, status FROM games WHERE id = ? AND status = 'active'");
$stmt->execute([$game_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode([
        'error'   => 'Game not found or already finished',
        'game_id' => $game_id
    ], JSON_PRETTY_PRINT);
    exit;
}

$currentFen = $row['fen'];

// === SERVER-SIDE VALIDATION ===
require_once __DIR__ . '/ChessValidator.php';

try {
    $chess = new ChessValidator($currentFen);

    $moveResult = $chess->move([
        'from'      => $from,
        'to'        => $to,
        'promotion' => $promotion
    ]);

    if ($moveResult === null) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Illegal move',
            'from'  => $from,
            'to'    => $to,
            'promotion' => $promotion
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $newFen = $chess->fen();

    // Basic status update (you can expand this)
    $status = 'active';

    $stmt = $pdo->prepare("
        UPDATE games 
        SET fen = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$newFen, $status, $game_id]);

    echo json_encode([
        'success' => true,
        'fen'     => $newFen,
        'status'  => $status
    ], JSON_PRETTY_PRINT);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Server validation error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}