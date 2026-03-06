<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);   // still logs to file
// ChessValidator.php - Complete server-side chess move validator for PHP (no dependencies)

class ChessValidator {
    private $board = [];  // 8x8 array, pieces as 'P','N','B','R','Q','K','p', etc. or null
    private $turn = 'w';
    private $castling = 'KQkq';
    private $enPassant = '-';
    private $halfmoveClock = 0;
    private $fullmoveNumber = 1;

    private $kingPos = ['w' => [0, 4], 'b' => [7, 4]];  // default start

    public function __construct($fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1') {
        $this->loadFen($fen);
    }

    public function loadFen($fen) {
        $parts = explode(' ', $fen);
        if (count($parts) < 6) throw new Exception('Invalid FEN');

        $ranks = explode('/', $parts[0]);
        $this->board = array_fill(0, 8, array_fill(0, 8, null));
        $this->kingPos = ['w' => null, 'b' => null];

        for ($rank = 7; $rank >= 0; $rank--) {
            $file = 0;
            foreach (str_split($ranks[7 - $rank]) as $char) {
                if (is_numeric($char)) {
                    $file += (int)$char;
                } else {
                    $this->board[$rank][$file] = $char;
                    $color = ctype_upper($char) ? 'w' : 'b';
                    if (strtoupper($char) === 'K') {
                        $this->kingPos[$color] = [$rank, $file];
                    }
                    $file++;
                }
            }
        }

        $this->turn = $parts[1] === 'w' || $parts[1] === 'b' ? $parts[1] : 'w';
        $this->castling = $parts[2] !== '-' ? $parts[2] : '';
        $this->enPassant = $parts[3] !== '-' ? $parts[3] : '-';
        $this->halfmoveClock = (int)$parts[4];
        $this->fullmoveNumber = (int)$parts[5];
    }

    public function fen() {
        $ranks = [];
        for ($rank = 7; $rank >= 0; $rank--) {
            $empty = 0;
            $row = '';
            for ($file = 0; $file < 8; $file++) {
                $p = $this->board[$rank][$file];
                if ($p === null) {
                    $empty++;
                } else {
                    if ($empty) $row .= $empty;
                    $row .= $p;
                    $empty = 0;
                }
            }
            if ($empty) $row .= $empty;
            $ranks[] = $row;
        }

        $castling = $this->castling ?: '-';
        $enPassant = $this->enPassant ?: '-';

        return implode('/', $ranks) . " $this->turn $castling $enPassant $this->halfmoveClock $this->fullmoveNumber";
    }

    public function move(array $move) {
		$fromStr = $move['from'] ?? '';
		$toStr   = $move['to'] ?? '';

		$from = $this->algToCoord($fromStr);
		$to   = $this->algToCoord($toStr);
		
		error_log("Requested move: from={$move['from']} parsed as " . json_encode($from));
		error_log("Requested move: to={$move['to']} parsed as " . json_encode($to));

		if (!$from || !$to) return null;

		[$fr, $ff] = $from;
		[$tr, $tf] = $to;
		
		
		$pieceOnFrom = $this->board[$fr][$ff] ?? 'empty';
		$pieceType = $pieceOnFrom === 'empty' ? 'empty' : strtoupper($pieceOnFrom);

		error_log("Square e8 board content: rank=$fr file=$ff piece='$pieceOnFrom' type='$pieceType'");
		
		error_log("Coordinates: from rank=$fr file=$ff to rank=$tr file=$tf");

        $piece = $this->board[$fr][$ff];
        if (!$piece) return null;

        $color = ctype_upper($piece) ? 'w' : 'b';
        if ($color !== $this->turn) return null;

        $moves = $this->getLegalMovesForSquare($fr, $ff);

        foreach ($moves as $m) {
            if ($m['to'][0] === $tr && $m['to'][1] === $tf) {
                // Promotion
                $promotion = $move['promotion'] ?? 'q';
                $promotionPiece = $color === 'w' ? strtoupper($promotion) : strtolower($promotion);

                // Make a copy of state to simulate
                $backupBoard = $this->board;
                $backupEnPassant = $this->enPassant;
                $backupCastling = $this->castling;
                $backupHalfmove = $this->halfmoveClock;

                // Execute the move
                $captured = $this->board[$tr][$tf];
                $this->board[$tr][$tf] = $piece;
                $this->board[$fr][$ff] = null;

                // Special moves
                $this->updateSpecialMoves($fr, $ff, $tr, $tf, $color, $piece, $promotionPiece, $captured);

                // Update state
                $this->turn = $this->turn === 'w' ? 'b' : 'w';
                $this->halfmoveClock = ($captured || strtoupper($piece) === 'P') ? 0 : $this->halfmoveClock + 1;
                if ($this->turn === 'w') $this->fullmoveNumber++;

                if (strtoupper($piece) === 'K') {
                    $this->kingPos[$color] = [$tr, $tf];
                }

                // Check if king is in check after move
                if ($this->isInCheck($color)) {
                    // Undo move
                    $this->board = $backupBoard;
                    $this->enPassant = $backupEnPassant;
                    $this->castling = $backupCastling;
                    $this->halfmoveClock = $backupHalfmove;
                    return null;
                }

                return $move;
            }
        }

		// ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
		// ADD THE DEBUG LINE **EXACTLY HERE**, right before the final return null;
		error_log("Move rejected: from=$fromStr to=$toStr color=$color turn=$this->turn moves_count=" . count($moves));


        return null;
    }

