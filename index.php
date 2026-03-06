<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>ECFCHESS</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">   
  <!--
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chessboard-js/1.0.0/chessboard-1.0.0.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/chess.js/1.0.0-beta.6/chess.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/chessboard-js/1.0.0/chessboard-1.0.0.min.js"></script>
  -->
  
  <!-- Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/chess.js/0.12.0/chess.min.js"></script>
  <link  rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@chrisoakman/chessboardjs@1.0.0/dist/chessboard-1.0.0.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/@chrisoakman/chessboardjs@1.0.0/dist/chessboard-1.0.0.min.js"></script>
  
  
  <style>
    body { font-family: Arial, sans-serif; background: #222; color: #fff; text-align: center; }
    #board { width: 400px; margin: 20px auto; }
    #status { font-size: 1.2em; margin: 10px; }
    #moveList { margin: 20px; }
    #clock { font-size: 1.5em; margin: 10px; }
    #evalBar { width: 400px; height: 20px; margin: 10px auto; background: linear-gradient(to right, #000 50%, #fff 50%); }
    table { width: 80%; margin: 20px auto; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 8px; }
    th { background: #444; }
    button { padding: 8px 16px; margin: 5px; cursor: pointer; }
  </style>
  <style>
  .page-container {
  display: flex;
  flex-direction: column;
  height: 100vh; /* Full viewport height */
}

.top-half {
  display: flex;
  flex-direction: row;
  height: 60vh; /* Half viewport height */
}

.col-25 {
  width: 25%;
  /* background-color: #f0f0f0; */
  border: 1px solid #ccc;
  text-align: center;
}

.col-50 {
  width: 50%;
  /* background-color: #e0e0e0; */
  border: 1px solid #ccc;
  text-align: center;
}

.bottom-half {
  width: 100%;
  /* height: 40vh; */
  /* height: 100vh; */
  /* background-color: #d0d0d0; */
  border: 1px solid #ccc;
  text-align: center;
}   
  </style>
</head>
<body>

<h1>ECFCHESS</h1>

<!-- <div id="board"></div> -->
<!-- <div id="status">Waiting for new game…</div> -->
<!-- <div id="clock">White: 10:00 | Black: 10:00</div> -->
<!-- <div id="evalBar"></div> -->
<!-- <div id="moveList">Moves: <ol id="moves"></ol></div> -->

<!--
<button id="newGame">New Game</button>
<button id="flip">Flip Board</button>
<button id="exportPgn">Export PGN</button>
<button id="exportFen">Export FEN</button>
-->

<!--
<br><br>
<input id="importInput" type="text" placeholder="Paste FEN or PGN" style="width: 400px;">
<button id="importBtn">Import</button>

<h2>Game History</h2>
<table>
	<thead>
		<tr>
		<th>ID</th>
		<th>Started</th>
		<th>Status</th>
		<th>Actions</th>
		</tr>
	</thead>
	<tbody id="historyBody"></tbody>
</table>
<button id="refreshHistory">Refresh History</button>
-->

<div class="page-container">
	<!-- Top Half with 3 Columns -->
	<div class="top-half">
		<div class="col-25"><div id="clock">White: 10:00 <br> Black: 10:00</div></div>
		<div class="col-50"><div id="board"></div></div>
		<div class="col-25"><div id="moveList">Moves: <ol id="moves"></ol></div></div>
	</div>
	<!-- Bottom Half -->
	<div class="bottom-half">
		<div id="evalBar"></div>
		<div id="status">Waiting for new game…</div>
		<div id="status"></div>
		<!-- <div id="moveList">Moves: <ol id="moves"></ol></div> -->
		<button id="newGame">New Game</button>
		<button id="flip">Flip Board</button>
		<button id="exportPgn">Export PGN</button>
		<button id="exportFen">Export FEN</button>
		<br><br>
		<input id="importInput" type="text" placeholder="Paste FEN or PGN" style="width: 400px;">
		<button id="importBtn">Import</button>

		<h2>Game History</h2>
		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>Started</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody id="historyBody"></tbody>
		</table>
		<button id="refreshHistory">Refresh History</button>
</div>   

<script>
// Global objects
let board = null;
let game  = new Chess();
let gameId = null;

const statusEl = document.getElementById('status');

const cfg = {
  draggable: true,
  position: 'start',
  onDragStart: onDragStart,
  onDrop: onDrop,
  onSnapEnd: onSnapEnd,
  pieceTheme: 'https://chessboardjs.com/img/chesspieces/wikipedia/{piece}.png'
};

board = Chessboard('board', cfg);

function loadHistory() {
	fetch('game.php?history=true')
		.then(r => r.json())
		.then(data => {
			const tbody = document.getElementById('historyBody');
			tbody.innerHTML = '';

			if (!data.success || data.games.length === 0) {
			tbody.innerHTML = '<tr><td colspan="4">No games found</td></tr>';
			return;
			}

			data.games.forEach(game => {
			const row = document.createElement('tr');
			row.innerHTML = `
			  <td>${game.id}</td>
			  <td>${new Date(game.created_at).toLocaleString()}</td>
			  <td>${game.status}</td>
			  <td>
				<button onclick="loadGame(${game.id})">Load</button>
				<button onclick="deleteGame(${game.id})">Delete</button>
			  </td>
			`;
			tbody.appendChild(row);
		});
	})
	.catch(err => console.error('History error:', err));
}

// Load a specific game by ID when "Load" button is clicked
function loadGame(id) {
	fetch(`game.php?game_id=${id}`)
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(data => {
			if (data.success) {
				// Update global gameId and load the FEN
				gameId = data.game_id;
				game.load(data.fen);
				board.position(data.fen);
				updateStatus();
				updateMoveList();
				// Optional: show success message
				document.getElementById('status').innerHTML += `<br>Loaded game ID: ${gameId} (${data.status})`;
			} else {
				alert(data.message || 'Game not found');
			}
		})
		.catch(error => {
			console.error('Load game error:', error);
			alert('Failed to load game: ' + error.message);
		});
}

function onDragStart(source, piece) {
  if (game.game_over()) return false;
  if ((game.turn() === 'w' && piece.search(/^b/) !== -1) ||
      (game.turn() === 'b' && piece.search(/^w/) !== -1)) {
    return false;
  }
}

function onDrop(source, target) {
  // Safeguard
  if (!source || !target || source === target) {
    return 'snapback';
  }

  let promotion = undefined;
  const piece = game.get(source);

  if (piece && piece.type === 'p') {
    if ((piece.color === 'w' && target[1] === '8') ||
        (piece.color === 'b' && target[1] === '1')) {
      promotion = 'q';  // auto-queen for testing; replace with prompt later if desired
    }
  }

  const move = game.move({
    from: source,
    to: target,
    promotion: promotion
  });

  if (move === null) {
    return 'snapback';
  }

	// Update UI optimistically
	updateStatus();
	updateMoveList();
  
	// ------------------------------------------------
	// ??? Put the console.log HERE ???
	console.log('Attempting to send:', {
		game_id: gameId,
		from: source,
		to: target,
		promotion: promotion
	});
   // ???????????????????????????????????????????????

  // Only send to server if client accepted the move
  if (gameId == null || gameId === undefined) {
        console.warn('No game ID set - cannot send move to server');
        alert("No active game. Click 'New Game' first.");
        game.undo();
        board.position(game.fen());
        return;
    }

    console.log('Attempting to send:', {
        game_id: gameId,
        from: source,
        to: target,
        promotion: promotion
    });

  console.log('Sending to server:', { game_id: gameId, from: source, to: target, promotion });

	fetch('game.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        game_id: gameId,
        from: source,
        to: target,
        promotion: promotion
    })
})
.then(r => {
    console.log('Server status:', r.status);

    // Try JSON parse first — works for both success (200) and error (400) responses
    return r.json()
        .then(data => {
            if (!r.ok) {
                // Server sent JSON error (e.g. {"error": "Illegal move"})
                throw new Error(data.error || `Server rejected (status ${r.status})`);
            }
            return data; // success case
        })
        .catch(jsonErr => {
            // If JSON parse fails (very rare now), fall back to text
            console.warn('JSON parse failed on response:', jsonErr);
            return r.text().then(text => {
                throw new Error('Server returned invalid response: ' + (text.substring(0, 150) || 'no content'));
            });
        });
})
.then(data => {
    console.log('Server accepted move:', data);
    // Optional: sync board with server FEN
    // if (data.fen) {
    //     game.load(data.fen);
    //     board.position(data.fen);
    // }
})
.catch(err => {
    console.error('Move error:', err);
    alert('Move failed: ' + err.message);
    game.undo();
    board.position(game.fen());
    updateStatus();
	updateMoveList();
});
}

