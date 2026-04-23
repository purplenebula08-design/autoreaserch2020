<?php

/**
 * Équivalent de evaluate_bpb dans prepare.py de Karpathy.
 * Vérité terrain — ne jamais modifier cette logique.
 * Retourne un score 0-100 (plus haut = meilleur, inverse de val_bpb).
 */
function evaluate(Mistral $mistral, string $task, string $result, string $approach): int {
    $prompt = <<<PROMPT
Tu es un évaluateur strict et objectif. Note ce résultat de 0 à 100.

TÂCHE ORIGINALE:
$task

APPROCHE UTILISÉE:
$approach

RÉSULTAT PRODUIT:
$result

CRITÈRES (note chacun sur 25):
1. Pertinence — le résultat répond-il exactement à la tâche ?
2. Complétude — tous les aspects importants sont-ils couverts ?
3. Qualité — le résultat est-il bien exécuté, précis, utile ?
4. Efficacité — approche simple et directe, sans superflu ?

Réponds UNIQUEMENT avec un entier entre 0 et 100. Rien d'autre.
PROMPT;

    $response = $mistral->chat([
        ['role' => 'user', 'content' => $prompt]
    ], 0.1, 10);

    $score = (int) trim(preg_replace('/[^0-9]/', '', $response));
    return max(0, min(100, $score));
}
