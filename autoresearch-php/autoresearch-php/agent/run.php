<?php
/**
 * autoresearch-php — boucle autonome inspirée de karpathy/autoresearch
 * Usage: php agent/run.php --task="ta tâche" --tag=apr23
 */

require_once __DIR__ . '/mistral.php';
require_once __DIR__ . '/evaluate.php';

// --- Config ---
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

$apiKey        = $_ENV['MISTRAL_API_KEY'] ?? '';
$model         = $_ENV['MISTRAL_MODEL']   ?? 'mistral-small-latest';
$maxIterations = (int)($_ENV['MAX_ITERATIONS'] ?? 100);
$threshold     = (int)($_ENV['SCORE_THRESHOLD'] ?? 90);

if (!$apiKey || $apiKey === 'your_key_here') {
    die("❌ Configure MISTRAL_API_KEY dans .env\n");
}

// --- Args ---
$opts = getopt('', ['task:', 'tag:']);
$task = $opts['task'] ?? null;
$tag  = $opts['tag']  ?? date('Mj');

if (!$task) {
    die("Usage: php agent/run.php --task=\"ta tâche\" --tag=apr23\n");
}

$mistral    = new Mistral($apiKey, $model);
$resultsFile = __DIR__ . "/../results/results_{$tag}.tsv";
$logFile     = __DIR__ . "/../logs/run_{$tag}.log";

// --- Init results.tsv (équivalent initialize results.tsv de Karpathy) ---
if (!file_exists($resultsFile)) {
    file_put_contents($resultsFile, "iteration\tscore\tstatus\tapproach\n");
}

function log_msg(string $msg, string $logFile): void {
    $line = "[" . date('H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

function log_result(string $file, int $iter, int $score, string $status, string $approach): void {
    $approach = str_replace(["\t", "\n"], ' ', substr($approach, 0, 80));
    file_put_contents($file, "$iter\t$score\t$status\t$approach\n", FILE_APPEND);
}

// --- État ---
$bestScore    = -1;
$bestResult   = '';
$bestApproach = '';
$history      = [];
$crashes      = 0;
$iteration    = 0;

log_msg("🚀 autoresearch-php démarré — tag: $tag", $logFile);
log_msg("📋 Tâche: $task", $logFile);
log_msg("🎯 Seuil: $threshold/100 — Max itérations: $maxIterations", $logFile);
log_msg(str_repeat('-', 60), $logFile);

// ============================================================
// BOUCLE PRINCIPALE — LOOP FOREVER (équivalent Karpathy)
// ============================================================
while ($iteration < $maxIterations) {
    $iteration++;
    log_msg("🔄 Itération $iteration/$maxIterations", $logFile);

    // --- 1. Construire le contexte historique ---
    $historyText = '';
    if (!empty($history)) {
        $historyText = "\nHISTORIQUE DES TENTATIVES PRÉCÉDENTES:\n";
        foreach (array_slice($history, -5) as $h) { // 5 dernières seulement
            $historyText .= "- Approche: {$h['approach']} → Score: {$h['score']}/100 ({$h['status']})\n";
        }
        $historyText .= "\nMEILLEUR RÉSULTAT ACTUEL (score $bestScore/100):\n$bestResult\n";
    }

    // --- 2. Proposer une approche (équivalent "tune train.py") ---
    $proposePrompt = <<<PROMPT
Tu es un agent autonome de recherche et d'amélioration.

TÂCHE: $task
$historyText

INSTRUCTIONS:
- Propose UNE nouvelle approche différente des précédentes
- Si c'est la première fois, commence par l'approche la plus directe
- Si tu as un historique, analyse ce qui a marché/échoué et innove
- Réfléchis à TOUS les aspects pertinents que tu connais sur ce sujet
- Ne jamais répéter exactement une approche déjà essayée

Réponds en JSON strict:
{
  "approach": "description courte de l'approche (1 ligne)",
  "reasoning": "pourquoi cette approche devrait mieux fonctionner",
  "result": "le résultat complet et détaillé de l'exécution de cette approche"
}
PROMPT;

    try {
        $raw = $mistral->chat([
            ['role' => 'system', 'content' => 'Tu es un agent autonome expert. Tu réponds uniquement en JSON valide.'],
            ['role' => 'user',   'content' => $proposePrompt],
        ], 0.8, 3000);

        // Parser le JSON
        $raw = preg_replace('/^```json\s*/m', '', $raw);
        $raw = preg_replace('/^```\s*/m', '', $raw);
        $parsed = json_decode(trim($raw), true);

        if (!$parsed || !isset($parsed['result'])) {
            throw new RuntimeException("JSON invalide: $raw");
        }

        $approach = $parsed['approach'] ?? 'approche sans nom';
        $result   = $parsed['result'];
        $crashes  = 0; // reset crash counter

        log_msg("💡 Approche: $approach", $logFile);

    } catch (RuntimeException $e) {
        $crashes++;
        log_msg("💥 Crash #$crashes: " . $e->getMessage(), $logFile);
        log_result($resultsFile, $iteration, 0, 'crash', 'erreur API ou JSON');

        if ($crashes >= 3) {
            log_msg("⚠️  3 crashes consécutifs — changement radical d'approche forcé", $logFile);
            $crashes = 0;
        }
        sleep(2);
        continue;
    }

    // --- 3. Évaluer (équivalent grep val_bpb de Karpathy) ---
    try {
        $score = evaluate($mistral, $task, $result, $approach);
        log_msg("📊 Score: $score/100", $logFile);
    } catch (RuntimeException $e) {
        log_msg("💥 Erreur évaluation: " . $e->getMessage(), $logFile);
        log_result($resultsFile, $iteration, 0, 'crash', $approach);
        sleep(2);
        continue;
    }

    // --- 4. Garder ou annuler (équivalent keep/discard de Karpathy) ---
    if ($score > $bestScore) {
        $bestScore    = $score;
        $bestResult   = $result;
        $bestApproach = $approach;
        $status       = 'keep';
        log_msg("✅ AMÉLIORÉ → nouveau meilleur score: $score/100", $logFile);

        // Sauvegarder le meilleur résultat
        file_put_contents(__DIR__ . "/../results/best_{$tag}.txt",
            "Score: $score/100\nApproche: $approach\n\n$result"
        );
    } else {
        $status = 'discard';
        log_msg("❌ Pas d'amélioration ($score <= $bestScore) — annulé", $logFile);
    }

    // Enregistrer dans le TSV
    log_result($resultsFile, $iteration, $score, $status, $approach);

    // Ajouter à l'historique
    $history[] = ['approach' => $approach, 'score' => $score, 'status' => $status];

    // --- 5. Critère d'arrêt ---
    if ($bestScore >= $threshold) {
        log_msg("🎉 Seuil $threshold atteint avec score $bestScore/100 !", $logFile);
        log_msg("📄 Meilleur résultat sauvé dans results/best_{$tag}.txt", $logFile);
        break;
    }

    // Petite pause pour ne pas bruler les tokens trop vite
    sleep(1);
}

// --- Résumé final ---
log_msg(str_repeat('=', 60), $logFile);
log_msg("🏁 FIN — $iteration itérations — Meilleur score: $bestScore/100", $logFile);
log_msg("📄 Résultats: results/results_{$tag}.tsv", $logFile);
log_msg("🏆 Meilleur résultat: results/best_{$tag}.txt", $logFile);

if ($bestResult) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "MEILLEUR RÉSULTAT (score: $bestScore/100)\n";
    echo str_repeat('=', 60) . "\n";
    echo $bestResult . "\n";
}
