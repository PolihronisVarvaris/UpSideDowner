<?php

require_once __DIR__ . '/../app/UserController.php';

$controller = new UserController();

// Helper to check and finalize KOs
function check_kos() {
    $new_kos = [];
    $limit = $_SESSION['ko_limit'] ?? 0;
    
    if ($limit > 0) {
        foreach ($_SESSION['scores'] as $player => $score) {
            if ($score['sipped'] >= $limit && empty($score['ko'])) {
                $_SESSION['scores'][$player]['ko'] = true;
                $new_kos[] = $player;
            }
        }
    }
    
    $active_players = [];
    foreach ($_SESSION['scores'] as $player => $score) {
        if (empty($score['ko'])) {
            $active_players[] = $player;
        }
    }
    
    $game_over = false;
    $winner = null;
    
    // Game over if 1 or 0 players left
    if (count($_SESSION['scores']) > 1 && count($active_players) <= 1) {
        $game_over = true;
        if (count($active_players) === 1) {
            $winner = $active_players[0];
        }
    }
    
    return [
        "new_kos" => $new_kos,
        "game_over" => $game_over,
        "winner" => $winner,
        "active_players" => array_values($active_players)
    ];
}

if ($uri === '/') {
    echo "Welcome to your PHP backend!";
}

elseif ($uri === '/start') {

    header('Content-Type: application/json');

    $playersParam = $_GET['players'] ?? '';
    $koLimitParam = (int)($_GET['ko_limit'] ?? 0);

    if (!$playersParam) {
        echo json_encode(["error" => "No players provided"]);
        exit;
    }

    $players = explode(",", $playersParam);
    $players = array_map('trim', $players);

    $_SESSION['players'] = $players;
    $_SESSION['turn'] = 0;
    $_SESSION['round'] = 1;
    $_SESSION['ko_limit'] = $koLimitParam;

    $_SESSION['scores'] = [];
    foreach ($players as $player) {
        $_SESSION['scores'][$player] = ["sipped" => 0, "given" => 0, "ko" => false];
    }

    echo json_encode([
        "message" => "Game started",
        "players" => $players,
        "ko_limit" => $koLimitParam
    ]);
}

elseif ($uri === "/play") {
    
    header('Content-Type: application/json');

    if (!isset($_SESSION['players'])) {
        echo json_encode(["error" => "Game not started"]);
        exit;
    }

    // Force an initial KO check just in case
    $statusCheck = check_kos();
    if ($statusCheck['game_over']) {
        echo json_encode([ "game_over" => true, "status" => $statusCheck, "scores" => $_SESSION['scores'] ]);
        exit;
    }

    $players = $_SESSION['players'];
    $turn = $_SESSION['turn'];
    
    // Skip knocked out players
    $safeBreak = 0;
    do {
        $currentPlayer = $players[$turn];
        if (!empty($_SESSION['scores'][$currentPlayer]['ko'])) {
            $turn++;
            if ($turn >= count($players)) {
                $turn = 0;
                $_SESSION['round']++;
            }
        } else {
            break; // found active player
        }
        $safeBreak++;
    } while ($safeBreak < count($players));
    
    $_SESSION['turn'] = $turn;
    
    $gravity = $controller->generateGravity();
    
    // ONLY pass ACTIVE players to generate targets dynamically
    $action = $controller->randomAction($gravity, $statusCheck['active_players']);

    $sips = 0;
    if (preg_match('/\d+/', $action['message'], $matches)) {
        $sips = (int)$matches[0];
    }

    $requires_target = false;
    $requires_challenge = false;

    if (preg_match('/give\s+(\d+)\s+sips/i', $action['message'], $dynamic_matches)) {
        $requires_target = true;
        $sips = (int)$dynamic_matches[1];
    }

    if ($action['type'] === 'drink' || $action['type'] === 'unstable') {
        $_SESSION['scores'][$currentPlayer]['sipped'] += $sips;
    } elseif ($action['type'] === 'give' || $action['type'] === 'boost') {
        $requires_target = true;
    } elseif ($action['type'] === 'orbit') {
        $requires_target = true; 
    } elseif ($action['type'] === 'challenge') {
        $requires_challenge = true;
    } elseif ($action['type'] === 'collapse' || $action['type'] === 'group') {
        // Everyone active drinks
        foreach ($statusCheck['active_players'] as $p) {
            $_SESSION['scores'][$p]['sipped'] += $sips;
        }
    } elseif ($action['type'] === 'reverse_sips' || $action['type'] === 'reverse') {
        foreach ($statusCheck['active_players'] as $p) {
            $_SESSION['scores'][$p]['given'] += $sips;
        }
    }

    // Precalculate next turn for next action
    $turn++;
    if ($turn >= count($players)) {
        $turn = 0;
        $_SESSION['round']++;
    }
    $_SESSION['turn'] = $turn;

    // Check if what just happened KO'd anyone
    $finalStatus = check_kos();

    echo json_encode([
        "player" => $currentPlayer,
        "type" => $action['type'],
        "message" => $action['message'],
        "gravity" => $gravity,
        "round" => $_SESSION['round'] ?? 1,
        "scores" => $_SESSION['scores'],
        "sips" => $sips,
        "requires_target" => $requires_target,
        "requires_challenge" => $requires_challenge,
        "active_players" => $finalStatus['active_players'],
        "status" => $finalStatus
    ]);
}

