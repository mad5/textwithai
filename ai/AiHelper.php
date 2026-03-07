<?php

function run($cmd) {
	$output = shell_exec($cmd);
	return $output;
}

function getToolsDescription() {
	$tools = [];
	$all = glob("tools/*.json");
	foreach($all as $f) {
		$tool = json_decode(file_get_contents($f), true);
		$tools[] = $tool;
	}
	return json_encode($tools, JSON_PRETTY_PRINT);
}

function getJsonWithoutFence($resForDecode) {
	// Wenn "tool_calls" vorkommt: nur den JSON-Teil aus dem Text holen (Text davor/danach verwerfen)
	if (stripos($resForDecode, 'tool_calls') !== false
		|| stripos($resForDecode, '"task_list":') !== false
		|| stripos($resForDecode, '"message": {') !== false
	) {
		$jsonFenceStart = stripos($resForDecode, '```json');
		if ($jsonFenceStart !== false) {
			$contentStart = $jsonFenceStart + 7;
			$fenceEnd = strpos($resForDecode, '```', $contentStart);
			if ($fenceEnd !== false) {
				$resForDecode = trim(substr($resForDecode, $contentStart, $fenceEnd - $contentStart));
			}
		}
	} else {
		// Ohne tool_calls: nur Fence entfernen, wenn genau am Anfang
		if (stripos($resForDecode, '```json') === 0) {
			$resForDecode = trim(substr($resForDecode, 7));
			if (str_ends_with($resForDecode, '```')) {
				$resForDecode = trim(substr($resForDecode, 0, -3));
			}
		}
	}
	return $resForDecode;
}

function sendlog($msg) {
	if(!is_string($msg)) $msg = print_r($msg,1);
	$msg = trim($msg);
	if(!file_exists("storage/log/ai.log")) {
		if (!is_dir("storage/log")) {
			mkdir("storage/log", 0777, true);
		}
	}
	$trace = getTrace(true);
	#var_dump($trace);exit;
	$s = basename($trace[1]["file"])."/".$trace[1]["line"];
	$line = "****************************************************\n".date("Y-m-d H:i:s")."\t".$s."\n".$msg;
	file_put_contents("storage/log/ai.log", $line."\n\n", FILE_APPEND);
	
	// Logs auf stderr ausgeben, damit stdout für die eigentliche Antwort (JSON oder Content) sauber bleibt
	//file_put_contents('php://stderr', substr($line, 0, 1000) . "\n\n");
}

function getTrace($asArray = false) {
	try {
		// Wir werfen eine Exception, um einen Stack-Trace zu generieren
		throw new Exception('');
	} catch (Exception $e) {
		// Überprüfen Sie, ob der Stack-Trace als Array zurückgegeben werden soll
		if ($asArray) {
			// Gib den Stack-Trace als Array zurück
			return $e->getTrace();
		}

		// Gib den Stack-Trace als Zeichenkette zurück
		return $e->getTraceAsString();
	}
}



/**
 * Holt den Rohtext einer URL: curl lädt die Seite, PHP bereinigt den Inhalt.
 * - Keine <script>-Inhalte
 * - Nur Inhalt zwischen <body> und </body>, falls vorhanden nur <main>…</main>
 * - Am Ende werden alle HTML-Tags mit strip_tags entfernt.
 * Gibt null zurück bei Fehler oder leerem Ergebnis.
 */
function fetchPageText(string $url): ?string {
	$escapedUrl = escapeshellarg($url);
	$html = @shell_exec("curl -L {$escapedUrl} -s 2>/dev/null");
	if ($html === null || $html === '') {
		return null;
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	@$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();

	// Alle <script>-Elemente entfernen
	$scripts = $dom->getElementsByTagName('script');
	while ($scripts->length > 0) {
		$scripts->item(0)->parentNode->removeChild($scripts->item(0));
	}

	$bodyList = $dom->getElementsByTagName('body');
	if ($bodyList->length === 0) {
		return null;
	}
	$body = $bodyList->item(0);

	$mainList = $body->getElementsByTagName('main');
	$contentNode = $mainList->length > 0 ? $mainList->item(0) : $body;

	$innerHtml = '';
	foreach ($contentNode->childNodes as $child) {
		$innerHtml .= $dom->saveHTML($child);
	}
	$text = strip_tags($innerHtml);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = trim(preg_replace('/\s+/u', ' ', $text));
	return $text === '' ? null : $text;
}


/**
 * Sendet den Rohtext an die KI mit der Bitte, nur den eigentlichen Seiteninhalt
 * (Artikel/Content) zu behalten; Navigation, Footer, Werbung etc. entfernen.
 */
function cleanContentWithAi(string $rawText): string {
	$maxInput = 120000;
	if (strlen($rawText) > $maxInput) {
		$rawText = substr($rawText, 0, $maxInput) . "\n[... Text gekürzt ...]";
	}

	$prompt = "Der folgende Text wurde von einer Webseite extrahiert und enthält oft Navigation, Menüs, Werbung, Footer und ähnliches.\n\n"
		. "Aufgabe: Extrahiere NUR den eigentlichen Hauptinhalt der Seite (Artikel, Beitrag, Inhalt). "
		. "Entferne alles andere (Navigation, Links-Menüs, „Cookie hinweisen“, „Zum Seitenanfang“, Werbeblöcke, Footer-Texte). "
		. "Antworte ausschließlich mit dem bereinigten Inhalt, ohne Einleitung oder Erklärung.\n\n---\n\n" . $rawText;

	return askAi($prompt);
}

function writeJson($filename, $data) {
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	file_put_contents($filename, $json);
}

function readJson($filename, $default = []) {
	if (file_exists($filename)) {
		$json = file_get_contents($filename);
		return json_decode($json, true);
	}
	return $default;

}
