from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List
import logging

from src.predict import predict_disease, FEATURES


logger = logging.getLogger(__name__)

app = FastAPI(
    title="Vitalis AI Service",
    description="API de prediction des maladies a partir des symptomes.",
    version="1.0.0"
)


class PredictionRequest(BaseModel):
    symptoms: List[str]


@app.get("/")
def root():
    return {
        "service": "Vitalis AI Service",
        "status": "running"
    }


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model": "loaded"
    }


@app.post("/predict")
def predict(request: PredictionRequest):
    if not request.symptoms:
        raise HTTPException(
            status_code=400,
            detail="At least one symptom is required."
        )

    unknown_symptoms = [
        symptom
        for symptom in request.symptoms
        if symptom not in FEATURES
    ]

    if unknown_symptoms:
        raise HTTPException(
            status_code=400,
            detail={
                "message": "Unknown symptoms detected.",
                "unknown_symptoms": unknown_symptoms
            }
        )

    try:
        disease, confidence = predict_disease(request.symptoms)

        return {
            "disease": disease,
            "confidence": round(float(confidence), 2)
        }

    except Exception:
        logger.exception("Disease prediction failed")
        raise HTTPException(
            status_code=500,
            detail="Prediction service temporarily unavailable."
        )