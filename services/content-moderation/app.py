import os
import tempfile
from contextlib import asynccontextmanager

import cv2
import httpx
import open_clip
import torch
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, HttpUrl
from PIL import Image

SPORT_PROMPTS = [
    "a sports match or competition", "an athlete training or exercising",
    "sports equipment, a field, court, track, pool, gym or stadium",
    "a coach, referee, sports interview, tactics discussion or team activity",
    "football, rugby, cricket, netball, athletics, basketball, tennis, swimming, cycling, boxing or another sport",
]
NON_SPORT_PROMPTS = [
    "ordinary non-sports daily life", "food, shopping, fashion or beauty content",
    "a party, dance, music performance or entertainment unrelated to sport",
    "a pet, landscape, vehicle, office or household scene unrelated to sport",
    "a meme, advertisement or talking person with no sports context",
]
MAX_DOWNLOAD_BYTES = 250 * 1024 * 1024
SAMPLE_FRAMES = 8

class AnalyzeRequest(BaseModel):
    video_id: str
    media_url: HttpUrl | None = None
    media_kind: str = "video"
    image_urls: list[HttpUrl] = []
    caption: str | None = None
    hashtags: list[str] = []
    metadata: dict = {}

@asynccontextmanager
async def lifespan(app: FastAPI):
    model_name = os.getenv("VISION_MODEL", "ViT-B-32")
    pretrained = os.getenv("VISION_PRETRAINED", "laion2b_s34b_b79k")
    model, _, preprocess = open_clip.create_model_and_transforms(model_name, pretrained=pretrained)
    tokenizer = open_clip.get_tokenizer(model_name)
    app.state.model = model.eval()
    app.state.preprocess = preprocess
    app.state.tokenizer = tokenizer
    yield

app = FastAPI(title="SportsUniverse Sports Relevance Engine", lifespan=lifespan)

def authorize(value: str | None):
    expected = os.getenv("CONTENT_ANALYSIS_TOKEN", "")
    if expected and value != f"Bearer {expected}":
        raise HTTPException(status_code=401, detail="Invalid analysis token")

async def download(url: str, suffix: str) -> str:
    target = tempfile.NamedTemporaryFile(suffix=suffix, delete=False)
    total = 0
    async with httpx.AsyncClient(follow_redirects=True, timeout=90) as client:
        async with client.stream("GET", url) as response:
            response.raise_for_status()
            async for chunk in response.aiter_bytes():
                total += len(chunk)
                if total > MAX_DOWNLOAD_BYTES:
                    raise HTTPException(status_code=413, detail="Media is too large for analysis")
                target.write(chunk)
    target.close()
    return target.name

def sample_video(path: str) -> list[Image.Image]:
    capture = cv2.VideoCapture(path)
    count = max(1, int(capture.get(cv2.CAP_PROP_FRAME_COUNT)))
    frames = []
    for position in torch.linspace(0, count - 1, SAMPLE_FRAMES).int().tolist():
        capture.set(cv2.CAP_PROP_POS_FRAMES, position)
        ok, frame = capture.read()
        if ok:
            frames.append(Image.fromarray(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)))
    capture.release()
    return frames

def score_images(images: list[Image.Image]) -> tuple[float, list[float]]:
    if not images:
        raise HTTPException(status_code=422, detail="No readable frames or images")
    model, preprocess, tokenizer = app.state.model, app.state.preprocess, app.state.tokenizer
    batch = torch.stack([preprocess(image) for image in images])
    prompts = SPORT_PROMPTS + NON_SPORT_PROMPTS
    with torch.inference_mode():
        image_features = model.encode_image(batch)
        text_features = model.encode_text(tokenizer(prompts))
        image_features /= image_features.norm(dim=-1, keepdim=True)
        text_features /= text_features.norm(dim=-1, keepdim=True)
        similarities = (100 * image_features @ text_features.T).softmax(dim=-1)
        frame_scores = similarities[:, :len(SPORT_PROMPTS)].sum(dim=1).tolist()
    # Median-like aggregation prevents one logo or unrelated transition frame
    # from deciding the result for an otherwise valid sports video.
    ordered = sorted(float(value) for value in frame_scores)
    return ordered[len(ordered) // 2], frame_scores

@app.get("/health")
def health():
    return {"status": "ok"}

@app.post("/v1/analyze")
async def analyze(payload: AnalyzeRequest, authorization: str | None = Header(default=None)):
    authorize(authorization)
    paths: list[str] = []
    images: list[Image.Image] = []
    try:
        if payload.media_kind == "video" and payload.media_url:
            path = await download(str(payload.media_url), ".mp4")
            paths.append(path)
            images.extend(sample_video(path))
        else:
            for url in payload.image_urls[:10]:
                path = await download(str(url), ".jpg")
                paths.append(path)
                images.append(Image.open(path).convert("RGB"))
        score, frame_scores = score_images(images)
        threshold = float(os.getenv("SPORTS_REVIEW_THRESHOLD", "0.45"))
        review = score < threshold
        return {
            "sports_relevance_score": round(score, 4),
            "moderation_recommendation": "review_for_removal" if review else "keep",
            "moderation_reason": (
                f"Only {round(score * 100)}% sports relevance across {len(frame_scores)} visual samples. Review for removal."
                if review else f"Sports content detected with {round(score * 100)}% relevance across {len(frame_scores)} visual samples."
            ),
            "content_labels": ["sports_visual" if not review else "possibly_non_sports"],
            "sample_scores": [round(value, 4) for value in frame_scores],
        }
    finally:
        for path in paths:
            try:
                os.unlink(path)
            except OSError:
                pass