    private function updateSpecialMoves($fr, $ff, $tr, $tf, $color, $piece, $promotionPiece, $captured) {
        // Promotion
        $promoRank = $color === 'w' ? 7 : 0;
        if (strtoupper($piece) === 'P' && $tr === $promoRank) {
            $this->board[$tr][$tf] = $promotionPiece;
        }

        // En passant
        if (strtoupper($piece) === 'P' && $tf != $ff && $this->board[$tr][$tf] === null && $this->enPassant === $this->coordToAlg($tr, $tf)) {
            $pawnRank = $color === 'w' ? $tr - 1 : $tr + 1;
            $this->board[$pawnRank][$tf] = null;
        }

        // Update en passant square
        $this->enPassant = '-';
        if (strtoupper($piece) === 'P' && abs($tr - $fr) === 2) {
            $epRank = $color === 'w' ? 2 : 5;
            $this->enPassant = $this->coordToAlg($epRank, $ff);
        }

        // Castling
        if (strtoupper($piece) === 'K' && abs($tf - $ff) === 2) {
            // King side
            if ($tf === 6) {
                $rookFromFile = 7;
                $rookToFile = 5;
            } else { // Queen side
                $rookFromFile = 0;
                $rookToFile = 3;
            }
            $this->board[$tr][$rookToFile] = $this->board[$tr][$rookFromFile];
            $this->board[$tr][$rookFromFile] = null;
        }

        // Update castling rights
        if (strtoupper($piece) === 'K') {
            $this->castling = str_replace($color === 'w' ? 'KQ' : 'kq', '', $this->castling);
        } else if (strtoupper($piece) === 'R') {
            if ($color === 'w') {
                if ($ff === 0) $this->castling = str_replace('Q', '', $this->castling);
                if ($ff === 7) $this->castling = str_replace('K', '', $this->castling);
            } else {
                if ($ff === 0) $this->castling = str_replace('q', '', $this->castling);
                if ($ff === 7) $this->castling = str_replace('k', '', $this->castling);
            }
        }

        // If rook captured, update opponent castling
        if ($captured && strtoupper($captured) === 'R') {
            $oppColor = $color === 'w' ? 'b' : 'w';
            if ($tr === ($oppColor === 'w' ? 0 : 7)) {
                if ($tf === 0) $this->castling = str_replace($oppColor === 'w' ? 'Q' : 'q', '', $this->castling);
                if ($tf === 7) $this->castling = str_replace($oppColor === 'w' ? 'K' : 'k', '', $this->castling);
            }
        }
    }