function onSnapEnd() {
  board.position(game.fen());
}

function updateMoveList() {
	moves.innerHTML = '';
	game.history().forEach((move, i) => {
	const li = document.createElement('li');
	li.textContent = `${Math.floor(i/2)+1}. ${move}`;
	moves.appendChild(li);
	});
}

function sendMove(from, to) {;
	fetch('game.php', { 
	method: 'POST',
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify({ game_id: gameId, from, to })
	})
	.then(r => r.json())
	.then(data => {
	if (data.success) {
	  game.load(data.fen);
	  board.position(data.fen);
	  updateStatus(data.status);
	} else {
	  alert(data.error || 'Invalid move');
	  game.undo();
	  board.position(game.fen());
	}
	})
	.catch(err => {
	alert('Server error: ' + err);
	game.undo();
	board.position(game.fen());
	});
}

function updateStatus() {
  let status = game.turn() === 'w' ? 'White'  + ' to move' : 'Black' + ' to move';
  if (game.in_check()) status += ' (in check)';
  if (game.in_checkmate()) status = 'Checkmate - ' + (game.turn() === 'w' ? 'Black' : 'White') + ' wins!';
  if (game.in_draw()) status = 'Draw';
  statusEl.innerHTML = status;
}

// exportPGN, exportFEN, importGame
function exportPgn() {
    fetch('game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ export: 'pgn', game_id: gameId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const blob = new Blob([data.pgn], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `chess_game.pgn`;
            a.click();
            URL.revokeObjectURL(url);
        } else {
            alert(data.error || 'PGN export failed');
        }
    })
    .catch(err => alert('PGN export error: ' + err.message));
}

