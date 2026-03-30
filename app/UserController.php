<?php

class UserController {

    public function getActions() {
        return [
            // Original Actions
            ["type" => "give", "message" => "Give 3 sips"],
            ["type" => "drink", "message" => "Drink 2 sips"],
            ["type" => "group", "message" => "Everyone drinks 1 sip"],
            ["type" => "rule", "message" => "Take 5 sips and create a rule for the group vote"],
            ["type" => "challenge", "message" => "Challenge someone, loser takes 4 sips"],
            ["type" => "question", "message" => "Answer a question and give 3 sips"],
            
            // Antigravity Themed Actions
            ["type" => "float", "message" => "Skip next turn"],
            ["type" => "reverse", "message" => "Everyone gives 2 sips"],
            ["type" => "boost", "message" => "Give 4 sips"],
            ["type" => "collapse", "message" => "Everyone drinks 2 sips"],
            ["type" => "orbit", "message" => "Another player copies your next action"],
            ["type" => "unstable", "message" => "Random player drinks 3 sips"]
        ];
    }

    public function generateGravity() {
        $gravities = ['normal', 'low gravity', 'zero gravity', 'reverse gravity'];
        return $gravities[array_rand($gravities)];
    }

    public function applyGravityModifier($action, $gravity, $players = []) {
        // Find existing sip numbers to potentially multiply
        preg_match('/\d+/', $action['message'], $matches);
        $sips = isset($matches[0]) ? (int)$matches[0] : 0;

        switch ($gravity) {
            case 'low gravity':
                // Amplify action (x1.5 effect)
                if ($sips > 0) {
                    $newSips = ceil($sips * 1.5);
                    $action['message'] = preg_replace('/\d+/', $newSips, $action['message'], 1);
                }
                break;

            case 'zero gravity':
                // Randomize action or target
                if (rand(0, 1) === 0 && !empty($players)) {
                    $randomTarget = $players[array_rand($players)];
                    $action['message'] = "[$randomTarget] " . $action['message'];
                } else {
                    $action['message'] = $action['message'] . " (And spin around twice!)";
                }
                break;

            case 'reverse gravity':
                // Invert action
                if (stripos($action['message'], 'give') !== false) {
                    $action['message'] = str_ireplace('give', 'drink', $action['message']);
                    // Alter the type to match the new behavior so scores register properly
                    $action['type'] = 'drink';
                } elseif (stripos($action['message'], 'drink') !== false) {
                    $action['message'] = str_ireplace('drink', 'give', $action['message']);
                    // Prevent scoring on an action that shifted away from drinking
                    $action['type'] = 'give'; 
                }
                break;

            case 'normal':
            default:
                break;
        }

        return $action;
    }

    public function randomAction($gravity = 'normal', $players = []) {
        $actions = $this->getActions();
        $randomIndex = array_rand($actions);
        $action = $actions[$randomIndex];
        
        return $this->applyGravityModifier($action, $gravity, $players);
    }
}
