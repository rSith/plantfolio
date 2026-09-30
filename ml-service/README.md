# ml-service/  — Flask ML API (Phase 3)

| Endpoint | Returns |
|---|---|
| `POST /predict/species` | Top 3 species with confidence |
| `POST /predict/disease` | Top 3 conditions with confidence |

- `notebooks/` — Colab training notebooks (commit them, cleared of large outputs)
- `models/` — trained `.keras`/`.h5` files are **git-ignored** (too large); share via Google Drive
- Setup: `python -m venv venv` → activate → `pip install -r requirements.txt`
