FROM python:3.10-slim

WORKDIR /app

COPY requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

COPY data/ ./data/
COPY models/ ./models/
COPY src/ ./src/

RUN addgroup --system app && adduser --system --ingroup app app \
    && chown -R app:app /app
USER app

CMD ["uvicorn", "src.api:app", "--host", "0.0.0.0", "--port", "8000"]