elseif ($uri === '/apply_action') {
    header('Content-Type: application/json');

    $type = $_GET['type'] ?? '';

    if ($type === 'give' || $type === 'boost') {
        $source = $_GET['source'] ?? '';
        $target = $_GET['target'] ?? '';
        $sips = (int)($_GET['sips'] ?? 0);
        
        if (isset($_SESSION['scores'][$source]) && isset($_SESSION['scores'][$target])) {
            $_SESSION['scores'][$source]['given'] += $sips;
            $_SESSION['scores'][$target]['sipped'] += $sips;
        }
    } elseif ($type === 'orbit') {
        // Social link mapping
    } elseif ($type === 'challenge') {
        $winner = $_GET['winner'] ?? '';
        $loser = $_GET['loser'] ?? '';
        
        if (isset($_SESSION['scores'][$winner]) && isset($_SESSION['scores'][$loser])) {
            $_SESSION['scores'][$winner]['given'] += 4;
            $_SESSION['scores'][$loser]['sipped'] += 4;
        }
    }

    $finalStatus = check_kos();
    echo json_encode(["status" => $finalStatus, "scores" => $_SESSION['scores']]);
}

elseif ($uri === '/update_score') {

    header('Content-Type: application/json');

    $player = $_GET['player'] ?? '';
    $type = $_GET['type'] ?? '';
    $change = (int)($_GET['change'] ?? 0);

    if ($player && in_array($type, ['sipped', 'given']) && isset($_SESSION['scores'][$player])) {
        $_SESSION['scores'][$player][$type] += $change;
        if ($_SESSION['scores'][$player][$type] < 0) {
            $_SESSION['scores'][$player][$type] = 0;
        }
        // Manual updates could potentially un-KO someone if sips were dragged down safely below limit in theory...
        // For simplicity, we just check and enforce KOs actively. 
        // If they drop below ko_limit but `ko` is already true, we un-KO them for manual forgiveness?
        $limit = $_SESSION['ko_limit'] ?? 0;
        if ($limit > 0 && $type === 'sipped' && $_SESSION['scores'][$player][$type] < $limit) {
            $_SESSION['scores'][$player]['ko'] = false; // "Medic!" forgiveness un-KO
        }
    }

    $finalStatus = check_kos();

    echo json_encode(["status" => $finalStatus, "scores" => $_SESSION['scores']]);
}

elseif ($uri === '/do') {
    header('Content-Type: application/json');
    $actions = $controller->getActions();
    echo json_encode($actions);
}

else {
    http_response_code(404);
    echo "404 Not Found";
}