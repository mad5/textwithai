<?php

function initAiAgent($config) {
	if (($config['llm'] ?? 'ollama') === 'openai') {
		include_once __DIR__.'/OpenAIClient.php';
		$aiClient = new OpenAIClient($config['openai_key'], $config['openai_model'] ?? 'gpt-4o');
	} else {
		include_once __DIR__.'/OllamaClient.php';
		$aiClient = new OllamaClient($config['ollama_url'], $config['ollama_model']);
	}

	return $aiClient;
}
