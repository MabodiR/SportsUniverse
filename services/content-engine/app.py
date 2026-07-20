import os
import tempfile
import urllib.request
from pathlib import Path

import cv2
import easyocr
from fastapi import Depends, FastAPI, Header, HTTPException
from faster_whisper import WhisperModel
from pydantic import BaseModel, HttpUrl
from sentence_transformers import SentenceTransformer

app = FastAPI(title="SportsUniverse Content Engine", version="1.0")
whisper = WhisperModel(os.getenv("WHISPER_MODEL", "small"), device=os.getenv("MODEL_DEVICE", "cpu"), compute_type=os.getenv("WHISPER_COMPUTE", "int8"))
encoder = SentenceTransformer(os.getenv("EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2"))
ocr = easyocr.Reader(os.getenv("OCR_LANGUAGES", "en").split(","), gpu=os.getenv("MODEL_DEVICE") == "cuda")


class AnalysisRequest(BaseModel):
    video_id: str
    media_url: HttpUrl
    caption: str | None = None
    hashtags: list[str] = []
    metadata: dict = {}


def authorize(authorization: str | None = Header(default=None)):
    expected = os.getenv("CONTENT_ANALYSIS_TOKEN")
    if expected and authorization != f"Bearer {expected}":
        raise HTTPException(status_code=401, detail="Invalid analysis token")


def sample_text(path: str) -> str:
    capture = cv2.VideoCapture(path)
    frames, count = [], int(capture.get(cv2.CAP_PROP_FRAME_COUNT) or 0)
    for position in sorted(set([0, count // 4, count // 2, count * 3 // 4, max(0, count - 1)])):
        capture.set(cv2.CAP_PROP_POS_FRAMES, position)
        ok, frame = capture.read()
        if ok:
            frames.append(frame)
    capture.release()
    return " ".join(text for frame in frames for text in ocr.readtext(frame, detail=0))


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/v1/analyze", dependencies=[Depends(authorize)])
def analyze(request: AnalysisRequest):
    suffix = Path(str(request.media_url)).suffix or ".mp4"
    with tempfile.NamedTemporaryFile(suffix=suffix) as media:
        urllib.request.urlretrieve(str(request.media_url), media.name)
        segments, info = whisper.transcribe(media.name, vad_filter=True)
        transcript = " ".join(segment.text.strip() for segment in segments).strip()
        detected_text = sample_text(media.name)
    metadata_text = " ".join(str(value) for value in request.metadata.values() if value)
    semantic_text = " ".join(filter(None, [request.caption or "", " ".join(request.hashtags), metadata_text, transcript, detected_text]))
    embedding = encoder.encode(semantic_text, normalize_embeddings=True).tolist()
    return {"video_id": request.video_id, "language": info.language, "transcript": transcript, "detected_text": detected_text, "embedding": embedding}