function exportFen() {
	const fen = game.fen();
	const blob = new Blob([fen], { type: 'text/plain' });
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url;
	a.download = 'chess_game.fen';
	a.click();
	URL.revokeObjectURL(url);
}

function importGame() {
	const input = document.getElementById('importInput').value.trim();
	if (input) {
		fetch('game.php?import=true', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ input: input, game_id: gameId })
		})
		.then(r => r.json())
		.then(data => {
			if (data.success) {
				game.load(data.fen);
				board.position(data.fen);
				updateStatus();
				alert('Imported successfully');
			} else {
				alert('Import failed: ' + data.message);
			}
		});
	}
}

document.getElementById('importBtn').addEventListener('click', importGame);
document.getElementById('exportPgn').addEventListener('click', exportPgn);
document.getElementById('exportFen').addEventListener('click', exportFen);
document.getElementById('newGame').addEventListener('click', () => {
  fetch('game.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ new_game: true })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      alert(data.error);
      return;
    }
    gameId = data.game_id;
    game.reset();
    board.position('start');
    updateStatus();
	updateMoveList();
    statusEl.innerHTML += `<br>Game ID: ${gameId}`;
  })
  .catch(err => console.error(err));
});

document.getElementById('flip').addEventListener('click', () => board.flip());

// Auto-load last active game on page load
/* window.addEventListener('load', () => {
    fetch('game.php?last_game=true')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                gameId = data.game_id;
                game.load(data.fen);
                board.position(data.fen);
                updateStatus();
                document.getElementById('status').innerHTML += `<br>Loaded last game - ID: ${gameId}`;
            } else {
                document.getElementById('status').innerHTML += '<br>No active game - click New Game';
            }
        })
        .catch(err => console.error('Load last game failed:', err));
}); */
// Auto-load last active game when page opens
window.addEventListener('load', () => {
	loadHistory();
    fetch('game.php?last_game=true', {
        method: 'GET'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            gameId = data.game_id;
            game.load(data.fen);
            board.position(data.fen);
            updateStatus();
			updateMoveList();
            statusEl.innerHTML += `<br>Last game loaded - ID: ${gameId}`;
        } else {
            console.log('No previous active game found');
            statusEl.innerHTML += '<br>No previous game - click New Game';
        }
    })
    .catch(err => {
        console.error('Failed to load last game:', err);
        statusEl.innerHTML += '<br>Error loading previous game';
    });
});
</script>

</body>
</html>