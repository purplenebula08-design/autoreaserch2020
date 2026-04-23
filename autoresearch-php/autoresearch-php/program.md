# autoresearch-php

Agent autonome inspiré de karpathy/autoresearch.
Au lieu d'un GPU + PyTorch, utilise l'API Mistral.
Au lieu d'optimiser val_bpb, optimise un score de qualité sur n'importe quelle tâche.

## Setup

1. Copie `.env.example` → `.env` et mets ta clé Mistral
2. Lance : `php agent/run.php --task="ta tâche ici" --tag=apr23`
3. L'agent tourne seul jusqu'à ce que tu l'arrêtes (Ctrl+C)

## Ce que fait l'agent

BOUCLE INFINIE :
1. Lit la tâche + l'historique des tentatives
2. Propose une approche (ou améliore la précédente)
3. Exécute → produit un résultat
4. S'auto-évalue avec un score 0-100
5. Si score meilleur → garde (commit dans results.tsv)
6. Si score pareil ou moins bon → annule, essaie autre chose
7. Recommence — JAMAIS de pause, JAMAIS de question à l'humain

## Métrique

Score 0-100 calculé par Mistral lui-même sur critères :
- Pertinence par rapport à la tâche
- Complétude
- Qualité d'exécution
- Simplicité / efficacité

## Règles (équivalent des contraintes de Karpathy)

- `task.txt` est en lecture seule — ne jamais modifier la tâche originale
- `evaluate()` est la vérité terrain — ne pas contourner le score
- Ne jamais s'arrêter pour demander confirmation à l'humain
- Si bloqué après 3 crashes → changer d'approche radicalement
- Simplicité : une amélioration de +1 point qui double la complexité = pas la peine