    private function isInCheck($color) {
    $oppColor = $color === 'w' ? 'b' : 'w';
    $kingPos = $this->kingPos[$color];
    if (!$kingPos) return false;

    [$kr, $kf] = $kingPos;
    error_log("isInCheck($color): king at $kr,$kf (opp=$oppColor)");

    $attacked = false;

    for ($r = 0; $r < 8; $r++) {
        for ($f = 0; $f < 8; $f++) {
            $p = $this->board[$r][$f];
            if ($p && ctype_upper($p) === ctype_upper($oppColor[0])) {
                $attacks = $this->getPseudoLegalMoves($r, $f, true);
                foreach ($attacks as $a) {
                    if ($a['to'][0] === $kr && $a['to'][1] === $kf) {
                        error_log("King attacked from $r,$f by $p");
                        $attacked = true;
                        break 2;
                    }
                }
            }
        }
    }

    error_log("isInCheck($color) result: " . ($attacked ? 'YES' : 'NO'));
    return $attacked;
}

    private function getLegalMovesForSquare($rank, $file) {
    $pseudo = $this->getPseudoLegalMoves($rank, $file, false);
    $legal = [];

    $piece = $this->board[$rank][$file];
    $color = ctype_upper($piece) ? 'w' : 'b';

    error_log("Filtering " . count($pseudo) . " pseudo moves for $color $piece at $rank,$file");

    foreach ($pseudo as $index => $m) {
        [$nr, $nf] = $m['to'];

        $backupBoard = $this->board;
        $backupKingPos = $this->kingPos;

        $isCastling = strtoupper($piece) === 'K' && abs($nf - $file) === 2;

        // Simulate king move
        $captured = $this->board[$nr][$nf];
        $this->board[$nr][$nf] = $piece;
        $this->board[$rank][$file] = null;

        // Simulate rook move if castling
        if ($isCastling) {
            $rookFromFile = $nf > $file ? 7 : 0;
            $rookToFile   = $nf > $file ? 5 : 3;
            $this->board[$nr][$rookToFile] = $this->board[$nr][$rookFromFile];
            $this->board[$nr][$rookFromFile] = null;
            error_log("Sim castling rook from $nr,$rookFromFile to $nr,$rookToFile");
        }

        // Update king pos
        if (strtoupper($piece) === 'K') {
            $this->kingPos[$color] = [$nr, $nf];
            error_log("Sim move $index: king pos forced to $nr,$nf");
        }

        $inCheck = $this->isInCheck($color);
        error_log("Sim move $index to $nr,$nf: in check after? " . ($inCheck ? 'YES' : 'NO'));

        if (!$inCheck) {
            $legal[] = $m;
            error_log("Sim move $index accepted");
        } else {
            error_log("Sim move $index rejected (in check)");
        }

        // Restore
        $this->board = $backupBoard;
        $this->kingPos = $backupKingPos;
    }

    error_log("Final legal count: " . count($legal));

    return $legal;
}

