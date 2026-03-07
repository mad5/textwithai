<?php
header('Content-Type: application/json');

$config = include_once 'config.php';
include_once 'ai/AiHelper.php';
include_once 'ai/AiClient.php';

$action = $_GET['action'] ?? '';

$storage = $config['storage'];

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
        $name = $data['name'] ?? '';
        if (empty($name)) {
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        if (!str_ends_with($name, '.md')) {
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

    case 'read':
        $name = $_GET['name'] ?? '';
        $path = $storage . '/' . $name;
        if (!file_exists($path)) {
            echo json_encode(['error' => 'File not found']);
            exit;
        }
        
        $revisions = [];
        $jsonPath = preg_replace('/\.md$/', '.json', $path);
        if (file_exists($jsonPath)) {
            $revisions = json_decode(file_get_contents($jsonPath), true);
        }

        echo json_encode([
            'content' => file_get_contents($path),
            'revisions' => $revisions
        ]);
        break;

    case 'save':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $content = $data['content'] ?? '';
        $path = $storage . '/' . $name;
        file_put_contents($path, $content);
        echo json_encode(['success' => true]);
        break;

    case 'save_revisions':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $revisions = $data['revisions'] ?? [];
        $path = $storage . '/' . $name;
        $jsonPath = preg_replace('/\.md$/', '.json', $path);
        file_put_contents($jsonPath, json_encode($revisions, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'process_paragraph':
        $data = json_decode(file_get_contents('php://input'), true);
        $text = $data['text'] ?? '';
        if (empty(trim($text))) {
            echo json_encode(['original' => $text, 'corrected' => $text]);
            exit;
        }

        try {
            $client = initAiAgent($config);
            $system_prompt = $config['system_prompt'] ?? "Correct this text section.";
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
