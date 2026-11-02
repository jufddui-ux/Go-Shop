FROM python:3.11-slim

RUN apt-get update && apt-get install -y \
    build-essential \
    libpq-dev \
    libpq5 \
    && rm -rf /var/lib/apt/lists/*

# Create standard user with UID 1000 for Hugging Face Spaces compatibility
RUN useradd -m -u 1000 user

WORKDIR /app

COPY backend/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY backend/ .

RUN mkdir -p /app/data /app/logs && chown -R user:user /app

USER user

ENV HOME=/home/user \
    PORT=7860 \
    ADMIN_HOST=0.0.0.0 \
    DB_TYPE=sqlite \
    REDIS_ENABLED=0

EXPOSE 7860

CMD ["sh", "-c", "if [ \"$DB_TYPE\" = \"postgres\" ] || [ -n \"$DATABASE_URL\" ]; then alembic upgrade head; else alembic stamp head 2>/dev/null || true; fi && python run.py"]
