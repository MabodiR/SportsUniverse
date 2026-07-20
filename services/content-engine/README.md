# SportsUniverse content engine

This optional Python service enriches uploaded videos with speech transcription, text detected in sampled frames, and semantic embeddings.

Run it in an isolated worker/container with `uvicorn app:app --host 0.0.0.0 --port 8090`, then configure Laravel with `CONTENT_ANALYSIS_URL=http://content-engine:8090` and the same `CONTENT_ANALYSIS_TOKEN` in both services. Without this URL, Laravel still creates metadata-based topics and local fallback embeddings.
