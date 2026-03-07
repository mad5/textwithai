<?php
$config = [
	"llm" => "ollama", //"openai", // "ollama",
	"ollama_url"=> "http://192.168.0.x:11434",
	"ollama_model" => "gemma3:12b",
	"openai_key" => "sk-proj-.....",
	"openai_model" => "gpt-4o",
	"allowedpath" => '/',
	"storage" => __DIR__.'/storage',
	"system_prompt" => "Always maintain the meaning and markdown format. Respond ONLY with the corrected text.  If there is nothing to correct, just return the original text without any explanation.",
];

return $config;
