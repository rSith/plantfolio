# ml-service/ — Flask ML API (stage M, weeks 11–14)

A small Python service that runs the two image models. **Only PHP talks to it** — the browser never does.
PHP saves the uploaded photo, sends it here with cURL, and stores the JSON reply in `ml_predictions`.
If the service is offline or slow, pages fall back to manual entry.

## Endpoints (draft — finalised in `docs/api-contract.md`, task DES-10)

| Method & path | Input | Returns | Task |
|---|---|---|---|
| `GET /health` | — | `{"status": "ok", "models": {...}}` | ML-01 |
| `POST /predict/species` | multipart `image` (JPG/PNG ≤ 5 MB) | top 3 species with confidence | ML-09 |
| `POST /predict/disease` | multipart `image` | top 3 conditions with confidence | ML-10 |

Example response:
```json
{
  "model_version": "disease-mobilenetv2-v1",
  "predictions": [
    {"label": "Tomato___Early_blight", "confidence": 0.92},
    {"label": "Tomato___Septoria_leaf_spot", "confidence": 0.05},
    {"label": "Tomato___healthy", "confidence": 0.02}
  ]
}
```
- Errors return JSON with an HTTP status and a message (e.g. `400` bad image, `415` wrong type, `503` model not loaded).
- PHP gives up after **10 seconds** and shows the manual fallback.
- Confidence words are decided in PHP: **High ≥ 80 %**, **Medium 60–79 %**, **Not sure < 60 %**.
- Models are loaded **once** at start-up, not per request.

## Models
| Feature | Plan | Target | Tasks |
|---|---|---|---|
| Disease detection | PlantVillage, 70/15/15 split; baseline CNN from scratch, then fine-tuned MobileNetV2 with augmentation | ≥ 90 % test accuracy; also measured on 20–30 real phone photos | ML-03 – ML-06 |
| Plant recognition | Fine-tuned MobileNetV2 on the species in `database/species.csv` — **or** the Pl@ntNet API if coverage is too narrow (decide in week 11, ML-07) | Correct species in the top 3 for most of ≥ 30 labelled phone photos | ML-07, ML-08 |

**Scope note:** PlantVillage covers food crops (tomato, potato, pepper, apple, grape, corn …) on plain backgrounds.
The Health Check page lists the supported crops and shows a scope message for other plants instead of guessing.

## Folders
- `app.py` — Flask app (created in ML-01) · `requirements.txt` — pinned packages
- `notebooks/` — Colab training notebooks (commit them, cleared of large outputs)
- `models/` — trained `.keras`/`.h5` files are **git-ignored** (too large); share via Google Drive — see `models/README.md`

## Setup
```bash
cd ml-service
python -m venv venv
venv\Scripts\activate            # macOS/Linux: source venv/bin/activate
pip install -r requirements.txt
python app.py                    # serves on http://127.0.0.1:5000
```
The URL PHP uses is `ml_service_url` in `config/config.php`.
