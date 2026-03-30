<?php

require_once __DIR__ . '/../app/UserController.php';

$controller = new UserController();

if ($uri === '/') {
    echo "Welcome to your PHP backend!";
}

elseif ($uri === '/start') {

    header('Content-Type: application/json');

    $playersParam = $_GET['players'] ?? '';

    if (!$playersParam) {
        echo json_encode(["error" => "No players provided"]);
        exit;
    }

    $players = explode(",", $playersParam);
    $players = array_map('trim', $players);

    $_SESSION['players'] = $players;
    $_SESSION['turn'] = 0;
    $_SESSION['round'] = 1;

    // ✅ Initialize scores with sipped/given structure
    $_SESSION['scores'] = [];
    foreach ($players as $player) {
        $_SESSION['scores'][$player] = ["sipped" => 0, "given" => 0];
    }

    echo json_encode([
        "message" => "Game started",
        "players" => $players
    ]);
}

elseif ($uri === "/play") {
    
    header('Content-Type: application/json');

    if (!isset($_SESSION['players'])) {
        echo json_encode(["error" => "Game not started"]);
        exit;
    }

    $players = $_SESSION['players'];
    $turn = $_SESSION['turn'];
    $round = $_SESSION['round'] ?? 1;
    $currentPlayer = $players[$turn];

    // --- Antigravity Mode Additions ---
    $gravity = $controller->generateGravity();
    $action = $controller->randomAction($gravity, $players);

    // Extract number of sips
    $sips = 0;
    if (preg_match('/\d+/', $action['message'], $matches)) {
        $sips = (int)$matches[0];
    }

    $requires_target = false;
    $requires_challenge = false;

    // Hybrid Scoring Model: Auto-update apparent drinks/gives from current player
    if ($action['type'] === 'drink' || $action['type'] === 'unstable') {
        $_SESSION['scores'][$currentPlayer]['sipped'] += $sips;
    } elseif ($action['type'] === 'give' || $action['type'] === 'boost') {
        $requires_target = true; // Wait for user frontend selection
    } elseif ($action['type'] === 'challenge') {
        $requires_challenge = true; // Trigger Battle Mode
    } elseif ($action['type'] === 'collapse' || $action['type'] === 'group') {
        foreach ($players as $p) {
            $_SESSION['scores'][$p]['sipped'] += $sips;
        }
    } elseif ($action['type'] === 'reverse_sips' || $action['type'] === 'reverse') {
        // "reverse" type (everyone gives)
        foreach ($players as $p) {
            $_SESSION['scores'][$p]['given'] += $sips;
        }
    }

    // Move turn and check round
    $turn++;
    if ($turn >= count($players)) {
        $turn = 0;
        $round++;
        $_SESSION['round'] = $round;
    }

    $_SESSION['turn'] = $turn;

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
        "players" => $players
    ]);
}

elseif ($uri === '/apply_action') {
    header('Content-Type: application/json');

    $type = $_GET['type'] ?? '';

    if ($type === 'give') {
        $source = $_GET['source'] ?? '';
        $target = $_GET['target'] ?? '';
        $sips = (int)($_GET['sips'] ?? 0);
        
        if (isset($_SESSION['scores'][$source]) && isset($_SESSION['scores'][$target])) {
            $_SESSION['scores'][$source]['given'] += $sips;
            $_SESSION['scores'][$target]['sipped'] += $sips;
        }
    } elseif ($type === 'challenge') {
        $winner = $_GET['winner'] ?? '';
        $loser = $_GET['loser'] ?? '';
        
        // Ensure penalities
        if (isset($_SESSION['scores'][$winner]) && isset($_SESSION['scores'][$loser])) {
            $_SESSION['scores'][$winner]['given'] += 4;
            $_SESSION['scores'][$loser]['sipped'] += 4;
        }
    }

    echo json_encode(["status" => "success", "scores" => $_SESSION['scores']]);
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
    }

    echo json_encode(["status" => "success", "scores" => $_SESSION['scores']]);
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