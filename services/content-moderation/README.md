# Sports relevance moderation

This service samples eight frames from videos (or up to ten uploaded pictures) and uses a CLIP vision model to compare the visuals with sports and non-sports concepts.

It never deletes content. Low-confidence content is marked `flagged` in Laravel with the recommendation `review_for_removal`, then appears in the existing admin moderation queue for a human decision.

Run locally:

```bash
docker build -t sportuniverse-content-moderation .
docker run --rm -p 8100:8100 \
  -e CONTENT_ANALYSIS_TOKEN=change-this-analysis-token \
  -e SPORTS_REVIEW_THRESHOLD=0.45 \
  sportuniverse-content-moderation
```

Laravel must use a URL reachable from the container. In production, use the service's private network hostname for `CONTENT_ANALYSIS_URL` and use the same strong random token in both services.
