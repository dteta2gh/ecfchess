CREATE DATABASE chess_game DEFAULT CHARACTER SET utf8mb4;

USE chess_game;

CREATE TABLE games (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fen         VARCHAR(128) NOT NULL DEFAULT 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
    moves       TEXT,                      -- PGN or JSON moves (we'll use simple JSON array)
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status      ENUM('active','checkmate','stalemate','draw','resign') DEFAULT 'active'
);