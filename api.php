<?php
header('Content-Type: application/json');

$config = include_once 'config.php';
include_once 'ai/AiHelper.php';
include_once 'ai/AiClient.php';

$action = $_GET['action'] ?? '';

// Nutzer-Subordner: bei "userid" => "browser" aus Request (Header/GET), sonst "default"
$baseStorage = $config['storage'];
$userFolder = 'default';
if (($_GET['public'] ?? '') === '1') {
	$userFolder = 'public';
} elseif (($config['userid'] ?? '') === 'browser') {
	$raw = $_SERVER['HTTP_X_USER_ID'] ?? $_GET['userid'] ?? '';
	$raw = trim((string) $raw);
	if ($raw !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $raw)) {
		$userFolder = $raw;
	}
}
$storage = $baseStorage . '/' . $userFolder;
if (!is_dir($storage)) {
	mkdir($storage, 0775, true);
}
$GLOBALS['textwithki_storage'] = $storage;

switch ($action) {
    case 'list':
        $files = glob($storage . '/*.md');
        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'name' => basename($file),
                'mtime' => filemtime($file)
            ];
        }
        // Sort by mtime descending
        usort($result, function($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });
        echo json_encode($result);
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = basename($data['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        if (substr($name, -3) !== '.md') {
            $name .= '.md';
        }
        $path = $storage . '/' . $name;
        if (file_exists($path)) {
            echo json_encode(['error' => 'File already exists']);
            exit;
        }
        file_put_contents($path, '');
        echo json_encode(['success' => true, 'name' => $name]);
        break;

    case 'list_assistants':
        $assistantsDir = 'assistants';
        if (!is_dir($assistantsDir)) {
            mkdir($assistantsDir, 0777, true);
        }
        $files = glob($assistantsDir . '/*.md');
        $result = [];
        foreach ($files as $file) {
            $result[] = basename($file, '.md');
        }
        echo json_encode($result);
        break;

    case 'read':
        $name = basename($_GET['name'] ?? '');
        $path = $storage . '/' . $name;
        if (!file_exists($path)) {
            echo json_encode(['error' => 'File not found']);
            exit;
        }
        
        $revisions = [];
        $selectedAssistant = 'Classic';
        $jsonPath = preg_replace('/\.md$/', '.json', $path);
        if (file_exists($jsonPath)) {
            $jsonData = json_decode(file_get_contents($jsonPath), true);
            if (is_array($jsonData)) {
                if (isset($jsonData['revisions'])) {
                    $revisions = $jsonData['revisions'];
                    $selectedAssistant = $jsonData['assistant'] ?? 'Classic';
                } else {
                    $revisions = $jsonData;
                }
            }
        }

        echo json_encode([
            'content' => file_get_contents($path),
            'revisions' => $revisions,
            'assistant' => $selectedAssistant
        ]);
        break;

    case 'save':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = basename($data['name'] ?? '');
        $content = $data['content'] ?? '';
        $path = $storage . '/' . $name;
        file_put_contents($path, $content);
        echo json_encode(['success' => true]);
        break;

    case 'save_revisions':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = basename($data['name'] ?? '');
        $revisions = $data['revisions'] ?? [];
        $assistant = $data['assistant'] ?? 'Classic';
        $path = $storage . '/' . $name;
        $jsonPath = preg_replace('/\.md$/', '.json', $path);
        $saveData = [
            'assistant' => $assistant,
            'revisions' => $revisions
        ];
        file_put_contents($jsonPath, json_encode($saveData, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'rename':
        $data = json_decode(file_get_contents('php://input'), true);
        $oldName = basename($data['oldName'] ?? '');
        $newName = basename($data['newName'] ?? '');
        if (empty($oldName) || empty($newName)) {
            echo json_encode(['error' => 'Old name and new name are required']);
            exit;
        }
        if (substr($newName, -3) !== '.md') {
            $newName .= '.md';
        }
        $oldPath = $storage . '/' . $oldName;
        $newPath = $storage . '/' . $newName;

        if (!file_exists($oldPath)) {
            echo json_encode(['error' => 'Original file not found']);
            exit;
        }
        if (file_exists($newPath)) {
            echo json_encode(['error' => 'A file with the new name already exists']);
            exit;
        }

        if (rename($oldPath, $newPath)) {
            // Also rename the .json file if it exists
            $oldJsonPath = preg_replace('/\.md$/', '.json', $oldPath);
            $newJsonPath = preg_replace('/\.md$/', '.json', $newPath);
            if (file_exists($oldJsonPath)) {
                rename($oldJsonPath, $newJsonPath);
            }
            echo json_encode(['success' => true, 'newName' => $newName]);
        } else {
            echo json_encode(['error' => 'Failed to rename file']);
        }
        break;

    case 'process_paragraph':
        $data = json_decode(file_get_contents('php://input'), true);
        $text = $data['text'] ?? '';
        $assistant = $data['assistant'] ?? 'Classic';
        $customPrompt = $data['custom_prompt'] ?? '';
        if (empty(trim($text))) {
            echo json_encode(['original' => $text, 'corrected' => $text]);
            exit;
        }

        try {
            $client = initAiAgent($config);
            $assistantPath = 'assistants/' . $assistant . '.md';
            $assistantPrompt = "";
            if (file_exists($assistantPath)) {
                $assistantPrompt = trim(file_get_contents($assistantPath)) . "\n\n";
            }

            $baseSystemPrompt = $config['system_prompt'] ?? "Correct this text section.";
            if ($customPrompt) {
                $system_prompt = $assistantPrompt . $baseSystemPrompt . "\n\nFühre die folgende Überarbeitung am Text durch: " . $customPrompt;
            } else {
                $system_prompt = $assistantPrompt . $baseSystemPrompt;
            }

            $client->generate($text, $system_prompt);
            $corrected = $client->getResponseText();
            echo json_encode(['original' => $text, 'corrected' => $corrected]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
