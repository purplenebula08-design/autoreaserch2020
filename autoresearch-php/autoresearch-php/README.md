# autoresearch-php

Portage PHP de [karpathy/autoresearch](https://github.com/karpathy/autoresearch).

**Au lieu de :** GPU + PyTorch + optimiser un LLM  
**Ici :** API Mistral + optimiser n'importe quelle tâche

## Installation

```bash
cp .env.example .env
# Édite .env et mets ta clé Mistral (https://console.mistral.ai)
```

## Usage

```bash
# Tâche simple
php agent/run.php --task="Écris une page HTML vitrine pour un restaurant japonais" --tag=test1

# Tâche complexe
php agent/run.php --task="Crée une stratégie SEO complète pour un blog tech" --tag=seo1

# L'agent tourne seul jusqu'à score >= 90/100 ou 100 itérations
# Ctrl+C pour arrêter
```

## Structure

```
autoresearch-php/
├── agent/
│   ├── run.php        # Boucle principale (LOOP FOREVER)
│   ├── mistral.php    # Client API Mistral
│   └── evaluate.php   # Évaluateur — vérité terrain (ne pas modifier)
├── results/
│   ├── results_TAG.tsv  # Historique de toutes les tentatives
│   └── best_TAG.txt     # Meilleur résultat sauvegardé
├── logs/
│   └── run_TAG.log      # Log complet de la session
├── program.md           # Règles de l'agent
└── .env                 # Ta clé Mistral
```

## Correspondance avec Karpathy

| Karpathy | autoresearch-php |
|---|---|
| GPU + PyTorch | API Mistral |
| `train.py` modifié par l'agent | Le prompt/approche modifié à chaque iter |
| `val_bpb` (plus bas = meilleur) | Score 0-100 (plus haut = meilleur) |
| `keep` / `discard` | `keep` / `discard` |
| `results.tsv` | `results/results_TAG.tsv` |
| `NEVER STOP` | Boucle jusqu'à seuil ou max itérations |

## Variables .env

| Variable | Défaut | Description |
|---|---|---|
| `MISTRAL_API_KEY` | — | Obligatoire |
| `MISTRAL_MODEL` | `mistral-small-latest` | Modèle à utiliser |
| `MAX_ITERATIONS` | `100` | ~100 itérations pendant que tu dors |
| `SCORE_THRESHOLD` | `90` | Stop automatique si score >= 90 |