    private function getPseudoLegalMoves($rank, $file, $attacksOnly = false) {
    $moves = [];
    $piece = $this->board[$rank][$file];
    if (!$piece) return $moves;

    $color = ctype_upper($piece) ? 'w' : 'b';
    $type = strtoupper($piece);
	error_log("Processing piece at $rank,$file: type=$type color=$color");
	error_log("Piece at $rank,$file: type=$type, color=$color");
    $dir = $color === 'w' ? 1 : -1;

    switch ($type) {
        case 'P': // Pawn
            // Single forward
            $nr = $rank + $dir;
            if ($nr >= 0 && $nr < 8 && $this->board[$nr][$file] === null) {
                $moves[] = ['to' => [$nr, $file]];
            }

            // Double forward from starting rank
            $startRank = $color === 'w' ? 1 : 6;
            if ($rank === $startRank) {
                $nr2 = $rank + 2 * $dir;
                $mid = $rank + $dir;
                if ($nr2 >= 0 && $nr2 < 8 &&
                    $this->board[$mid][$file] === null &&
                    $this->board[$nr2][$file] === null) {
                    $moves[] = ['to' => [$nr2, $file]];
                }
            }

            // Captures + en passant
            foreach ([-1, 1] as $df) {
                $nf = $file + $df;
                if ($nf >= 0 && $nf < 8) {
                    $nr = $rank + $dir;
                    if ($nr >= 0 && $nr < 8) {
                        $target = $this->board[$nr][$nf];
                        if ($target !== null && $color !== (ctype_upper($target) ? 'w' : 'b')) {
                            $moves[] = ['to' => [$nr, $nf]];
                        }
                        // En passant
                        if ($this->enPassant === $this->coordToAlg($nr, $nf)) {
                            $moves[] = ['to' => [$nr, $nf]];
                        }
                    }
                }
            }
            break;

        case 'N': // Knight
			$deltas = [[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[1,-2],[-1,2],[-1,-2]];
			foreach ($deltas as $delta) {
				$nr = $rank + $delta[0];
				$nf = $file + $delta[1];
				if ($nr >= 0 && $nr < 8 && $nf >= 0 && $nf < 8) {
					$target = $this->board[$nr][$nf];
					$targetColor = $target === null ? 'empty' : (ctype_upper($target) ? 'w' : 'b');
					if ($attacksOnly) {
						// For attacks (check detection), add if empty or opponent
						if ($target === null || $color !== $targetColor) {
							$moves[] = ['to' => [$nr, $nf]];
						}
					} else {
						// For normal moves, add if empty or opponent
						if ($target === null || $color !== $targetColor) {
							$moves[] = ['to' => [$nr, $nf]];
						}
					}
				}
			}
			break;

        case 'B': // Bishop
        case 'R': // Rook
        case 'Q': // Queen
            $directions = [];
            if ($type === 'B' || $type === 'Q') {
                $directions = array_merge($directions, [[1,1],[1,-1],[-1,1],[-1,-1]]);
            }
            if ($type === 'R' || $type === 'Q') {
                $directions = array_merge($directions, [[1,0],[-1,0],[0,1],[0,-1]]);
            }

            foreach ($directions as $d) {
                $dr = $d[0];
                $df = $d[1];
                $nr = $rank + $dr;
                $nf = $file + $df;
                while ($nr >= 0 && $nr < 8 && $nf >= 0 && $nf < 8) {
                    $target = $this->board[$nr][$nf];
                    if ($target === null) {
                        $moves[] = ['to' => [$nr, $nf]];
                        $nr += $dr;
                        $nf += $df;
                        continue;
                    }
                    if ($color !== (ctype_upper($target) ? 'w' : 'b')) {
                        $moves[] = ['to' => [$nr, $nf]];
                    }
                    break;
                }
            }
            break;

        case 'K':
    error_log("Entering king case for color=$color at rank=$rank file=$file");

    $deltas = [[1,1],[1,-1],[-1,1],[-1,-1],[1,0],[-1,0],[0,1],[0,-1]];
    $adjacentCount = 0;

    foreach ($deltas as $delta) {
        $nr = $rank + $delta[0];
        $nf = $file + $delta[1];
        if ($nr >= 0 && $nr < 8 && $nf >= 0 && $nf < 8) {
            $target = $this->board[$nr][$nf];
            $targetColor = $target === null ? 'empty' : (ctype_upper($target) ? 'w' : 'b');
            error_log("King check adjacent $nr,$nf: target='$target' targetColor=$targetColor");

            if ($attacksOnly || $target === null || $color !== $targetColor) {
                $moves[] = ['to' => [$nr, $nf]];
                $adjacentCount++;
                error_log("Added king move to $nr,$nf");
            }
        }
    }

    error_log("King adjacent moves added: $adjacentCount");

    // Castling
    if (!$attacksOnly) {
        $castleRank = $color === 'w' ? 0 : 7;
        $oppColor = $color === 'w' ? 'b' : 'w';

        // King-side
        if (strpos($this->castling, $color === 'w' ? 'K' : 'k') !== false) {
			//djt01 - start
			$moves[] = ['to' => [$castleRank, 6]];
			error_log("Added king-side castling move to $castleRank,6");
			//djt01 - end
            error_log("King-side castling check for $color");
            if ($this->board[$castleRank][5] === null &&
                $this->board[$castleRank][6] === null &&
                !$this->isSquareAttacked($castleRank, 4, $oppColor) &&
                !$this->isSquareAttacked($castleRank, 5, $oppColor) &&
                !$this->isSquareAttacked($castleRank, 6, $oppColor)) {
                $moves[] = ['to' => [$castleRank, 6]];
                error_log("Added king-side castling move");
            } else {
                error_log("King-side castling blocked");
            }
        }

        // Queen-side
        if (strpos($this->castling, $color === 'w' ? 'Q' : 'q') !== false) {
			//djt01 - start
			$moves[] = ['to' => [$castleRank, 2]];
			error_log("Added queen-side castling move to $castleRank,2");
			//djt01 - end
            error_log("Queen-side castling check for $color");
            if ($this->board[$castleRank][1] === null &&
                $this->board[$castleRank][2] === null &&
                $this->board[$castleRank][3] === null &&
                !$this->isSquareAttacked($castleRank, 4, $oppColor) &&
                !$this->isSquareAttacked($castleRank, 2, $oppColor) &&
                !$this->isSquareAttacked($castleRank, 3, $oppColor)) {
                $moves[] = ['to' => [$castleRank, 2]];
                error_log("Added queen-side castling move");
            } else {
                error_log("Queen-side castling blocked");
            }
        }
    }

    error_log("King total moves generated: " . count($moves));
    break;
    }

    return $moves;
}

    private function addSlidingMoves(&$moves, $rank, $file, $directions, $color, $attacksOnly) {
        foreach ($directions as [$dr, $df]) {
            $nr = $rank + $dr;
            $nf = $file + $df;
            while ($nr >= 0 && $nr < 8 && $nf >= 0 && $nf < 8) {
                $target = $this->board[$nr][$nf];
                if ($target === null) {
                    $moves[] = ['to' => [$nr, $nf]];
                    $nr += $dr;
                    $nf += $df;
                    continue;
                }
                if ($color !== ctype_upper($target) ? 'w' : 'b') {
                    $moves[] = ['to' => [$nr, $nf]];
                }
                break;
            }
        }
    }

    private function isSquareAttacked($rank, $file, $attackerColor) {
        for ($r = 0; $r < 8; $r++) {
            for ($f = 0; $f < 8; $f++) {
                $p = $this->board[$r][$f];
                if ($p && ctype_upper($p) === ctype_upper($attackerColor[0])) {
                    $attacks = $this->getPseudoLegalMoves($r, $f, true); // attacks only
                    foreach ($attacks as $a) {
                        if ($a['to'][0] === $rank && $a['to'][1] === $file) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    private function algToCoord($sq) {
		if (!is_string($sq) || strlen($sq) !== 2 || !preg_match('/^[a-h][1-8]$/', $sq)) {
			error_log("Invalid algebraic square: '$sq' (not a string or wrong format)");
			return null;
		}

		$fileChar = $sq[0];
		$rankChar = $sq[1];

		$file = ord($fileChar) - ord('a');
		$rank = (int)$rankChar - 1;

		if ($file < 0 || $file > 7 || $rank < 0 || $rank > 7) {
			error_log("Square out of bounds: '$sq' → file=$file rank=$rank");
			return null;
		}

		error_log("algToCoord: '$sq' → rank=$rank file=$file");

		return [$rank, $file];
	}

    private function coordToAlg($r, $f) {
        return chr($f + ord('a')) . ($r + 1);
    }

    public function inCheckmate() {
        if (!$this->isInCheck($this->turn)) return false;

        // Check if any legal move escapes check
        for ($r = 0; $r < 8; $r++) {
            for ($f = 0; $f < 8; $f++) {
                $p = $this->board[$r][$f];
                if ($p && ctype_upper($p) === ($this->turn === 'w' ? 'A' : 'a')) { // same color
                    if (count($this->getLegalMovesForSquare($r, $f)) > 0) return false;
                }
            }
        }
        return true;
    }

    public function inStalemate() {
        if ($this->isInCheck($this->turn)) return false;

        for ($r = 0; $r < 8; $r++) {
            for ($f = 0; $f < 8; $f++) {
                $p = $this->board[$r][$f];
                if ($p && ctype_upper($p) === ($this->turn === 'w' ? 'A' : 'a')) {
                    if (count($this->getLegalMovesForSquare($r, $f)) > 0) return false;
                }
            }
        }
        return true;
    }

    public function inDraw() {
        // Simplified: stalemate + insufficient material + 50-move rule
        if ($this->inStalemate() || $this->halfmoveClock >= 50) return true;

        // Insufficient material stub (add full check if needed)
        return false;
    }
}